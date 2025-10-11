# Agent Guidelines for MEMEX

## Commands

### Development (Castor CLI)
- **Run server**: `castor server`
- **Compile guides**: `castor compile:guides`
- **Compile contexts**: `castor compile:contexts`
- **Initialize KB**: `castor init`
- **View stats**: `castor stats`
- **System check**: `castor doctor`
- **List all commands**: `castor list`

### Production (MEMEX Binary)
- **Build binary**: See `BUILD.md` for instructions
- **Run server**: `./memex server`
- **All commands**: Replace `castor` with `./memex`

### Testing
- **Run PHPUnit tests**: `make test` or `vendor/bin/phpunit`
- **Run MCP integration tests**: `make test-mcp` (requires Node.js 20+)
- **Test specific file**: `vendor/bin/phpunit tests/Service/GuideServiceTest.php`
- **Test with output**: `vendor/bin/phpunit --testdox`

### GitHub Actions CI/CD
- **CI Pipeline**: `.github/workflows/ci.yml`
  - Runs on push/PR to `main`
  - Jobs: `test` (PHPUnit) → `build` (binary) → `test-mcp` (integration)
  - Badge: `![CI](https://github.com/jacquesbh/memex-mcp/actions/workflows/ci.yml/badge.svg)`
- **Release Pipeline**: `.github/workflows/release.yml`
  - Triggered on `v*` tags (e.g., `v1.0.0`)
  - Builds binary and creates GitHub Release
  - Auto-attaches `memex` binary to release

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
