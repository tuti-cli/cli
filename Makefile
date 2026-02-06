.PHONY: help build up down restart shell logs clean test lint install build-phar build-binary build-binary-linux build-binary-mac test-phar test-binary install-local uninstall-local release version-bump check-build

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

# Platform detection
UNAME_S := $(shell uname -s)
UNAME_M := $(shell uname -m)
ifeq ($(UNAME_S),Linux)
    PLATFORM_OS := linux
else ifeq ($(UNAME_S),Darwin)
    PLATFORM_OS := darwin
else
    PLATFORM_OS := unknown
endif
ifeq ($(UNAME_M),x86_64)
    PLATFORM_ARCH := amd64
else ifeq ($(UNAME_M),aarch64)
    PLATFORM_ARCH := arm64
else ifeq ($(UNAME_M),arm64)
    PLATFORM_ARCH := arm64
else
    PLATFORM_ARCH := unknown
endif
PLATFORM := $(PLATFORM_OS)-$(PLATFORM_ARCH)

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
	@grep -E '^(build-phar|build-binary|test-phar|test-binary|install-local|uninstall-local|check-build|release|version-bump):.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(CYAN)%-18s$(RESET) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(YELLOW)Current version: $(GREEN)$(VERSION)$(RESET)"
	@echo "$(YELLOW)Platform: $(GREEN)$(PLATFORM)$(RESET)"

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

dotenv-test: ## Test if .env is loaded correctly
	@echo "$(CYAN)Testing .env loading...$(RESET)"
	@$(DOCKER_EXEC) php -r "require 'vendor/autoload.php'; \$$app = require 'bootstrap/app.php'; \$$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); echo 'APP_NAME: ' . env('APP_NAME', 'NOT LOADED') . PHP_EOL; echo 'APP_ENV: ' . env('APP_ENV', 'NOT LOADED') . PHP_EOL;"
	@echo "$(GREEN)✓ Dotenv loaded successfully$(RESET)"

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
	@$(DOCKER_EXEC) php -d phar.readonly=0 tuti app:build tuti.phar --build-version=$(VERSION)
	@echo "$(GREEN)✓ PHAR built: builds/tuti.phar$(RESET)"

build-binary: build-phar ## Build self-contained binaries using phpacker
	@echo "$(CYAN)Building self-contained binaries with phpacker...$(RESET)"
	@echo "$(YELLOW)This embeds PHP 8.4 runtime - no system PHP needed!$(RESET)"
	@$(DOCKER_EXEC) rm -rf builds/build 2>/dev/null || true
	@$(DOCKER_EXEC) mkdir -p builds/build /tmp/phpacker-cache
	@$(DOCKER_EXEC) chmod -R 777 builds/build /tmp/phpacker-cache
	@$(DOCKER_EXEC) sh -c 'HOME=/tmp PHPACKER_CACHE=/tmp/phpacker-cache ./vendor/bin/phpacker build --src=./builds/tuti.phar --php=8.4 all'
	@echo "$(GREEN)✓ Binaries built in builds/build/$(RESET)"
	@$(DOCKER_EXEC) ls -laR builds/build/

build-binary-linux: build-phar ## Build Linux binaries only using phpacker
	@echo "$(CYAN)Building Linux binaries with phpacker...$(RESET)"
	@$(DOCKER_EXEC) rm -rf builds/build 2>/dev/null || true
	@$(DOCKER_EXEC) mkdir -p builds/build /tmp/phpacker-cache
	@$(DOCKER_EXEC) chmod -R 777 builds/build /tmp/phpacker-cache
	@$(DOCKER_EXEC) sh -c 'HOME=/tmp PHPACKER_CACHE=/tmp/phpacker-cache ./vendor/bin/phpacker build --src=./builds/tuti.phar --php=8.4 linux'
	@echo "$(GREEN)✓ Linux binaries built$(RESET)"
	@$(DOCKER_EXEC) ls -laR builds/build/linux/

build-binary-mac: build-phar ## Build macOS binaries only using phpacker
	@echo "$(CYAN)Building macOS binaries with phpacker...$(RESET)"
	@$(DOCKER_EXEC) rm -rf builds/build 2>/dev/null || true
	@$(DOCKER_EXEC) mkdir -p builds/build /tmp/phpacker-cache
	@$(DOCKER_EXEC) chmod -R 777 builds/build /tmp/phpacker-cache
	@$(DOCKER_EXEC) sh -c 'HOME=/tmp PHPACKER_CACHE=/tmp/phpacker-cache ./vendor/bin/phpacker build --src=./builds/tuti.phar --php=8.4 mac'
	@echo "$(GREEN)✓ macOS binaries built$(RESET)"
	@$(DOCKER_EXEC) ls -laR builds/build/mac/

