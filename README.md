# MEMEX

![CI](https://github.com/jacquesbh/memex-mcp/actions/workflows/ci.yml/badge.svg)

```
 ███╗   ███╗███████╗███╗   ███╗███████╗██╗  ██╗
 ████╗ ████║██╔════╝████╗ ████║██╔════╝╚██╗██╔╝
 ██╔████╔██║█████╗  ██╔████╔██║█████╗   ╚███╔╝ 
 ██║╚██╔╝██║██╔══╝  ██║╚██╔╝██║██╔══╝   ██╔██╗ 
 ██║ ╚═╝ ██║███████╗██║ ╚═╝ ██║███████╗██╔╝ ██╗
 ╚═╝     ╚═╝╚══════╝╚═╝     ╚═╝╚══════╝╚═╝  ╚═╝
```

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
- **🔄 Auto-Compilation**: Markdown files compiled to JSON for fast retrieval
- **🚀 Claude AI Integration**: Compatible with Claude 3.7+ via MCP protocol
- **📁 Flexible Storage**: Use custom knowledge base paths, shareable across projects

## Requirements

- PHP 8.3+
- Composer
- **Ollama** with `nomic-embed-text` model (for semantic search) - [Install Ollama](https://ollama.com)
- **Node.js 20+** (for MCP Inspector integration tests)
- **Built with Symfony MCP SDK** (official Symfony AI SDK)

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



## Configuration

**Claude Desktop**
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

All configuration options (OpenCode, custom KB, etc.): [USAGE.md](USAGE.md)

## MCP Tools

**Guides:** `get_guide`, `list_guides`, `write_guide`, `delete_guide`
**Contexts:** `get_context`, `list_contexts`, `write_context`, `delete_context`



## Knowledge Base Structure

```
knowledge-base/
├── guides/      # Technical how-to docs
├── contexts/    # AI personas/prompts
└── ,vectors/    # SQLite database with embeddings
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

Built with PHP 8.3+ and [Symfony MCP SDK](https://symfony.com/doc/current/ai_sdk.html), MEMEX uses:
- **Services:** Guide/Context management, Markdown→JSON compilation, semantic search (Ollama embeddings)
- **Tools:** 8 MCP tools (get, list, write, delete for guides/contexts)
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
