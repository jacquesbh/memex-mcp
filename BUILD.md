# Building MEMEX Binary

This guide explains how to build the standalone MEMEX binary.

## Prerequisites

- PHP 8.3+
- Symfony CLI (for `symfony composer` commands)
- Composer dependencies installed (`make install`)

## Quick Build (Recommended)

Use the Makefile for simplified build process:

```bash
make build
```

This will:
1. Install Composer dependencies via Symfony CLI
2. Compile the MEMEX binary using Castor's repack command
3. Make the binary executable
4. Display success message

## Manual Build Process

If you prefer manual commands:

```bash
symfony composer install
vendor/jolicode/castor/bin/castor repack --app-name=memex --logo-file=.castor.logo.php
mv memex.linux.phar memex
chmod +x memex
```

The `--logo-file` option includes the custom MEMEX logo in the binary.

## Makefile Commands

- **`make install`**: Install dependencies (requires `composer.lock`)
- **`make build`**: Full build (install + compile binary)
- **`make clean`**: Remove binary and vendor directory
- **`make help`**: Display all available commands

## What Gets Included

The build process includes:

- **Source code**: `src/` directory
- **Configuration**: `config/` directory
- **Entry point**: `castor.php`
- **Custom logo**: Configured in `.castor.stub.php`
- **Compression**: GZ compression for smaller binary size
- **All dependencies**: Including Symfony MCP SDK and all required libraries

**Note**: Do not create a `box.json` file as it will conflict with Castor's repack command. Use `box.json.dist` for reference only.

## Verification

After building, test the binary:

```bash
./memex server
```

Or run any other command:

```bash
./memex stats
./memex compile:guides
./memex list
```

## Distribution

The `./memex` binary is a single-file executable that can be distributed without requiring:
- Composer
- vendor/ directory
- Source files

Simply copy the `memex` file and the `knowledge-base/` directory to deploy.

## Notes

- The binary includes the MEMEX logo (configured in `.castor.stub.php`)
- Build uses the Castor from `vendor/` to ensure version compatibility
- Do not use a globally installed Castor for building