test-phar: ## Test PHAR file (requires PHP)
	@echo "$(CYAN)Testing PHAR...$(RESET)"
	@if [ -f "builds/tuti.phar" ]; then \
		$(DOCKER_EXEC) php builds/tuti.phar --version && \
		$(DOCKER_EXEC) php builds/tuti.phar list | head -20 && \
		echo "$(GREEN)✓ PHAR works!$(RESET)"; \
	else \
		echo "$(RED)✗ PHAR not found at builds/tuti.phar$(RESET)"; \
		exit 1; \
	fi

test-binary: ## Test self-contained binary (no PHP required!)
	@echo "$(CYAN)Testing binary for $(PLATFORM)...$(RESET)"
	@if [ "$(PLATFORM_OS)" = "linux" ]; then \
		ARCH="$(PLATFORM_ARCH)"; \
		if [ "$$ARCH" = "amd64" ]; then ARCH="x64"; fi; \
		if [ -f "builds/build/linux/linux-$$ARCH" ]; then \
			./builds/build/linux/linux-$$ARCH --version && \
			echo "$(GREEN)✓ Binary works without PHP!$(RESET)"; \
		else \
			echo "$(RED)✗ Binary not found at builds/build/linux/linux-$$ARCH$(RESET)"; \
			echo "$(YELLOW)Run 'make build-binary' first$(RESET)"; \
			exit 1; \
		fi \
	elif [ "$(PLATFORM_OS)" = "darwin" ]; then \
		ARCH="$(PLATFORM_ARCH)"; \
		if [ "$$ARCH" = "amd64" ]; then ARCH="x64"; fi; \
		if [ -f "builds/build/mac/mac-$$ARCH" ]; then \
			./builds/build/mac/mac-$$ARCH --version && \
			echo "$(GREEN)✓ Binary works without PHP!$(RESET)"; \
		else \
			echo "$(RED)✗ Binary not found at builds/build/mac/mac-$$ARCH$(RESET)"; \
			echo "$(YELLOW)Run 'make build-binary' first$(RESET)"; \
			exit 1; \
		fi \
	else \
		echo "$(RED)Unsupported platform for testing$(RESET)"; \
		exit 1; \
	fi

install-local: build-binary ## Build and install binary locally to ~/.tuti/bin
	@echo "$(CYAN)Installing binary to ~/.tuti/bin...$(RESET)"
	@mkdir -p $(HOME)/.tuti/bin $(HOME)/.tuti/logs $(HOME)/.tuti/cache
	@if [ "$(PLATFORM_OS)" = "linux" ]; then \
		ARCH="$(PLATFORM_ARCH)"; \
		if [ "$$ARCH" = "amd64" ]; then ARCH="x64"; fi; \
		cp builds/build/linux/linux-$$ARCH $(HOME)/.tuti/bin/tuti; \
	elif [ "$(PLATFORM_OS)" = "darwin" ]; then \
		ARCH="$(PLATFORM_ARCH)"; \
		if [ "$$ARCH" = "amd64" ]; then ARCH="x64"; fi; \
		cp builds/build/mac/mac-$$ARCH $(HOME)/.tuti/bin/tuti; \
	fi
	@chmod +x $(HOME)/.tuti/bin/tuti
	@echo "$(GREEN)✓ Installed to ~/.tuti/bin/tuti$(RESET)"
	@echo ""
	@echo "$(YELLOW)Add to PATH (if not already):$(RESET)"
	@echo "  echo 'export PATH=\"\$$PATH:\$$HOME/.tuti/bin\"' >> ~/.bashrc"
	@echo "  source ~/.bashrc"
	@echo ""
	@echo "$(YELLOW)Test:$(RESET)"
	@echo "  ~/.tuti/bin/tuti --version"

uninstall-local: ## Remove locally installed binary and optionally ~/.tuti
	@echo "$(CYAN)Uninstalling tuti from ~/.tuti/bin...$(RESET)"
	@rm -f $(HOME)/.tuti/bin/tuti
	@echo "$(GREEN)✓ Binary removed$(RESET)"
	@echo ""
	@echo "$(YELLOW)To also remove data directory:$(RESET)"
	@echo "  rm -rf ~/.tuti"

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
