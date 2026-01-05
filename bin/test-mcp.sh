#!/usr/bin/env bash

set -eu

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}🧪 MEMEX MCP Direct JSON-RPC Integration Tests${NC}\n"

# Use compiled binary if available and working, otherwise fall back to castor
MEMEX_BIN="./memex"
USE_CASTOR=false

if [[ ! -x "$MEMEX_BIN" ]]; then
    echo -e "${YELLOW}⚠ Binary not found, using castor instead${NC}"
    USE_CASTOR=true
else
    # Test if binary can actually run server (check for PDO extension)
    QUICK_TEST_KB="/tmp/memex-quick-test-$$"
    mkdir -p "$QUICK_TEST_KB/guides" "$QUICK_TEST_KB/contexts" "$QUICK_TEST_KB/.vectors"
    if echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0.0"}}}' | $MEMEX_BIN server --kb="$QUICK_TEST_KB" 2>&1 | grep -q "PDO\|Class.*not found"; then
        echo -e "${YELLOW}⚠ Binary missing required extensions, using castor instead${NC}"
        USE_CASTOR=true
    fi
    rm -rf "$QUICK_TEST_KB"
fi

if [[ "$USE_CASTOR" == "true" ]]; then
    MEMEX_BIN="symfony php vendor/bin/castor"
fi

TEST_KB="/tmp/memex-test-mcp-kb"
rm -rf "$TEST_KB"
mkdir -p "$TEST_KB"

echo -e "${YELLOW}📦 Initializing test knowledge base...${NC}"
$MEMEX_BIN init --kb="$TEST_KB" >/dev/null 2>&1

pass() {
    echo -e "${GREEN}✓ PASS${NC}: $1"
}

fail() {
    echo -e "${RED}✗ FAIL${NC}: $1"
    echo -e "${RED}Output:${NC} $2"
    rm -rf "$TEST_KB"
    exit 1
}

call_tool() {
    local tool_name="$1"
    local arguments="$2"
    
    local payload=$(jq -nc \
        --arg tool_name "$tool_name" \
        --argjson args "$arguments" \
        '{
            jsonrpc: "2.0",
            id: 2,
            method: "tools/call",
            params: {
                name: $tool_name,
                arguments: $args
            }
        }')
    
    (
        echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0.0"}}}'
        echo "$payload"
    ) | $MEMEX_BIN server --kb="$TEST_KB" 2>&1 | tail -1 | jq -r '.result.content[0].text // empty' 2>/dev/null
}

echo -e "\n${YELLOW}Test 1: List guides (should be empty)${NC}"
output=$(call_tool "list_guides" "{}")
if echo "$output" | tr -d '\n ' | grep -q '"total":0'; then
    pass "list_guides returns empty"
else
    fail "list_guides should be empty" "$output"
fi

echo -e "\n${YELLOW}Test 2: Generate UUID${NC}"
output=$(call_tool "generate_uuid" "{}")
GUIDE_UUID=$(echo "$output" | jq -r '.uuid // empty' 2>/dev/null)
if [[ -n "$GUIDE_UUID" ]] && [[ "$GUIDE_UUID" =~ ^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$ ]]; then
    pass "generate_uuid created valid UUID: $GUIDE_UUID"
else
    fail "generate_uuid failed" "$output"
fi

echo -e "\n${YELLOW}Test 3: Write guide with UUID${NC}"
guide_args=$(jq -n --arg uuid "$GUIDE_UUID" --arg title "Test Guide" --arg content "This is a test guide for CI/CD" '{uuid: $uuid, title: $title, content: $content}')
output=$(call_tool "write_guide" "$guide_args")
if echo "$output" | grep -q "test-guide"; then
    pass "write_guide created test-guide"
else
    fail "write_guide failed" "$output"
fi

