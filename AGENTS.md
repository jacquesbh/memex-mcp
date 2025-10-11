# Agent Guidelines for MEMEX

📖 **Documentation:** [README.md](README.md) (overview) | [USAGE.md](USAGE.md) (complete guide)

## Commands Reference

**Dev:** `castor server`, `castor compile:guides`, `castor init`, `castor stats`
**Prod:** `./memex server` (replace `castor` with `./memex`)
**Testing:** `make test` (PHPUnit), `make test-mcp` (MCP integration)
**Build:** `make build` (creates binary)
**Update:** `./memex --version`, `./memex check-update`, `./memex self-update`

Complete command reference: [USAGE.md](USAGE.md)

## CI/CD

**CI:** `.github/workflows/ci.yml` - Runs on push/PR: test → build → test-mcp
**Release:** `.github/workflows/release.yml` - Triggered on `v*` tags, creates GitHub Release with:
  - `memex` binary
  - `memex.sha256` (for self-update verification)
  - `memex.sha512` (for manual verification)

## Code Style (PHP 8.3+, Symfony MCP SDK)
- **Strict types**: Always use `declare(strict_types=1);` at top of file
- **Constructor DI**: Use constructor property promotion with `readonly` for services
- **Type hints**: Full type declarations on all parameters, returns, and properties
- **Namespacing**: PSR-4 autoloading (`Memex\` → `src/`)
- **Naming**: PascalCase for classes, camelCase for methods/properties
- **Abstract patterns**: Extend base classes when sharing behavior (e.g., `ContentService`, `AbstractToolMetadata`)
- **Tool architecture**: Separate Metadata (schema) from Executor (logic)
- **Error handling**: Throw `RuntimeException` for operational errors, `InvalidArgumentException` for validation
- **Validation**: Always validate user input (title, content, slug) before operations
- **Security**: Check path traversal, validate slugs, limit content size (1MB max)
- **Tool returns**: Executors return `ToolCallResult` with structured data
- **No comments**: Code should be self-documenting

## Tool Creation Pattern
When adding a new MCP tool:
1. Create `src/Tool/Metadata/XxxToolMetadata.php` extending `AbstractToolMetadata`
2. Create `src/Tool/Executor/XxxToolExecutor.php` implementing `ToolExecutorInterface` + `IdentifierInterface`
3. Add both to `MemexToolChain` constructor
4. Test with Claude Desktop
