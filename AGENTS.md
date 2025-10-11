# Agent Guidelines for MEMEX

## Commands
- **Run server**: `php bin/server.php`
- **Compile guides**: `php bin/compile-guides.php`
- **Compile contexts**: `php bin/compile-contexts.php`
- **No tests configured** - verify changes by running the server

## Code Style (PHP 8.3+, Symfony)
- **Strict types**: Always use `declare(strict_types=1);` at top of file
- **Constructor DI**: Use constructor property promotion with `readonly` for services
- **Type hints**: Full type declarations on all parameters, returns, and properties
- **Namespacing**: PSR-4 autoloading (`Memex\` → `src/`)
- **Naming**: PascalCase for classes, camelCase for methods/properties
- **Abstract patterns**: Extend base classes when sharing behavior (e.g., `ContentService`)
- **Error handling**: Throw `RuntimeException` for operational errors, `InvalidArgumentException` for validation
- **Validation**: Always validate user input (title, content, slug) before operations
- **Security**: Check path traversal, validate slugs, limit content size (1MB max)
- **Array returns**: Tools return structured arrays with `success`, `error`, or data fields
- **No comments**: Code should be self-documenting
