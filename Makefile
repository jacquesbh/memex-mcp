PHP_VERSION ?= $(shell cat .php-version)
PHP_EXTENSIONS ?= mbstring,phar,posix,tokenizer,curl,filter,openssl,pdo,pdo_sqlite
PHP_RUNNER ?= symfony php
CASTOR_VERSION ?= v1.7.0
SPC_VERSION ?= 2.8.2
HOST_OS := $(shell uname -s)
HOST_ARCH := $(shell uname -m)
REPACK_OS := $(if $(filter Darwin,$(HOST_OS)),darwin,linux)
REPACK_ARCH := $(if $(filter arm64 aarch64,$(HOST_ARCH)),arm64,amd64)
COMPILE_OS := $(if $(filter Darwin,$(HOST_OS)),macos,linux)
COMPILE_ARCH := $(if $(filter arm64 aarch64,$(HOST_ARCH)),aarch64,x86_64)
REPACKED_PHAR := memex.$(REPACK_OS)-$(REPACK_ARCH).phar

.PHONY: help install clean check-arch build local.install test test-mcp test-embed coverage

help: ## Display this help
	@grep -E '^[a-zA-Z._-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

install: vendor ## Install Composer dependencies

clean: ## Clean generated files (binary and vendor)
	rm -f memex memex.*.phar
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
	BUILD_PHP_ARCH=$$($(PHP_RUNNER) -r 'echo php_uname("m");' 2>/dev/null); \
	if [ -z "$$BUILD_PHP_ARCH" ]; then \
		echo "✗ Unable to determine architecture for the PHP build runner"; \
		exit 1; \
	fi; \
	echo "Host arch: $$HOST_ARCH"; \
	echo "Process arch: $$PROC_ARCH"; \
	if [ "$$OS_NAME" = "Darwin" ]; then \
		echo "Rosetta translated: $$ROSETTA"; \
	fi; \
	echo "Build PHP arch: $$BUILD_PHP_ARCH"; \
	if [ "$$OS_NAME" = "Darwin" ] && [ "$$ROSETTA" = "1" ]; then \
		echo "✗ Terminal session is translated (Rosetta). Use a native arm64 shell."; \
		exit 1; \
	fi; \
	if [ "$$HOST_ARCH" != "$$BUILD_PHP_ARCH" ]; then \
		echo "✗ PHP build runner architecture ($$BUILD_PHP_ARCH) does not match host ($$HOST_ARCH)."; \
		echo "  Hint: remove ~/.symfony5/php and re-run, or use an arm64 Symfony PHP runtime."; \
		exit 1; \
	fi

build: check-arch install ## Build the MEMEX binary (installs dependencies first)
	$(eval VERSION := $(shell grep "const MEMEX_VERSION" castor.php | sed "s/.*'\(.*\)'.*/\1/"))
	$(PHP_RUNNER) vendor/jolicode/castor/bin/castor repack --app-name=memex --app-version=$(VERSION) --os=$(REPACK_OS) --arch=$(REPACK_ARCH) --castor-version=$(CASTOR_VERSION) --logo-file=.castor.logo.php --output-directory=.
	$(PHP_RUNNER) vendor/jolicode/castor/bin/castor compile $(REPACKED_PHAR) --spc-version=$(SPC_VERSION) --binary-path=memex --os=$(COMPILE_OS) --arch=$(COMPILE_ARCH) --php-version=$(PHP_VERSION) --php-extensions=$(PHP_EXTENSIONS)
	rm -f $(REPACKED_PHAR)
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
	$(PHP_RUNNER) vendor/bin/phpunit

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
	XDEBUG_MODE=coverage $(PHP_RUNNER) vendor/bin/phpunit --coverage-html=/tmp/coverage
	@echo "\n✅ Coverage report generated at: /tmp/coverage/index.html"
	@echo "Open with: open /tmp/coverage/index.html"

vendor: composer.lock
	symfony composer install

composer.lock: composer.json
