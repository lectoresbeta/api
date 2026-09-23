.DEFAULT_GOAL := help
SHELL := /bin/bash

## —— Puesta en marcha ————————————————————————————————————————————————

install: ## Instala dependencias y levanta los servicios
	composer install
	docker compose up -d
	@echo "Esperando a PostgreSQL…"
	@until docker compose exec -T postgres pg_isready -U lectoresbeta >/dev/null 2>&1; do sleep 1; done
	$(MAKE) jwt-keys
	$(MAKE) migrate
	@echo "Listo. Mailpit en http://localhost:8025 — RabbitMQ en http://localhost:15672"

up: ## Levanta PostgreSQL, RabbitMQ y Mailpit
	docker compose up -d

down: ## Para los servicios
	docker compose down

jwt-keys: ## Genera el par de claves para firmar los JWT
	@mkdir -p config/jwt
	@if [ ! -f config/jwt/private.pem ]; then \
		php bin/console lexik:jwt:generate-keypair --skip-if-exists; \
		echo "Claves generadas en config/jwt/ (ignoradas por git)"; \
	fi

## —— Base de datos ———————————————————————————————————————————————————

migrate: ## Aplica las migraciones pendientes
	php bin/console doctrine:migrations:migrate --no-interaction

migration: ## Genera una migración a partir del mapeo
	php bin/console doctrine:migrations:diff

db-test: ## Prepara la base de datos de test
	php bin/console --env=test doctrine:database:create --if-not-exists
	php bin/console --env=test doctrine:migrations:migrate --no-interaction

## —— Calidad —————————————————————————————————————————————————————————

check: cs stan deptrac test ## Ejecuta todo lo que ejecuta CI

cs: ## Comprueba el estilo sin modificar nada
	vendor/bin/php-cs-fixer fix --dry-run --diff

fix: ## Corrige el estilo
	vendor/bin/php-cs-fixer fix

stan: ## Análisis estático (nivel 7)
	vendor/bin/phpstan analyse

deptrac: ## Comprueba capas y aislamiento entre contextos
	vendor/bin/deptrac analyse --config-file=deptrac.layers.yaml --fail-on-uncovered --report-uncovered
	vendor/bin/deptrac analyse --config-file=deptrac.contexts.yaml --fail-on-uncovered --report-uncovered

test: ## Toda la batería
	vendor/bin/phpunit

test-unit: ## Solo tests de dominio: sin Symfony, sin base de datos
	vendor/bin/phpunit --testsuite=Unit

docs: ## Valida la documentación
	python3 docs/_tools/check-docs.py

## —— Mensajería ——————————————————————————————————————————————————————

consume: ## Consume los eventos de integración
	php bin/console messenger:consume integration -vv

## ————————————————————————————————————————————————————————————————————

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

.PHONY: install up down jwt-keys migrate migration db-test check cs fix stan deptrac test test-unit docs consume help
