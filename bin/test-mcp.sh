#!/usr/bin/env bash

set -eu

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}🧪 MEMEX MCP Inspector Integration Tests${NC}\n"

MEMEX_BIN="./memex"
if [[ ! -x "$MEMEX_BIN" ]]; then
    echo -e "${RED}✗ FAIL: memex binary not found or not executable${NC}"
    exit 1
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
    exit 1
}

call_tool() {
    local tool_name="$1"
    local arguments="$2"

    local cmd=(npx --yes @modelcontextprotocol/inspector 
               --cli "$MEMEX_BIN" server --kb="$TEST_KB"
               --method tools/call
               --tool-name "$tool_name")

    if [[ "$arguments" != "{}" ]]; then
        while IFS= read -r line; do
            cmd+=(--tool-arg "$line")
        done < <(echo "$arguments" | jq -r 'to_entries[] | "\(.key)=\(.value)"')
    fi

    "${cmd[@]}" 2>/dev/null | \
        grep -v "^>" | \
        jq -c '.content[0].text // empty' 2>/dev/null | \
        sed 's/^"//;s/"$//' | \
        sed 's/\\n/\n/g' | \
        sed 's/\\"/"/g'
}

echo -e "\n${YELLOW}Test 1: List guides (should be empty)${NC}"
output=$(call_tool "list_guides" "{}")
if echo "$output" | grep -q '"total":0'; then
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
output=$(call_tool "write_guide" "{\"uuid\":\"$GUIDE_UUID\",\"title\":\"Test Guide\",\"content\":\"This is a test guide for CI/CD\"}")
if echo "$output" | grep -q "test-guide"; then
    pass "write_guide created test-guide"
else
    fail "write_guide failed" "$output"
fi

echo -e "\n${YELLOW}Test 4: List guides (should contain test-guide)${NC}"
output=$(call_tool "list_guides" "{}")
if echo "$output" | grep -q "test-guide"; then
    pass "list_guides contains test-guide"
else
    fail "list_guides missing test-guide" "$output"
fi

echo -e "\n${YELLOW}Test 5: Get guide by UUID${NC}"
output=$(call_tool "get_guide" "{\"uuid\":\"$GUIDE_UUID\"}")
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
output=$(call_tool "write_context" "{\"uuid\":\"$CONTEXT_UUID\",\"name\":\"Test Context\",\"content\":\"This is a test context for CI/CD\"}")
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
output=$(call_tool "get_context" "{\"uuid\":\"$CONTEXT_UUID\"}")
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

echo -e "\n${YELLOW}Test 11: Delete guide${NC}"
output=$(call_tool "delete_guide" '{"slug":"test-guide"}')
if echo "$output" | grep -q '"success":true'; then
    pass "delete_guide removed test-guide"
else
    fail "delete_guide failed" "$output"
fi

echo -e "\n${YELLOW}Test 12: Delete context${NC}"
output=$(call_tool "delete_context" '{"slug":"test-context"}')
if echo "$output" | grep -q '"success":true'; then
    pass "delete_context removed test-context"
else
    fail "delete_context failed" "$output"
fi

echo -e "\n${YELLOW}Test 13: List guides (should be empty again)${NC}"
output=$(call_tool "list_guides" "{}")
if echo "$output" | grep -q '"total":0'; then
    pass "list_guides empty after cleanup"
else
    fail "list_guides should be empty" "$output"
fi

echo -e "\n${YELLOW}Test 14: List contexts (should be empty again)${NC}"
output=$(call_tool "list_contexts" "{}")
if echo "$output" | grep -q '"total":0'; then
    pass "list_contexts empty after cleanup"
else
    fail "list_contexts should be empty" "$output"
fi

rm -rf "$TEST_KB"

echo -e "\n${GREEN}✅ All 14 MCP integration tests passed!${NC}"
exit 0
