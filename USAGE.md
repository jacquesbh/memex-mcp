# MEMEX Usage Guide

## What is MEMEX?

**MEMEX** (**MEM**ory + ind**EX**) is an MCP (Model Context Protocol) server that manages a knowledge base for AI:
- **Guides**: Technical documentation and implementation guides
- **Contexts**: Reusable personas, prompts, and conventions

Inspired by [Vannevar Bush's Memex (1945)](https://en.wikipedia.org/wiki/Memex), MEMEX augments AI memory with persistent access to your knowledge.

## Prerequisites

- PHP 8.3+
- Composer installed
- MCP-compatible client:
  - **Claude Desktop** (recommended)
  - **Cline** (VS Code extension)
  - Any MCP-compatible client

## Initial Setup

### 1. Install Dependencies

```bash
cd /path/to/mcp-memex
composer install
```

### 2. (Optional) Configure Custom Knowledge Base

By default, the server uses the `knowledge-base/` directory. You can change this:

```bash
# Absolute path
castor server --knowledge-base=/shared/company-knowledge

# Relative path
castor server --knowledge-base=./custom-kb
```

**Use cases**:
- Share knowledge base across multiple projects
- Company-wide guide and context library
- Test with different content sets

**Note**: The directory must exist and contain `guides/` and `contexts/` subdirectories.

### 3. Test the Server Manually

**With Castor (recommended):**
```bash
castor server
```

**With MEMEX binary:**
```bash
./memex server
```

The server waits for JSON-RPC commands on STDIN. If it starts without errors, it works! ✅

---

## Using with Claude Desktop

### 1. Locate Configuration File

**macOS**:
```
~/Library/Application Support/Claude/claude_desktop_config.json
```

**Windows**:
```
%APPDATA%\Claude\claude_desktop_config.json
```

**Linux**:
```
~/.config/Claude/claude_desktop_config.json
```

### 2. Add MCP Configuration

Edit `claude_desktop_config.json`:

**With MEMEX binary (recommended)**:

```json
{
  "mcpServers": {
    "memex": {
      "command": "/absolute/path/to/mcp-memex/memex",
      "args": ["server"]
    }
  }
}
```

**With Castor (development)**:

```json
{
  "mcpServers": {
    "memex": {
      "command": "/absolute/path/to/mcp-memex/vendor/bin/castor",
      "args": ["server"]
    }
  }
}
```

**With custom knowledge base**:

```json
{
  "mcpServers": {
    "memex": {
      "command": "/absolute/path/to/mcp-memex/memex",
      "args": ["server", "--knowledge-base=/shared/company-kb"]
    }
  }
}
```

⚠️ **Important**: 
- Replace `/absolute/path/to/mcp-memex` with the **absolute** path to your project
- To build the MEMEX binary: see `BUILD.md`

### 3. Restart Claude Desktop

Completely quit and relaunch Claude Desktop.

### 4. Verify Connection

In Claude Desktop, look for the 🔌 (plug) icon in the bottom left or settings. You should see `memex` connected with 8 available tools.

### 5. Use the Server

In Claude Desktop, you can now use the tools:

**Example prompts**:
```
List available guides
```

```
Load the guide for adding a menu in Sylius admin
```

```
Write a guide for creating a custom repository in Sylius
```

```
Write a context "Sylius Expert" with best practices
```

Claude will automatically detect and use the appropriate tools (`list_guides`, `get_guide`, `write_guide`, `write_context`, etc.)

---

## Using with Cline (VS Code)

### 1. Install Cline

VS Code extension: [Cline](https://marketplace.visualstudio.com/items?itemName=saoudrizwan.claude-dev)

### 2. Configure MCP

In VS Code, open Cline settings and add the MCP server:

```json
{
  "mcpServers": {
    "memex": {
      "command": "/absolute/path/to/mcp-memex/memex",
      "args": ["server"]
    }
  }
}
```

### 3. Use in Cline

Open Cline and ask:
```
List available guides
```

Or:
```
Load the guide for adding a custom field to Sylius product form
```

---

## Usage Examples

### Example 1: Retrieve an Existing Guide

**Prompt**:
```
Load the guide for adding a menu in Sylius admin
```

Claude will use the `get_guide` tool to retrieve the guide from the knowledge base.

### Example 2: Create a Custom Guide

**Prompt**:
```
Write a guide for implementing a custom repository in Sylius with Doctrine
```

Claude will use `write_guide` to create the file in `knowledge-base/guides/`.

### Example 3: Load Context + Guide

**Prompt**:
```
Load the "Sylius Expert" context then give me the guide for creating a plugin
```

Claude will first load the context with `get_context`, then retrieve the guide with `get_guide`.

### Example 4: Knowledge Base Cleanup

**Prompt**:
```
List available guides
```

Then after analysis:
```
Delete the guide "old-deprecated-guide"
```

Claude will use `delete_guide` to clean up the base.

---

## Building MEMEX Binary

See `BUILD.md` for complete instructions.

---

## Manual Service Testing

To test compilation:

```bash
cd /path/to/mcp-memex

# With Castor
castor compile:guides
castor compile:contexts

# With binary
./memex compile:guides
./memex compile:contexts

# View stats
castor stats
# or
./memex stats
```

---

## Knowledge Base: Guides and Contexts

### What is a Guide?

A **guide** is a technical document explaining **HOW** to do something:
- Implementation steps
- Code examples
- Architecture
- Best practices

**Location**: `knowledge-base/guides/*.md`

**Example**: `knowledge-base/guides/sylius-admin-menu.md`

```markdown
---
title: "Sylius Admin Menu Item"
type: guide
tags: [sylius, admin, menu]
created: 2025-01-10
---

# Adding a Menu to Sylius Admin

## Description
Guide for adding a new element to the Sylius admin menu.

## Implementation

### Step 1: Create the listener
...
```

### What is a Context?

A **context** is a prompt/persona that defines **HOW** the AI should think/respond:
- Role/expertise (e.g., "You are a Sylius expert")
- Constraints (e.g., "Always use dependency injection")
- Conventions (e.g., "Follow PSR-12")
- Tone of voice

**Location**: `knowledge-base/contexts/*.md`

**Example**: `knowledge-base/contexts/sylius-expert.md`

```markdown
---
name: "Sylius Expert"
type: context
tags: [sylius, expert, e-commerce]
created: 2025-01-10
---

You are a Sylius expert with deep knowledge of:
- Symfony/Doctrine architecture
- Sylius patterns (Resources, Grids, State Machine)
- E-commerce best practices

## Constraints
- Always use dependency injection
- Follow Sylius conventions
- PSR-12 compliant code
```

### Adding Content Manually

**Via Claude (recommended)**:
```
Write a guide for creating a custom Sylius repository
```

**Or manually**:
1. Create `knowledge-base/guides/my-guide.md` or `knowledge-base/contexts/my-context.md`
2. Add YAML frontmatter
3. Write content in Markdown
4. Recompile:
   ```bash
   castor compile:guides
   castor compile:contexts
   # or
   ./memex compile:guides
   ./memex compile:contexts
   ```

### Recompilation

After manually adding files:

```bash
# With Castor
castor compile:guides
castor compile:contexts

# With MEMEX binary
./memex compile:guides
./memex compile:contexts

# Or delete compiled files (auto-recompile on next call)
rm knowledge-base/compiled/guides.json
rm knowledge-base/compiled/contexts.json
```

---

## Troubleshooting

### Server Won't Start

**Error**: `Class not found`

**Solution**:
```bash
composer dump-autoload
```

### Claude Desktop Doesn't See the Server

1. Check absolute path in `claude_desktop_config.json`
2. Build the binary if needed (see `BUILD.md`)
3. Check execution permissions:
   ```bash
   chmod +x memex
   ```
4. Test manually:
   ```bash
   ./memex server
   # or
   castor server
   ```
5. Check Claude Desktop logs (Menu > View > Developer > Toggle Developer Tools)

### No Guides/Contexts Found

**Problem**: `list_guides` or `list_contexts` returns empty list.

**Solution**: 
1. Verify `knowledge-base/guides/` or `knowledge-base/contexts/` contains `.md` files
2. Recompile: `castor compile:guides` or `castor compile:contexts`
3. Check compiled files: `cat knowledge-base/compiled/guides.json`

---

## Monitoring

### Check Compiled Guides/Contexts

```bash
# View compiled guides
cat knowledge-base/compiled/guides.json | jq

# View compiled contexts
cat knowledge-base/compiled/contexts.json | jq
```

### View Stats

```bash
# View stats
castor stats
# or
./memex stats
```

---

## Recommended Workflows

### Workflow 1: Use an Existing Guide

1. **Load guide**: `Load the guide for adding a Sylius menu`
2. **Analyze** returned guide
3. **Request code**: `Generate code based on this guide`
4. **Implement** the code
5. **Validate** with the guide's checklist

### Workflow 2: Create and Share Knowledge

1. **Create guide**: `Write a guide for X`
2. Guide is stored in `knowledge-base/guides/`
3. **Share** with team via `--knowledge-base=/shared/kb`
4. Entire team can now use this guide

### Workflow 3: Load Context for Specialized Responses

1. **Load context**: `Load the Sylius Expert context`
2. Claude adopts the persona/constraints from context
3. **Ask questions**: `How to implement X?`
4. Responses are contextualized with Sylius expertise

### Workflow 4: Context + Guide = Optimal Response

1. **Load context**: `Load the MonsieurBiz Code Standards context`
2. **Load guide**: `Then load the custom repository guide`
3. **Request code**: `Generate code following our standards`
4. Generated code conforms to both standards AND guide

---

## Tips

### Guides vs Contexts

- **Guide** = Technical instructions (HOW to do)
- **Context** = Persona/constraints (HOW to think)
- Both are complementary!

### Available MCP Tools (8 tools)

- `get_guide` / `get_context` - Retrieve content
- `list_guides` / `list_contexts` - List content
- `write_guide` / `write_context` - Create/update
- `delete_guide` / `delete_context` - Delete

### Performance

- Files are compiled to JSON for fast access
- Compilation is automatic when needed
- Use `list_guides` / `list_contexts` to see what's available

### Sharing

- Use `--knowledge-base=/shared/path` to share across projects
- Create a company-wide library of guides and contexts
- Version your knowledge base with Git

### Best Practices

- Name guides descriptively
- Use tags for categorization
- Create reusable contexts (e.g., "Sylius Expert", "Code Reviewer")
- Update guides when practices evolve (overwrite: true)

---

## Support

- MCP Protocol: https://modelcontextprotocol.io/
- Claude API: https://docs.anthropic.com/

---

**Enjoy! 🎉**
