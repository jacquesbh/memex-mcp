# MEMEX

```
 ███╗   ███╗███████╗███╗   ███╗███████╗██╗  ██╗
 ████╗ ████║██╔════╝████╗ ████║██╔════╝╚██╗██╔╝
 ██╔████╔██║█████╗  ██╔████╔██║█████╗   ╚███╔╝ 
 ██║╚██╔╝██║██╔══╝  ██║╚██╔╝██║██╔══╝   ██╔██╗ 
 ██║ ╚═╝ ██║███████╗██║ ╚═╝ ██║███████╗██╔╝ ██╗
 ╚═╝     ╚═╝╚══════╝╚═╝     ╚═╝╚══════╝╚═╝  ╚═╝
```

**MEM**ory + ind**EX** - A Model Context Protocol (MCP) server for managing your knowledge base of **guides** and **contexts**.

Inspired by Vannevar Bush's visionary [Memex](https://en.wikipedia.org/wiki/Memex) (1945), a theoretical proto-hypertext system for storing and retrieving knowledge. Just as Bush imagined a device to augment human memory, MEMEX augments AI memory by providing persistent access to technical guides and reusable contexts (prompts/personas).

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

## Installation

1. Clone the repository
2. Install dependencies:
```bash
make install
# or manually: symfony composer install
```
3. Setup Ollama (required for semantic search):
```bash
# Install from https://ollama.com then:
ollama pull nomic-embed-text
```

## Testing

**PHPUnit Tests (105 tests, 281 assertions):**
```bash
make test
```

**MCP Inspector Integration Tests:**
```bash
# Build binary first
make build

# Initialize knowledge base
./memex init

# Run MCP integration tests
make test-mcp
```

**Prerequisites for MCP tests:**
- Node.js 20+ installed
- `memex` binary built (`make build`)
- Tests use `@modelcontextprotocol/inspector` (auto-installed via npx)



## Quick Start

### Running the MCP Server

**Development (with Castor):**
```bash
castor server
```

**Production (with memex binary):**
```bash
./memex server
```

**With custom knowledge base:**
```bash
castor server --knowledge-base=/path/to/shared/kb
# or
./memex server --knowledge-base=/path/to/shared/kb
```

For complete usage instructions, see [`USAGE.md`](USAGE.md).

### Claude Desktop Configuration

Add to `~/Library/Application Support/Claude/claude_desktop_config.json`:

**Option 1: Using memex binary (recommended):**
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

**Option 2: Using Castor (development):**
```json
{
  "mcpServers": {
    "memex": {
      "command": "/absolute/path/to/memex-mcp/vendor/bin/castor",
      "args": ["server"]
    }
  }
}
```



With custom knowledge base (any option):
```json
{
  "mcpServers": {
    "memex": {
      "command": "/absolute/path/to/memex-mcp/memex",
      "args": ["server", "--knowledge-base=/shared/company-kb"]
    }
  }
}
```

## MCP Tools

### Guides (Technical How-To)

- **`get_guide`**: Retrieve a guide by query
  ```
  get_guide("sylius admin menu")
  ```

- **`list_guides`**: List all available guides
  ```
  list_guides()
  ```

- **`write_guide`**: Create or update a guide
  ```
  write_guide(
    title: "Implementing Custom Repository",
    content: "# Guide content in Markdown...",
    tags: ["sylius", "doctrine"],
    overwrite: false
  )
  ```

- **`delete_guide`**: Delete a guide
  ```
  delete_guide(slug: "old-guide")
  ```

### Contexts (Prompts/Personas)

- **`get_context`**: Retrieve a context by query
  ```
  get_context("sylius expert")
  ```

- **`list_contexts`**: List all available contexts
  ```
  list_contexts()
  ```

- **`write_context`**: Create or update a context
  ```
  write_context(
    name: "Sylius Expert",
    content: "You are an expert in Sylius e-commerce...",
    tags: ["sylius", "expert"],
    overwrite: false
  )
  ```

- **`delete_context`**: Delete a context
  ```
  delete_context(slug: "old-context")
  ```



## Knowledge Base Structure

```
knowledge-base/
├── guides/                     # Technical guides
│   ├── sylius-admin-menu.md
│   └── custom-repository.md
├── contexts/                   # Contexts/prompts
│   ├── sylius-expert.md
│   └── code-review.md
└── compiled/                   # Auto-generated
    ├── guides.json
    └── contexts.json
```

### Guide Format

```markdown
---
title: "Implementing a Custom Repository"
type: guide
tags: [sylius, repository, doctrine]
created: 2025-01-10
---

# Implementing a Custom Repository

## Overview
Step-by-step guide to create a custom Doctrine repository in Sylius.

## Steps
1. Create the interface
2. Implement the repository class
...
```

### Context Format

```markdown
---
name: "Sylius Expert"
type: context
tags: [sylius, expert, e-commerce]
created: 2025-01-10
---

You are an expert in Sylius e-commerce framework with deep knowledge of:
- Symfony best practices
- Doctrine ORM patterns
- Sylius plugin architecture

## Constraints
- Always use dependency injection
- Follow PSR-12 coding standards
```

