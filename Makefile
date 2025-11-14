.PHONY: help build up down restart shell logs clean test lint install composer-update tuti

.DEFAULT_GOAL := help

CYAN := \033[0;36m
RESET := \033[0m

help: ## Show this help message
	@echo "$(CYAN)Tuti CLI - Docker Commands$(RESET)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(CYAN)%-20s$(RESET) %s\n", $$1, $$2}'

build: ## Build Docker image
	docker compose build --no-cache

up: ## Start Docker containers
	docker compose up -d

down: ## Stop Docker containers
	docker compose down

restart: down up ## Restart Docker containers

shell: ## Access container shell
	docker compose exec app bash

logs: ## Show container logs
	docker compose logs -f app

clean: ## Clean up Docker resources
	docker compose down -v
	docker system prune -f

install: ## Install composer dependencies
	docker compose exec app composer install

composer-update: ## Update composer dependencies
	docker compose exec app composer update

test: ## Run all tests
	docker compose exec app composer test

lint: ## Run linter
	docker compose exec app composer lint

refactor: ## Run refactor check
	docker compose exec app composer test:refactor

phpstan: ## Run PHPStan
	docker compose exec app composer test:types

init: build up install ## Initialize project
	@echo "$(CYAN)✓ Tuti CLI is ready!$(RESET)"

# Tuti CLI passthrough - captures everything after "tuti"
tuti: ## Run tuti CLI (usage: make tuti list)
	@docker compose exec app php tuti $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))

# Catch-all target to prevent "No rule to make target" errors
%:
	@true
