# MCP UI Element - Implementation Guide Generator

A Symfony-based Model Context Protocol (MCP) server that generates structured implementation guides for UI elements using Claude AI.

## Features

- **MCP Tool Integration**: Exposes `generate-implementation-guide` tool via MCP protocol
- **Claude AI Integration**: Uses Claude 3.7 Sonnet (compatible with Claude 4) for guide generation
- **Knowledge Base**: Markdown-based patterns compiled to JSON for efficient retrieval
- **STDIO Transport**: Compatible with Claude Desktop and other MCP clients

## Requirements

- PHP 8.3+
- Composer
- Claude API Key

## Installation

1. Clone the repository
2. Install dependencies:
```bash
composer install
```

3. Configure environment:
```bash
cp .env .env.local
# Edit .env.local and add your CLAUDE_API_KEY
```

## Usage

### Running the MCP Server

```bash
php bin/server.php
```

### Claude Desktop Configuration

Add to your Claude Desktop config (`~/Library/Application Support/Claude/claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "mcp-ui-element": {
      "command": "php",
      "args": ["/absolute/path/to/mcp-ui-element/bin/server.php"]
    }
  }
}
```

## Architecture

- **bin/server.php**: MCP server entry point
- **src/Tool/**: MCP tool implementations
- **src/Service/**: Business logic (Claude API, Knowledge Base, Pattern Compiler)
- **knowledge-base/**: Markdown patterns and constraints
- **knowledge-base/compiled/**: Compiled JSON patterns (generated)

## Testing the Server

Test MCP protocol manually:
```bash
(echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}' && \
 sleep 0.5 && \
 echo '{"jsonrpc":"2.0","method":"notifications/initialized"}' && \
 sleep 0.5 && \
 echo '{"jsonrpc":"2.0","id":2,"method":"tools/list"}' && \
 sleep 1) | php bin/server.php
```

Test tool execution:
```bash
(echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}' && \
 sleep 0.5 && \
 echo '{"jsonrpc":"2.0","method":"notifications/initialized"}' && \
 sleep 0.5 && \
 echo '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"generate-implementation-guide","arguments":{"elementType":"Button","requirements":"A primary action button with hover effects"}}}' && \
 sleep 1) | php bin/server.php
```

## Phase 1 Status: ✅ Complete

- [x] Project initialized with Symfony
- [x] Dependencies installed (php-mcp/server, claude-php/claude-3-api, league/commonmark)
- [x] Directory structure created
- [x] Configuration files set up
- [x] MCP server entry point created
- [x] **Server functional with minimal tool implementation**
- [x] Tool discoverable and callable via MCP protocol

## Current Status

The MCP server is **FULLY OPERATIONAL** with complete Phase 1 & 2 implementation:

### Phase 1 ✅ Complete
- ✅ Symfony project structure
- ✅ MCP protocol integration (php-mcp/server)
- ✅ Tool discovery and registration
- ✅ STDIO transport for Claude Desktop

### Phase 2 ✅ Complete
- ✅ **ClaudeApiService** - Claude 3.7 Sonnet integration
- ✅ **KnowledgeBaseService** - Pattern management & matching
- ✅ **PatternCompilerService** - Markdown → JSON compilation
- ✅ **GuideGeneratorService** - Orchestrates Claude + patterns
- ✅ **GenerateImplementationGuideTool** - Full integration with services
- ✅ Sample patterns (Button, Form Input)

### What Works NOW
- 🚀 Complete guide generation via Claude API
- 🧠 Pattern-based context enrichment
- 📝 Structured JSON output with 7-step implementation guides
- 🎯 Framework-specific recommendations (React, Vue, Angular, etc.)
- ♿ Accessibility considerations (WCAG)
- ✅ Validation checklists
- 🔄 Fallback guide generation on API errors

## Example Output

```json
{
  "element_type": "Button",
  "framework": "React",
  "analysis": "Detailed analysis...",
  "architecture": {
    "structure": "Description...",
    "components": ["Component1", "Component2"]
  },
  "implementation_steps": [
    {
      "step": 1,
      "title": "Step title",
      "description": "Detailed description",
      "considerations": ["Point 1", "Point 2"]
    }
  ],
  "patterns": ["Pattern1", "Pattern2"],
  "constraints": ["Constraint1", "Constraint2"],
  "validation_checklist": ["Check1", "Check2"]
}
```

## Next Steps (Phase 3+)

**Phase 3**: Enhanced knowledge base
- Add more UI patterns (Modal, Dropdown, Card, etc.)
- Add constraint patterns (Performance, Security, A11y)
- Pattern versioning and updates

**Phase 4**: Advanced features
- Multi-language support
- Custom pattern injection
- Guide export formats (Markdown, PDF)
- Integration with design systems
