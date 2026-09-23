.DEFAULT_GOAL := help
SHELL := /bin/bash

# Todo se ejecuta dentro del contenedor. Para ejecutarlo en la máquina —útil
# si ya tienes PHP 8.4 instalado— basta con vaciar EXEC:
#
#     make stan EXEC=
#
DC       = docker compose
SERVICE  = app
EXEC     = $(DC) exec -T --user www-data $(SERVICE)
PHP      = $(EXEC) php
COMPOSER = $(EXEC) composer
CONSOLE  = $(PHP) bin/console

# El contenedor escribe en el mismo directorio que tú: vendor/, var/ y lo que
# corrija php-cs-fixer. Pasarle tu UID hace que `www-data` sea tú dentro de la
# imagen, y que no acabes con un checkout que no puedes borrar sin sudo.
export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)

## —— Ciclo de vida ———————————————————————————————————————————————————

build: ## Construye las imágenes
	$(DC) build

up: ## Levanta todo y deja la base de datos migrada
	$(DC) up -d
	$(MAKE) jwt-keys
	$(MAKE) migrate
	@echo
	@echo "  API        http://localhost:8000"
	@echo "  Mailpit    http://localhost:8025"
	@echo "  RabbitMQ   http://localhost:15672  (lectoresbeta / lectoresbeta)"
	@echo "  PostgreSQL localhost:5432          (lectoresbeta / lectoresbeta)"

down: ## Para los servicios y conserva los datos
	$(DC) down

destroy: ## Para los servicios y BORRA los volúmenes (base de datos incluida)
	$(DC) down -v

restart: ## Reinicia los servicios
	$(DC) restart

ps: ## Estado de los servicios
	$(DC) ps

logs: ## Sigue los registros de todos los servicios
	$(DC) logs -f

logs-worker: ## Sigue solo el consumidor de eventos
	$(DC) logs -f worker

sh: ## Abre una shell en el contenedor de la aplicación
	$(DC) exec --user www-data $(SERVICE) bash

sh-root: ## Abre una shell como root, para instalar algo o mirar permisos
	$(DC) exec $(SERVICE) bash

console: ## Ejecuta un comando de Symfony: make console CMD="debug:router"
	$(CONSOLE) $(CMD)

install: build up ## Construye, levanta y deja el entorno listo

## —— Base de datos ———————————————————————————————————————————————————

migrate: ## Aplica las migraciones pendientes
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

migration: ## Genera una migración a partir del mapeo
	$(CONSOLE) doctrine:migrations:diff

migration-status: ## Qué migraciones hay y cuáles se han aplicado
	$(CONSOLE) doctrine:migrations:status

schema-validate: ## Comprueba que el mapeo y el esquema coinciden
	$(CONSOLE) doctrine:schema:validate

db-reset: ## Rehace la base de datos desde cero. DESTRUYE los datos
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(MAKE) migrate

db-test: ## Prepara la base de datos de test
	$(CONSOLE) --env=test doctrine:database:create --if-not-exists
	$(CONSOLE) --env=test doctrine:migrations:migrate --no-interaction --allow-no-migration

psql: ## Abre psql contra la base de datos de desarrollo
	$(DC) exec postgres psql -U lectoresbeta -d lectoresbeta

## —— Calidad —————————————————————————————————————————————————————————

check: cs stan deptrac test docs ## Lo mismo que ejecuta CI

cs: ## Comprueba el estilo sin modificar nada
	$(EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff

fix: ## Corrige el estilo
	$(EXEC) vendor/bin/php-cs-fixer fix

stan: ## Análisis estático (nivel 7)
	$(EXEC) vendor/bin/phpstan analyse

deptrac: ## Comprueba capas y aislamiento entre contextos
	$(EXEC) vendor/bin/deptrac analyse --config-file=deptrac.layers.yaml --fail-on-uncovered --report-uncovered
	$(EXEC) vendor/bin/deptrac analyse --config-file=deptrac.contexts.yaml --fail-on-uncovered --report-uncovered

test: ## Toda la batería
	$(EXEC) vendor/bin/phpunit

test-unit: ## Solo tests de dominio: sin Symfony, sin base de datos, en milisegundos
	$(EXEC) vendor/bin/phpunit --testsuite=Unit

docs: ## Valida docs/
	python3 docs/_tools/check-docs.py

## —— Seguridad y dependencias ————————————————————————————————————————

jwt-keys: ## Genera el par de claves para firmar los JWT
	$(CONSOLE) lexik:jwt:generate-keypair --skip-if-exists

deps: ## Instala o actualiza las dependencias
	$(DC) run --rm deps

deps-reset: ## Borra vendor/ y lo reinstala desde cero
	$(DC) run --rm --user root deps rm -rf vendor
	$(DC) run --rm deps

audit: ## Avisos de seguridad de las dependencias
	$(COMPOSER) audit

## —— Mensajería ——————————————————————————————————————————————————————

consume: ## Consume los eventos de integración en primer plano
	$(CONSOLE) messenger:consume integration -vv

failed: ## Mensajes que han agotado los reintentos
	$(CONSOLE) messenger:failed:show

## ————————————————————————————————————————————————————————————————————

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

.PHONY: build up down destroy restart ps logs logs-worker sh console install \
        migrate migration migration-status schema-validate db-reset db-test psql \
        check cs fix stan deptrac test test-unit docs \
        jwt-keys deps deps-reset audit consume failed sh-root help