echo -e "\n${YELLOW}Test 3b: Write large guide (20k chars) with chunking${NC}"
output=$(call_tool "generate_uuid" "{}")
LARGE_UUID=$(echo "$output" | jq -r '.uuid // empty' 2>/dev/null)
LARGE_CONTENT=$(python3 -c "print('# Large Guide\n\nThis is a large guide to test chunking.\n\n' + '\n\n'.join(['## Section ' + str(i) + '\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.' for i in range(1, 41)]))")
large_args=$(jq -n --arg uuid "$LARGE_UUID" --arg title "Large Guide" --arg content "$LARGE_CONTENT" '{uuid: $uuid, title: $title, content: $content}')
output=$(call_tool "write_guide" "$large_args")
if echo "$output" | grep -q "large-guide"; then
    CONTENT_LEN=${#LARGE_CONTENT}
    pass "write_guide created large-guide (${CONTENT_LEN} chars)"
else
    fail "write_guide large guide failed" "$output"
fi

echo -e "\n${YELLOW}Test 3c: Search in large guide${NC}"
output=$(call_tool "search_knowledge_base" '{"query":"Lorem ipsum dolor"}')
if echo "$output" | grep -q "large-guide"; then
    pass "search found content in large guide chunks"
else
    fail "search in large guide failed" "$output"
fi

echo -e "\n${YELLOW}Test 3d: Delete large guide${NC}"
output=$(call_tool "delete_guide" '{"slug":"large-guide"}')
if echo "$output" | tr -d '\n ' | grep -q '"success":true'; then
    pass "delete_guide removed large-guide"
else
    fail "delete_guide large guide failed" "$output"
fi

echo -e "\n${YELLOW}Test 4: List guides (should contain test-guide)${NC}"
output=$(call_tool "list_guides" "{}")
if echo "$output" | grep -q "test-guide"; then
    pass "list_guides contains test-guide"
else
    fail "list_guides missing test-guide" "$output"
fi

echo -e "\n${YELLOW}Test 5: Get guide by UUID${NC}"
get_args=$(jq -n --arg uuid "$GUIDE_UUID" '{uuid: $uuid}')
output=$(call_tool "get_guide" "$get_args")
if echo "$output" | grep -q "Test Guide"; then
    pass "get_guide retrieved test-guide"
else
    fail "get_guide failed" "$output"
fi

echo -e "\n${YELLOW}Test 6: Generate UUID for context${NC}"
output=$(call_tool "generate_uuid" "{}")
CONTEXT_UUID=$(echo "$output" | jq -r '.uuid // empty' 2>/dev/null)
if [[ -n "$CONTEXT_UUID" ]] && [[ "$CONTEXT_UUID" =~ ^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$ ]]; then
    pass "generate_uuid created valid UUID: $CONTEXT_UUID"
else
    fail "generate_uuid failed for context" "$output"
fi

echo -e "\n${YELLOW}Test 7: Write context with UUID${NC}"
context_args=$(jq -n --arg uuid "$CONTEXT_UUID" --arg name "Test Context" --arg content "This is a test context for CI/CD" '{uuid: $uuid, name: $name, content: $content}')
output=$(call_tool "write_context" "$context_args")
if echo "$output" | grep -q "test-context"; then
    pass "write_context created test-context"
else
    fail "write_context failed" "$output"
fi

echo -e "\n${YELLOW}Test 8: List contexts (should contain test-context)${NC}"
output=$(call_tool "list_contexts" "{}")
if echo "$output" | grep -q "test-context"; then
    pass "list_contexts contains test-context"
else
    fail "list_contexts missing test-context" "$output"
fi

echo -e "\n${YELLOW}Test 9: Get context by UUID${NC}"
get_ctx_args=$(jq -n --arg uuid "$CONTEXT_UUID" '{uuid: $uuid}')
output=$(call_tool "get_context" "$get_ctx_args")
if echo "$output" | grep -q "Test Context"; then
    pass "get_context retrieved test-context"
else
    fail "get_context failed" "$output"
fi

echo -e "\n${YELLOW}Test 10: Search knowledge base${NC}"
output=$(call_tool "search_knowledge_base" '{"query":"test"}')
if echo "$output" | grep -q "test-guide\|test-context"; then
    pass "search_knowledge_base found results"
else
    fail "search_knowledge_base failed" "$output"
fi

echo -e "\n${YELLOW}Test 10b: Generate UUID for emoji guide${NC}"
output=$(call_tool "generate_uuid" "{}")
EMOJI_GUIDE_UUID=$(echo "$output" | jq -r '.uuid // empty' 2>/dev/null)
if [[ -n "$EMOJI_GUIDE_UUID" ]]; then
    pass "generate_uuid for emoji guide: $EMOJI_GUIDE_UUID"
else
    fail "generate_uuid failed for emoji guide" "$output"
fi

echo -e "\n${YELLOW}Test 10c: Write guide with emojis in content${NC}"
EMOJI_CONTENT="# Welcome Guide 🎉

This guide contains various emojis to test UTF-8 multibyte handling.

## Features ✨

- Fast search 🔍
- Easy to use 👍
- Supports unicode: café, naïve, 日本語
- Emojis everywhere: 🚀 💡 🎯 ❤️ 🔥 ⭐

## Code Examples 💻

Here's some code with special chars:

\`\`\`php
\$message = \"Hello 世界! 🌍\";
echo \$message;
\`\`\`

## Conclusion 🏁

Thanks for reading! 🙏"

emoji_guide_args=$(jq -n --arg uuid "$EMOJI_GUIDE_UUID" --arg title "Emoji Test Guide" --arg content "$EMOJI_CONTENT" '{uuid: $uuid, title: $title, content: $content}')
output=$(call_tool "write_guide" "$emoji_guide_args")
if echo "$output" | grep -q "emoji-test-guide"; then
    pass "write_guide with emojis succeeded"
else
    fail "write_guide with emojis failed" "$output"
fi

echo -e "\n${YELLOW}Test 10d: Get emoji guide and verify content${NC}"
get_emoji_args=$(jq -n --arg uuid "$EMOJI_GUIDE_UUID" '{uuid: $uuid}')
output=$(call_tool "get_guide" "$get_emoji_args")
if echo "$output" | grep -q "🎉" && echo "$output" | grep -q "日本語"; then
    pass "get_guide preserved emojis and unicode"
else
    fail "get_guide lost emojis or unicode" "$output"
fi

echo -e "\n${YELLOW}Test 10e: Search for emoji content${NC}"
output=$(call_tool "search_knowledge_base" '{"query":"emojis unicode handling"}')
if echo "$output" | grep -q "emoji-test-guide"; then
    pass "search found emoji guide"
else
    fail "search for emoji content failed" "$output"
fi

echo -e "\n${YELLOW}Test 10f: Generate UUID for emoji context${NC}"
output=$(call_tool "generate_uuid" "{}")
EMOJI_CTX_UUID=$(echo "$output" | jq -r '.uuid // empty' 2>/dev/null)
if [[ -n "$EMOJI_CTX_UUID" ]]; then
    pass "generate_uuid for emoji context: $EMOJI_CTX_UUID"
else
    fail "generate_uuid failed for emoji context" "$output"
fi

echo -e "\n${YELLOW}Test 10g: Write context with emojis${NC}"
EMOJI_CTX_CONTENT="You are a friendly assistant 🤖 that helps users with:
- Code review 👀
- Bug fixing 🐛
- Documentation 📚

Always respond with enthusiasm! 🎉"

emoji_ctx_args=$(jq -n --arg uuid "$EMOJI_CTX_UUID" --arg name "Emoji Bot Context" --arg content "$EMOJI_CTX_CONTENT" '{uuid: $uuid, name: $name, content: $content}')
output=$(call_tool "write_context" "$emoji_ctx_args")
if echo "$output" | grep -q "emoji-bot-context"; then
    pass "write_context with emojis succeeded"
else
    fail "write_context with emojis failed" "$output"
fi

echo -e "\n${YELLOW}Test 10h: Get emoji context and verify content${NC}"
get_emoji_ctx_args=$(jq -n --arg uuid "$EMOJI_CTX_UUID" '{uuid: $uuid}')
output=$(call_tool "get_context" "$get_emoji_ctx_args")
if echo "$output" | grep -q "🤖" && echo "$output" | grep -q "🐛"; then
    pass "get_context preserved emojis"
else
    fail "get_context lost emojis" "$output"
fi

echo -e "\n${YELLOW}Test 10i: Delete emoji guide${NC}"
output=$(call_tool "delete_guide" '{"slug":"emoji-test-guide"}')
if echo "$output" | tr -d '\n ' | grep -q '"success":true'; then
    pass "delete_guide removed emoji-test-guide"
else
    fail "delete_guide emoji guide failed" "$output"
fi

echo -e "\n${YELLOW}Test 10j: Delete emoji context${NC}"
output=$(call_tool "delete_context" '{"slug":"emoji-bot-context"}')
if echo "$output" | tr -d '\n ' | grep -q '"success":true'; then
    pass "delete_context removed emoji-bot-context"
else
    fail "delete_context emoji context failed" "$output"
fi

echo -e "\n${YELLOW}Test 11: Delete guide${NC}"
output=$(call_tool "delete_guide" '{"slug":"test-guide"}')
if echo "$output" | tr -d '\n ' | grep -q '"success":true'; then
    pass "delete_guide removed test-guide"
else
    fail "delete_guide failed" "$output"
fi

echo -e "\n${YELLOW}Test 12: Delete context${NC}"
output=$(call_tool "delete_context" '{"slug":"test-context"}')
if echo "$output" | tr -d '\n ' | grep -q '"success":true'; then
    pass "delete_context removed test-context"
else
    fail "delete_context failed" "$output"
fi

echo -e "\n${YELLOW}Test 13: List guides (should be empty again)${NC}"
output=$(call_tool "list_guides" "{}")
if echo "$output" | tr -d '\n ' | grep -q '"total":0'; then
    pass "list_guides empty after cleanup"
else
    fail "list_guides should be empty" "$output"
fi

echo -e "\n${YELLOW}Test 14: List contexts (should be empty again)${NC}"
output=$(call_tool "list_contexts" "{}")
if echo "$output" | tr -d '\n ' | grep -q '"total":0'; then
    pass "list_contexts empty after cleanup"
else
    fail "list_contexts should be empty" "$output"
fi

rm -rf "$TEST_KB"

echo -e "\n${GREEN}✅ All 24 MCP integration tests passed!${NC}"
exit 0
