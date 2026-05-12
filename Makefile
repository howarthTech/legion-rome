# Convenience commands. `make` with no target = list them.
.DEFAULT_GOAL := help
SHELL := /usr/bin/env bash

# Use Hugo from $PATH; fall back to ~/.local/bin/hugo for our setup
HUGO := $(shell command -v hugo 2>/dev/null || echo $$HOME/.local/bin/hugo)

.PHONY: help
help: ## Show this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

.PHONY: dev
dev: ## Start the Hugo dev server (http://localhost:1313) with live reload
	@$(HUGO) server \
		--bind 0.0.0.0 \
		--port 1313 \
		--baseURL "http://localhost:1313" \
		--appendPort=false \
		--disableFastRender \
		--buildFuture

.PHONY: build
build: ## Production build (output in ./public)
	@$(HUGO) --environment production --minify --gc --cleanDestinationDir
	@mkdir -p public/_form
	@cp forms/*.php public/_form/
	@chmod 0644 public/_form/*.php
	@echo "✓ Built. Output: ./public ($$(find public -type f | wc -l) files)"

.PHONY: build-staging
build-staging: ## Build with TODO banners visible (the dev default)
	@$(HUGO) --minify --buildFuture --cleanDestinationDir
	@mkdir -p public/_form && cp forms/*.php public/_form/
	@echo "✓ Built (staging). Output: ./public"

.PHONY: clean
clean: ## Remove build output
	@rm -rf public resources
	@echo "✓ Cleaned ./public and ./resources"

.PHONY: dev-forms
dev-forms: build ## Build + start docker-compose dev env to test PHP forms
	@if [ ! -f dev/secrets/romelegion.org.env ]; then \
		echo "❌ dev/secrets/romelegion.org.env not found."; \
		echo "   cp dev/secrets/romelegion.org.env.example dev/secrets/romelegion.org.env"; \
		echo "   then fill in a dev Resend key."; \
		exit 1; \
	fi
	@docker compose -f docker-compose.dev.yml up --remove-orphans

.PHONY: dev-forms-stop
dev-forms-stop: ## Stop the docker-compose dev env
	@docker compose -f docker-compose.dev.yml down

.PHONY: check-links
check-links: build ## Find broken internal links in the built site
	@which muffet >/dev/null || (echo "Install muffet: go install github.com/raviqqe/muffet/v2@latest"; exit 1)
	@echo "Run the dev server in another terminal first (make dev), then:"
	@muffet http://localhost:1313/ --max-connections=2 --buffer-size=8192

.PHONY: lint-caddy
lint-caddy: ## Validate the Caddy site block syntax
	@docker run --rm -v $$PWD/caddy/sites:/conf caddy:2.11-alpine \
		caddy validate --adapter caddyfile --config /conf/romelegion.org.caddy

.PHONY: deploy-files
deploy-files: ## Show the files that will be deployed to the VPS
	@find public -type f | sort

.PHONY: caddy-block
caddy-block: ## Print the Caddy site block (for copying to /etc/caddy/sites/)
	@cat caddy/sites/romelegion.org.caddy
