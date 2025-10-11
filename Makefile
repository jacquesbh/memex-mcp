.PHONY: help install clean build test test-mcp coverage

help: ## Display this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

install: vendor ## Install Composer dependencies

clean: ## Clean generated files (binary and vendor)
	rm -f memex memex.linux.phar
	rm -rf vendor/

build: install ## Build the MEMEX binary (installs dependencies first)
	vendor/jolicode/castor/bin/castor repack --app-name=memex --logo-file=.castor.logo.php
	mv memex.linux.phar memex
	chmod +x memex
	@echo "\n✅ MEMEX binary created successfully!"
	@echo "Test it with: ./memex --version"

test: vendor ## Run PHPUnit unit tests
	vendor/bin/phpunit

test-mcp: ## Run MCP Inspector integration tests
	@bash bin/test-mcp.sh

coverage: vendor ## Generate HTML coverage report in /tmp/coverage
	XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html=/tmp/coverage
	@echo "\n✅ Coverage report generated at: /tmp/coverage/index.html"
	@echo "Open with: open /tmp/coverage/index.html"

vendor: composer.lock
	symfony composer install

composer.lock: composer.json