## Workflow Examples

### Example 1: Building Team Knowledge Base

```
User: "Write a guide for creating a Sylius plugin"
→ write_guide creates knowledge-base/guides/create-sylius-plugin.md

User: "Write a context for MonsieurBiz code standards"
→ write_context creates knowledge-base/contexts/monsieurbiz-standards.md

User: "List all guides"
→ list_guides shows available guides

User: "Load the MonsieurBiz context and give me the guide for creating a plugin"
→ get_context + get_guide = contextualized response
```

### Example 2: Shared Knowledge Base

```bash
# Project A
castor server --knowledge-base=/shared/company-kb
# or
./memex server --knowledge-base=/shared/company-kb

# Project B
castor server --knowledge-base=/shared/company-kb
# or
./memex server --knowledge-base=/shared/company-kb

# Both projects share the same guides and contexts!
```

### Example 3: Cleanup

```
User: "List guides"
→ Shows 10 guides including "old-deprecated-guide"

User: "Delete the guide old-deprecated-guide"
→ delete_guide removes it and recompiles index
```

## Building the MEMEX Binary

**Quick build:**
```bash
make build
```

See [`BUILD.md`](BUILD.md) for complete build instructions and manual process.

## Manual Compilation

**With Castor/MEMEX:**
```bash
castor compile:guides
castor compile:contexts
# or
./memex compile:guides
./memex compile:contexts
```



## Why MEMEX?

In 1945, Vannevar Bush envisioned the **Memex** - a device that would store all of one's books, records, and communications, making them instantly retrievable. It was a revolutionary idea that preceded the internet by decades.

MEMEX brings this vision to AI development:
- **Store** your technical guides and best practices
- **Retrieve** them instantly via semantic search
- **Share** knowledge across projects and teams
- **Augment** AI memory with persistent, curated context

**MEMEX** = **MEM**ory + ind**EX** - Your AI's external memory system.

## Architecture

```
castor.php                         # CLI entry point (all functionality)
src/
├── Service/
│   ├── ContentService.php        # Abstract base for guides/contexts
│   ├── GuideService.php          # Guide management
│   ├── ContextService.php        # Context management
│   ├── PatternCompilerService.php # Markdown → JSON compiler
│   └── VectorService.php         # Semantic search with embeddings
└── Tool/
    ├── GetGuideTool.php
    ├── GetContextTool.php
    ├── ListGuidesTool.php
    ├── ListContextsTool.php
    ├── WriteGuideTool.php
    ├── WriteContextTool.php
    ├── DeleteGuideTool.php
    ├── DeleteContextTool.php
    └── SearchTool.php
```

### CLI Layer (Castor)

MEMEX uses [Castor](https://github.com/jolicode/castor) as its CLI framework:
- **Development**: Run `castor <command>` for interactive development
- **Production**: Build with `castor build` to create standalone `./memex` binary
- **Distribution**: Single PHAR file with all dependencies included

## Security

All write/delete operations include:
- ✅ Input validation (title/name, content size)
- ✅ Slug sanitization (alphanumeric + hyphens only)
- ✅ Path traversal protection
- ✅ File size limits (1MB max)
- ✅ Safe YAML frontmatter generation

## Use Cases

### 1. Team Documentation
Build a shared library of implementation guides accessible to all developers via AI.

### 2. AI Personas
Create reusable contexts/personas (e.g., "Sylius Expert", "Security Reviewer") that define how Claude should respond.

### 3. Project Conventions
Store project-specific coding standards, conventions, and best practices as contexts.

### 4. Cross-Project Knowledge
Share guides and contexts across multiple projects using `--knowledge-base`.

### 5. Living Documentation
Update guides and contexts directly from Claude conversations as knowledge evolves.

## Status: ✅ Complete

### Phase 1-2 ✅
- ✅ Original guide generation tool (Claude AI powered)
- ✅ Basic knowledge base with patterns

### Phase 3 ✅ (Current)
- ✅ Dual content types (guides + contexts)
- ✅ 8 new MCP tools for full CRUD operations
- ✅ Abstract service architecture (DRY, extensible)
- ✅ Automatic compilation system
- ✅ Custom knowledge base path support
- ✅ Security validations
- ✅ Markdown frontmatter support

## Next Steps (Future)

**Phase 4**: Enhanced features
- Semantic search with embeddings
- Version control for guides/contexts
- Import/export functionality
- Template system for quick guide creation
- Guide validation and linting

## Contributing

1. Add guides to `knowledge-base/guides/`
2. Add contexts to `knowledge-base/contexts/`
3. Use frontmatter for metadata
4. Test with `list_guides` and `list_contexts`
5. Share your knowledge base path with the team!

---

**Created by [Jacques Bodin-Hullin](https://github.com/jacquesbh) @ [MonsieurBiz](https://monsieurbiz.com) with [OpenCode](https://opencode.ai), a bit of AI, and a lot of time mastering AI**
