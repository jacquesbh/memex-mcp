PHP_VERSION ?= $(shell cat .php-version)
PHP_EXTENSIONS ?= mbstring,phar,posix,tokenizer,curl,filter,openssl,pdo,pdo_sqlite

.PHONY: help install clean check-arch build local.install test test-mcp test-embed coverage

help: ## Display this help
	@grep -E '^[a-zA-Z._-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

install: vendor ## Install Composer dependencies

clean: ## Clean generated files (binary and vendor)
	rm -f memex memex.linux.phar
	rm -f composer.lock
	rm -rf vendor/

check-arch: ## Verify architecture compatibility for build
	@echo "🔍 Checking build architecture..."
	@HOST_ARCH=$$(uname -m); \
	PROC_ARCH=$$(arch); \
	OS_NAME=$$(uname -s); \
	ROSETTA=0; \
	if [ "$$OS_NAME" = "Darwin" ]; then \
		if sysctl -n sysctl.proc_translated >/dev/null 2>&1; then \
			ROSETTA=$$(sysctl -n sysctl.proc_translated); \
		fi; \
	fi; \
	if ! command -v symfony >/dev/null 2>&1; then \
		echo "✗ symfony CLI not found in PATH"; \
		exit 1; \
	fi; \
	SYMFONY_PHP_ARCH=$$(symfony php -r 'echo php_uname("m");' 2>/dev/null); \
	if [ -z "$$SYMFONY_PHP_ARCH" ]; then \
		echo "✗ Unable to determine architecture for symfony php"; \
		exit 1; \
	fi; \
	echo "Host arch: $$HOST_ARCH"; \
	echo "Process arch: $$PROC_ARCH"; \
	if [ "$$OS_NAME" = "Darwin" ]; then \
		echo "Rosetta translated: $$ROSETTA"; \
	fi; \
	echo "Symfony PHP arch: $$SYMFONY_PHP_ARCH"; \
	if [ "$$OS_NAME" = "Darwin" ] && [ "$$ROSETTA" = "1" ]; then \
		echo "✗ Terminal session is translated (Rosetta). Use a native arm64 shell."; \
		exit 1; \
	fi; \
	if [ "$$HOST_ARCH" != "$$SYMFONY_PHP_ARCH" ]; then \
		echo "✗ symfony php architecture ($$SYMFONY_PHP_ARCH) does not match host ($$HOST_ARCH)."; \
		echo "  Hint: remove ~/.symfony5/php and re-run, or use an arm64 Symfony PHP runtime."; \
		exit 1; \
	fi

build: check-arch install ## Build the MEMEX binary (installs dependencies first)
	$(eval VERSION := $(shell grep "const MEMEX_VERSION" castor.php | sed "s/.*'\(.*\)'.*/\1/"))
	symfony php vendor/jolicode/castor/bin/castor repack --app-name=memex --app-version=$(VERSION) --logo-file=.castor.logo.php
	symfony php vendor/jolicode/castor/bin/castor compile memex.linux.phar --binary-path=memex --php-version=$(PHP_VERSION) --php-extensions=$(PHP_EXTENSIONS) --os=$(shell uname -s | tr '[:upper:]' '[:lower:]' | sed 's/darwin/macos/') --arch=$(shell uname -m | sed 's/arm64/aarch64/')
	rm -f memex.linux.phar
	chmod +x memex
	@echo "\n✅ MEMEX binary created successfully!"
	@echo "Test it with: ./memex --version"

local.install: ## Install memex binary locally
	$(eval CURRENT_MEMEX := $(shell which memex 2>/dev/null))
	$(eval INSTALL_DIR := $(if $(CURRENT_MEMEX),$(dir $(CURRENT_MEMEX)),$(HOME)/bin/))
	@mkdir -p $(INSTALL_DIR)
	@rm -f $(INSTALL_DIR)memex
	@cp memex $(INSTALL_DIR)memex
	@echo "\n✅ MEMEX installed successfully at: $(INSTALL_DIR)memex"
	@echo "Version: $$($(INSTALL_DIR)memex --version)"

test: vendor ## Run PHPUnit unit tests
	symfony php vendor/bin/phpunit

test-mcp: ## Run MCP Direct JSON-RPC integration tests
	@bash bin/test-mcp.sh

test-embed: vendor ## Test embed command with --force flag
	@echo "Testing embed --force functionality..."
	@set -e; \
	TEST_KB=$$(mktemp -d); \
	mkdir -p $$TEST_KB/guides $$TEST_KB/contexts; \
	echo "---\nuuid: \"550e8400-e29b-41d4-a716-446655440000\"\ntitle: \"Test Guide\"\ntype: guide\ntags: [\"test\"]\n---\n\n# Test Guide\n\nTest content" > $$TEST_KB/guides/test.md; \
	vendor/bin/castor embed --kb=$$TEST_KB 2>&1 | grep -q "Indexed" && echo "✓ Initial embed works" || { echo "✗ Initial embed failed"; exit 1; }; \
	test -f $$TEST_KB/.vectors/embeddings.db && echo "✓ Database created" || { echo "✗ Database not created"; exit 1; }; \
	vendor/bin/castor embed --kb=$$TEST_KB --force 2>&1 | grep -q "Deleting existing vector database" && echo "✓ Force flag deletes database" || { echo "✗ Force flag didn't delete database"; exit 1; }; \
	test -f $$TEST_KB/.vectors/embeddings.db && echo "✓ Database recreated" || { echo "✗ Database not recreated"; exit 1; }; \
	vendor/bin/castor embed --kb=$$TEST_KB --force 2>&1 | grep -q "Successfully indexed" && echo "✓ Force reindex works" || { echo "✗ Force reindex failed"; exit 1; }; \
	rm -rf $$TEST_KB; \
	echo "\n✅ All embed --force tests passed!"

coverage: vendor ## Generate HTML coverage report in /tmp/coverage
	XDEBUG_MODE=coverage symfony php vendor/bin/phpunit --coverage-html=/tmp/coverage
	@echo "\n✅ Coverage report generated at: /tmp/coverage/index.html"
	@echo "Open with: open /tmp/coverage/index.html"

vendor: composer.lock
	symfony composer install

composer.lock: composer.json
