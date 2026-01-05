# MEMEX

[![CI](https://github.com/jacquesbh/memex-mcp/actions/workflows/ci.yml/badge.svg)](https://github.com/jacquesbh/memex-mcp/actions/workflows/ci.yml)

```
         ███╗   ███╗███████╗███╗   ███╗███████╗██╗  ██╗
         ████╗ ████║██╔════╝████╗ ████║██╔════╝╚██╗██╔╝
         ██╔████╔██║█████╗  ██╔████╔██║█████╗   ╚███╔╝ 
         ██║╚██╔╝██║██╔══╝  ██║╚██╔╝██║██╔══╝   ██╔██╗ 
         ██║ ╚═╝ ██║███████╗██║ ╚═╝ ██║███████╗██╔╝ ██╗
         ╚═╝     ╚═╝╚══════╝╚═╝     ╚═╝╚══════╝╚═╝  ╚═╝

┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│   • FREE TO USE - NO STRINGS ATTACHED                           │
│                                                                 │
│  This project is MIT licensed to maximize adoption and help     │
│  as many developers as possible. We believe in open source      │
│  through voluntary contribution rather than legal obligation.   │
│                                                                 │
│   • Found a bug? Built something cool?                          │
│   • We'd love your contribution via issue or PR!                │
│                                                                 │
│  See CONTRIBUTING.md for guidelines.                            │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```
**License:** [MIT](LICENSE) - Use freely in any project.

---

**MEM**ory + ind**EX** - A Model Context Protocol (MCP) server for managing your knowledge base of **guides** and **contexts**.

Inspired by Vannevar Bush's visionary [Memex](https://en.wikipedia.org/wiki/Memex) (1945), MEMEX augments AI memory by providing persistent access to technical guides and reusable contexts.

📖 **Documentation:** [USAGE.md](USAGE.md) (complete guide) | [AGENTS.md](AGENTS.md) (for AI agents)

## Features

- **📚 Dual Knowledge Base**: Manage both technical guides and reusable contexts
  - **Guides**: Step-by-step implementation instructions
  - **Contexts**: Personas, conventions, and prompts for AI interactions
- **✍️ Write Tools**: Create and update guides/contexts directly from Claude
- **🔍 Search Tools**: Retrieve guides and contexts with semantic search
- **🗑️ Delete Tools**: Clean up obsolete content
- **📋 List Tools**: Browse all available guides and contexts
- **🔍 Vector Search**: Semantic search powered by Ollama embeddings
- **🚀 Claude AI Integration**: Compatible with Claude 3.7+ via MCP protocol
- **📁 Flexible Storage**: Use custom knowledge base paths, shareable across projects

## Requirements

- PHP 8.3+
- Composer
- **Ollama** with `nomic-embed-text` model (for semantic search) - [Install Ollama](https://ollama.com)
- **Built with official [MCP SDK](https://github.com/modelcontextprotocol/php-sdk)** (`mcp/sdk`)

## Supported Operating Systems

- **macOS** ✅
- **Linux** ✅
- **Windows** ❌ (not supported)

**Default knowledge base location:** `~/.memex/knowledge-base`

## Quick Start

```bash
make install                    # Install dependencies
ollama pull nomic-embed-text   # Setup Ollama for semantic search
make build                      # Build binary
./memex server                  # Run server
```

Complete setup instructions: [USAGE.md](USAGE.md)

## Installation

### Download Latest Release

```bash
curl -L https://github.com/jacquesbh/memex-mcp/releases/latest/download/memex -o memex
chmod +x memex
./memex --version
```

### Self-Update

Once installed, keep MEMEX up-to-date:

```bash
./memex self-update
```



## Configuration

### Knowledge Base Path

Configure your knowledge base location in three ways (priority: CLI > Local config > Global config > Default):

1. **CLI Flag**: `./memex server --kb=/path/to/kb`
2. **Local Config**: `./memex.json` (project-specific)
3. **Global Config**: `~/.memex/memex.json` (user-wide)

**Config file format:**
```json
{
  "knowledgeBase": "/absolute/path/to/kb"
}
```

**Default**: `~/.memex/knowledge-base` (if no config provided)

### Claude Desktop

```json
{
  "mcpServers": {
    "memex": {
      "command": "/absolute/path/to/memex-mcp/memex",
      "args": ["server"]
    }
  }
}
```

Custom KB: Add `"--kb=/path"` to `args` array (optional if using config file).

All configuration options (OpenCode, custom KB, etc.): [USAGE.md](USAGE.md)

## MCP Tools (10)

| Category | Tools |
|----------|-------|
| **Guides** | `get_guide`, `list_guides`, `write_guide`, `delete_guide` |
| **Contexts** | `get_context`, `list_contexts`, `write_context`, `delete_context` |
| **Utility** | `generate_uuid`, `search_knowledge_base` |



## Knowledge Base Structure

```
knowledge-base/
├── guides/      # Technical how-to docs
├── contexts/    # AI personas/prompts
└── .vectors/    # SQLite database with embeddings
```

Files use Markdown with YAML frontmatter. Details: [USAGE.md](USAGE.md)

## Building from Source

```bash
make build
```

This creates a standalone `./memex` binary with all dependencies included.

**Manual build:**
```bash
symfony composer install
vendor/jolicode/castor/bin/castor repack --app-name=memex --logo-file=.castor.logo.php
mv memex.linux.phar memex
chmod +x memex
```

**Verification:**
```bash
./memex server
./memex stats
```

**Distribution:** Copy `memex` binary. Requires PHP 8.3+ and Ollama.



## Why MEMEX?

Inspired by Vannevar Bush's 1945 vision of a device to store and retrieve knowledge instantly, MEMEX augments AI with persistent memory for guides and contexts.

## Architecture

Built with PHP 8.3+ and the official [MCP SDK](https://github.com/modelcontextprotocol/php-sdk), MEMEX uses:
- **Services:** Guide/Context management, Markdown→JSON compilation, semantic search (Ollama embeddings)
- **Tools:** 10 MCP tools (CRUD for guides/contexts + UUID generation + semantic search)
- **CLI:** [Castor](https://github.com/jolicode/castor) framework, compiled to PHAR binary
- **Security:** Input validation, path traversal protection, 1MB content limit

## Testing

```bash
make test          # PHPUnit
make test-mcp      # MCP integration tests (requires Node.js)
```

See [AGENTS.md](AGENTS.md) for CI/CD details.

---

**Created by [Jacques Bodin-Hullin](https://github.com/jacquesbh) @ [MonsieurBiz](https://monsieurbiz.com) with [OpenCode](https://opencode.ai), a bit of AI, and a lot of time mastering AI**
