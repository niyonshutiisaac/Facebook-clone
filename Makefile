# =============================================================================
# facebook-clone — developer shortcut commands
# =============================================================================
.DEFAULT_GOAL := help
SHELL := /bin/bash

# ---------------------------------------------------------------- stack ------
up:            ## Boot the whole stack (first run: also builds images)
	docker compose up -d --build

down:          ## Stop the stack
	docker compose down

destroy:       ## Stop the stack and delete every volume (DESTRUCTIVE)
	docker compose down -v --remove-orphans

logs:          ## Tail logs for every service
	docker compose logs -f

ps:            ## Show service status
	docker compose ps

# --------------------------------------------------------------- backend -----
be:            ## Open a shell inside the PHP-FPM container
	docker compose exec php-fpm bash

artisan:       ## Run an artisan command: make artisan c="migrate --seed"
	docker compose exec php-fpm php artisan $(c)

migrate:       ## Run database migrations
	docker compose exec php-fpm php artisan migrate

fresh:         ## Drop every table, migrate and seed demo data
	docker compose exec php-fpm php artisan migrate:fresh --seed

seed:          ## Seed demo data
	docker compose exec php-fpm php artisan db:seed

test:          ## Run the backend test suite (Pest / PHPUnit)
	docker compose exec php-fpm php artisan test

lint:          ## Fix backend code style with Laravel Pint
	docker compose exec php-fpm ./vendor/bin/pint

analyse:       ## Run static analysis (PHPStan)
	docker compose exec php-fpm ./vendor/bin/phpstan analyse

horizon:       ## Tail Horizon (queue) logs
	docker compose logs -f horizon

# -------------------------------------------------------------- frontend -----
fe:            ## Open a shell inside the Node container
	docker compose exec node sh

fe-install:    ## Install frontend dependencies
	docker compose exec node npm install

fe-dev:        ## Start the Vite dev server
	docker compose exec node npm run dev

fe-build:      ## Production build of the React app
	docker compose exec node npm run build

fe-test:       ## Run frontend unit tests (Vitest)
	docker compose exec node npm run test

fe-typecheck:  ## TypeScript strict-mode typecheck
	docker compose exec node npm run typecheck

# ----------------------------------------------------------------- misc ------
key:           ## Generate an application key (first run)
	docker compose exec php-fpm php artisan key:generate

scout:         ## Import all searchable models into Meilisearch
	docker compose exec php-fpm php artisan scout:import-all

help:          ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

.PHONY: up down destroy logs ps be artisan migrate fresh seed test lint analyse \
        horizon fe fe-install fe-dev fe-build fe-test fe-typecheck key scout help
