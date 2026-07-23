# Makefile - Convenience commands for Jurnal Kelas App
# Usage: make <command>

.PHONY: help build up down restart logs shell artisan migrate seed fresh dev build-assets setup

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

build: ## Build Docker images
	docker compose build

up: ## Start all containers
	docker compose up -d

up-dev: ## Start all containers including dev tools (phpMyAdmin, Bun/Vite)
	docker compose --profile dev up -d

down: ## Stop all containers
	docker compose down

restart: ## Restart all containers
	docker compose restart

logs: ## View container logs (follow mode)
	docker compose logs -f

shell: ## Open shell in app container
	docker compose exec app bash

artisan: ## Run artisan command (usage: make artisan cmd="migrate")
	docker compose exec app php artisan $(cmd)

migrate: ## Run database migrations
	docker compose exec app php artisan migrate

seed: ## Run database seeders
	docker compose exec app php artisan db:seed

fresh: ## Fresh migration with seeders
	docker compose exec app php artisan migrate:fresh --seed

dev: ## Start Vite dev server
	docker compose exec app bun run dev

build-assets: ## Build production assets
	docker compose exec app bun run build

setup: ## Run initial setup
	bash setup.sh
