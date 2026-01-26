.PHONY: help build up down restart shell logs clean test lint install build-phar build-binaries test-phar release version-bump check-build

.DEFAULT_GOAL := help

# Colors
CYAN := \033[0;36m
GREEN := \033[0;32m
YELLOW := \033[0;33m
RED := \033[0;31m
RESET := \033[0m

# Docker command prefix
DOCKER_EXEC := docker compose exec -T app
DOCKER_EXEC_IT := docker compose exec app

# Version (read from config/app.php)
VERSION ?= $(shell grep "'version'" config/app.php | sed "s/.*'\([^']*\)'.*/\1/")

help: ## Show this help message
	@echo "$(CYAN)═══════════════════════════════════════════════════════════$(RESET)"
	@echo "$(CYAN)              Tuti CLI - Development Commands              $(RESET)"
	@echo "$(CYAN)═══════════════════════════════════════════════════════════$(RESET)"
	@echo ""
	@echo "$(YELLOW)Development:$(RESET)"
	@grep -E '^(install|test|lint):.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(CYAN)%-18s$(RESET) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(YELLOW)Docker:$(RESET)"
	@grep -E '^(build|up|down|restart|shell|logs|clean):.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(CYAN)%-18s$(RESET) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(YELLOW)Build & Release:$(RESET)"
	@grep -E '^(build-phar|build-binaries|test-phar|check-build|release|version-bump):.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(CYAN)%-18s$(RESET) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(YELLOW)Current version: $(GREEN)$(VERSION)$(RESET)"

# =============================================================================
# Docker
# =============================================================================

build: ## Build Docker image
	@docker compose build --no-cache

up: ## Start Docker containers
	@docker compose up -d

down: ## Stop Docker containers
	@docker compose down

restart: down up ## Restart Docker containers

shell: ## Access container shell
	@docker compose exec app bash

logs: ## Show container logs
	@docker compose logs -f app

clean: ## Clean up Docker resources
	@docker compose down -v
	@docker system prune -f

# =============================================================================
# Setup & Development (runs inside Docker)
# =============================================================================

install: up ## Install composer dependencies
	@$(DOCKER_EXEC) composer install

check-build: ## Check if app:build command is available
	@$(DOCKER_EXEC) php tuti list 2>/dev/null | grep -q "app:build" && \
		echo "$(GREEN)✓ app:build is available$(RESET)" || \
		echo "$(RED)✗ app:build not found. Check config/commands.php$(RESET)"

test: ## Run all tests
	@$(DOCKER_EXEC) php tuti test:unit

lint: ## Run linter (Pint)
	@$(DOCKER_EXEC) php tuti lint

# =============================================================================
# Build & Release (runs inside Docker)
# =============================================================================

build-phar: ## Build PHAR using Laravel Zero app:build
	@echo "$(CYAN)Building PHAR v$(VERSION)...$(RESET)"
	@$(DOCKER_EXEC) php -d phar.readonly=0 tuti app:build tuti --build-version=$(VERSION)
	@echo "$(GREEN)✓ PHAR built: builds/tuti$(RESET)"

build-binaries: build-phar ## Build native binaries for Linux and macOS
	@echo "$(CYAN)Building native binaries...$(RESET)"
	@mkdir -p builds/bin
	@echo "$(YELLOW)Building Linux x64...$(RESET)"
	@$(DOCKER_EXEC) php vendor/bin/phpacker build builds/tuti \
		--output=builds/bin/tuti-linux-amd64 \
		--os=linux \
		--arch=x64 2>/dev/null || \
		$(DOCKER_EXEC) php vendor/bin/phpacker compile builds/tuti builds/bin/tuti-linux-amd64 2>/dev/null || \
		echo "$(YELLOW)⚠ Linux build requires running on appropriate platform$(RESET)"
	@echo "$(YELLOW)Building Linux ARM64...$(RESET)"
	@$(DOCKER_EXEC) php vendor/bin/phpacker build builds/tuti \
		--output=builds/bin/tuti-linux-arm64 \
		--os=linux \
		--arch=arm64 2>/dev/null || \
		echo "$(YELLOW)⚠ Linux ARM64 build skipped$(RESET)"
	@echo "$(GREEN)✓ Binaries built in builds/bin/$(RESET)"
	@ls -la builds/bin/ 2>/dev/null || true

test-phar: ## Test PHAR file
	@echo "$(CYAN)Testing PHAR...$(RESET)"
	@if [ -f "builds/tuti" ]; then \
		$(DOCKER_EXEC) php builds/tuti --version && \
		$(DOCKER_EXEC) php builds/tuti list | head -20 && \
		echo "$(GREEN)✓ PHAR works!$(RESET)"; \
	else \
		echo "$(RED)✗ PHAR not found at builds/tuti$(RESET)"; \
		echo "$(YELLOW)Checking for tuti.phar...$(RESET)"; \
		if [ -f "builds/tuti.phar" ]; then \
			$(DOCKER_EXEC) php builds/tuti.phar --version && \
			echo "$(GREEN)✓ tuti.phar works!$(RESET)"; \
		else \
			echo "$(RED)✗ No PHAR found. Run 'make build-phar' first$(RESET)"; \
			exit 1; \
		fi \
	fi

test-all: test build-phar test-phar ## Run all tests including PHAR

release: ## Show release process
	@echo "$(CYAN)═══════════════════════════════════════════════════════════$(RESET)"
	@echo "$(CYAN)                    Release Process                        $(RESET)"
	@echo "$(CYAN)═══════════════════════════════════════════════════════════$(RESET)"
	@echo ""
	@echo "$(YELLOW)Current version: $(GREEN)$(VERSION)$(RESET)"
	@echo ""
	@echo "$(YELLOW)Quick Release:$(RESET)"
	@echo "  make release-auto V=x.y.z"
	@echo ""
	@echo "$(YELLOW)Manual Steps:$(RESET)"
	@echo "  1. make version-bump V=x.y.z"
	@echo "  2. make build-phar"
	@echo "  3. make test-phar"
	@echo "  4. git add . && git commit -m 'Release vx.y.z'"
	@echo "  5. git tag -a vx.y.z -m 'Release vx.y.z'"
	@echo "  6. git push origin main --tags"

version-bump: ## Bump version (usage: make version-bump V=1.0.0)
	@if [ -z "$(V)" ]; then \
		echo "$(RED)Error: Version not specified$(RESET)"; \
		echo "Usage: make version-bump V=1.0.0"; \
		exit 1; \
	fi
	@echo "$(CYAN)Bumping version to $(V)...$(RESET)"
	@sed -i "s/'version' => '[^']*'/'version' => '$(V)'/" config/app.php
	@echo "$(GREEN)✓ Version updated to $(V)$(RESET)"

release-auto: version-bump build-phar test-phar ## Automated release (usage: make release-auto V=1.0.0)
	@echo "$(CYAN)Creating release v$(V)...$(RESET)"
	@git add .
	@git commit -m "Release v$(V)"
	@git tag -a v$(V) -m "Release v$(V)"
	@echo "$(GREEN)✓ Release v$(V) created locally$(RESET)"
	@echo ""
	@echo "$(YELLOW)To publish:$(RESET)"
	@echo "  git push origin main --tags"
