.PHONY: help install clean build

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

install: vendor ## Installe les dépendances Composer

clean: ## Nettoie les fichiers générés (binaire et vendor)
	rm -f memex memex.linux.phar
	rm -rf vendor/

build: install ## Compile le binaire MEMEX (installe les dépendances d'abord)
	vendor/jolicode/castor/bin/castor repack --app-name=memex --logo-file=.castor.logo.php
	mv memex.linux.phar memex
	chmod +x memex
	@echo "\n✅ Binaire memex créé avec succès !"
	@echo "Testez-le avec: ./memex list"

vendor: composer.lock
	symfony composer install

composer.lock: composer.json

