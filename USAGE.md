# MEMEX Usage Guide

📖 **Quick overview:** [README.md](README.md) | **For AI agents:** [AGENTS.md](AGENTS.md)

MCP server for managing AI knowledge base (guides + contexts). See [README.md](README.md) for overview.

## Prerequisites

- PHP 8.3+
- **Ollama** with `nomic-embed-text` model ([Install](https://ollama.com))
- MCP-compatible client:
  - **Claude Desktop** (recommended)
  - **Cline** (VS Code extension)
  - Any MCP-compatible client

## Installation

### Option 1: Download Binary (Recommended)

```bash
# Download latest release
curl -L https://github.com/jacquesbh/memex-mcp/releases/latest/download/memex -o memex
chmod +x memex

# Verify installation
./memex --version

# Check for updates anytime
./memex check-update

# Update to latest version
./memex self-update
```

### Option 2: Build from Source

```bash
cd /path/to/memex-mcp
composer install
make build
```

## Initial Setup

### 1. Setup Ollama

MEMEX uses Ollama for semantic search. Install from [ollama.com](https://ollama.com), then:

```bash
# Pull required embedding model
ollama pull nomic-embed-text

# Verify it's running
ollama list
```

### 2. (Optional) Configure Custom Knowledge Base

By default, the server uses `~/.memex/knowledge-base`. You can override this in three ways (in order of priority):

#### Option 1: CLI Flag (Highest Priority)
```bash
# Absolute path
castor server --kb=/shared/company-knowledge

# Relative path
castor server --kb=./custom-kb
```

#### Option 2: Configuration File

Create `memex.json` in either location:

**Project-specific** (`./<project>/memex.json`):
```json
{
  "knowledgeBase": "/absolute/path/to/kb"
}
```

**Global** (`~/.memex/memex.json`):
```json
{
  "knowledgeBase": "/Users/you/Documents/memex-kb"
}
```

**Priority**: Local config > Global config > Default path

#### Option 3: Default Path
Falls back to `~/.memex/knowledge-base` if no config or CLI flag provided.

**Use cases**:
- Share knowledge base across multiple projects
- Company-wide guide and context library
- Test with different content sets
- Per-project knowledge bases

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
      "command": "/absolute/path/to/memex-mcp/memex",
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
      "command": "/absolute/path/to/memex-mcp/vendor/bin/castor",
      "args": ["server"]
    }
  }
}
```

**Custom knowledge base**: Add `"--kb=/shared/company-kb"` to `args` array.

⚠️ Use absolute paths. Build binary: see [README.md](README.md).

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

Same configuration as Claude Desktop. Add to Cline MCP settings.

## Using with OpenCode

Add to `~/.config/opencode/opencode.json`:

```json
{
  "mcp": {
    "memex-mcp": {
      "type": "local",
      "command": ["/absolute/path/to/memex-mcp/memex", "server"],
      "enabled": true
    }
  }
}
```

**Custom KB**: Add `"--kb=/path"` to command array.

---

## Manual Service Testing

To test compilation:

```bash
cd /path/to/memex-mcp

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

## Knowledge Base Format

**Guides** (technical how-to): `knowledge-base/guides/*.md`  
**Contexts** (AI personas): `knowledge-base/contexts/*.md`

Both use Markdown with YAML frontmatter. See [README.md](README.md) for structure.

**Creating content:**
- Via your LLM (Claude, OpenAI…) connected to the MCP: `"Write a guide for X"` or `"Write a context Y"`
- Manually: Create `.md` file, add frontmatter, run `./memex compile:guides` or `./memex compile:contexts`

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

### Ollama Connection Errors

**Error**: `Failed to get embeddings from Ollama`

**Solutions:**
```bash
# Verify Ollama is running
ollama list

# Install model if missing
ollama pull nomic-embed-text
```

If Ollama isn't installed, get it from [ollama.com](https://ollama.com).

---

## Monitoring

### View Stats

```bash
# View stats
castor stats
# or
./memex stats
```

---

## Example Workflows

**Load guide**: `"Load the guide for adding a Sylius menu"`  
**Create guide**: `"Write a guide for implementing custom repository"`  
**Load context + guide**: `"Load Sylius Expert context, then load the plugin guide"`  
**Share KB**: Use `--kb=/shared/kb` across projects

## Tips

- **Guides** = technical how-to | **Contexts** = AI personas
- Share KB across projects with `--kb`
- Use tags for organization
- Version KB with Git

---

## Updating MEMEX

### Check for Updates

```bash
./memex check-update
```

This compares your local version against the latest GitHub Release.

### Self-Update

```bash
./memex self-update
```

MEMEX will:
1. Download the latest binary from GitHub Releases
2. Verify integrity using SHA-256 checksum
3. Replace the current binary atomically
4. Display old and new version hashes

**Requirements:**
- Internet connection
- Write permissions on the binary file
- Only works with PHAR builds (not when running from source)

### Manual Update

If self-update fails, download manually:

```bash
# Backup current version
cp memex memex.backup

# Download latest
curl -L https://github.com/jacquesbh/memex-mcp/releases/latest/download/memex -o memex
chmod +x memex

# Verify
./memex --version
```

**Rollback** if needed:
```bash
mv memex.backup memex
```

---

**Documentation:** [README.md](README.md) | [AGENTS.md](AGENTS.md)
