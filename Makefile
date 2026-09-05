COMPOSE = docker compose
APP = $(COMPOSE) exec app
ARTISAN = $(APP) php artisan

.PHONY: help up down build restart logs shell key migrate seed fresh test queue-restart ps setup

help:
	@echo Available targets:
	@echo   make setup          - .env + build + key + migrate --seed
	@echo   make up             - docker compose up -d --build
	@echo   make down           - docker compose down
	@echo   make build          - docker compose build
	@echo   make restart        - restart all services
	@echo   make ps             - docker compose ps
	@echo   make logs           - follow logs
	@echo   make shell          - shell in app container
	@echo   make key            - php artisan key:generate
	@echo   make migrate        - php artisan migrate
	@echo   make seed           - php artisan db:seed
	@echo   make fresh          - migrate:fresh --seed
	@echo   make test           - php artisan test
	@echo   make queue-restart  - restart queue worker

setup:
	@if [ ! -f .env ]; then cp .env.example .env; fi
	$(COMPOSE) up -d --build
	$(ARTISAN) key:generate --force
	$(ARTISAN) migrate --seed --force

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build

restart:
	$(COMPOSE) restart

ps:
	$(COMPOSE) ps

logs:
	$(COMPOSE) logs -f

shell:
	$(COMPOSE) exec app bash

key:
	$(ARTISAN) key:generate --force

migrate:
	$(ARTISAN) migrate --force

seed:
	$(ARTISAN) db:seed --force

fresh:
	$(ARTISAN) migrate:fresh --seed --force

test:
	$(ARTISAN) test

queue-restart:
	$(COMPOSE) restart queue
