HOST_UID ?= $(shell id -u)
HOST_GID ?= $(shell id -g)
DOCKER_COMPOSE = HOST_UID=$(HOST_UID) HOST_GID=$(HOST_GID) docker-compose
PHP = $(DOCKER_COMPOSE) exec -T -u app php
TEST_PHP = $(DOCKER_COMPOSE) exec -T -u app -e APP_ENV=test -e XDEBUG_MODE=off php
PHP_RUN = $(DOCKER_COMPOSE) run --rm --no-deps -u app php

.PHONY: help build up down restart shell composer install update console cs cs-fix phpstan test test-coverage logs cache-clear permissions migrate db-reset test-db bootstrap

help:
	@echo "Available targets:"
	@echo "  make build          - Build Docker images"
	@echo "  make up             - Start containers"
	@echo "  make down           - Stop containers"
	@echo "  make restart        - Restart containers"
	@echo "  make shell          - Open shell in php container"
	@echo "  make install        - composer install"
	@echo "  make update         - composer update"
	@echo "  make composer ARGS= - Run composer with ARGS"
	@echo "  make console ARGS=  - Run bin/console with ARGS"
	@echo "  make migrate        - Run Doctrine migrations"
	@echo "  make test-db        - Create and migrate the test database"
	@echo "  make db-reset       - Drop and recreate both databases"
	@echo "  make cs             - PHPCS (PSR-12) check"
	@echo "  make cs-fix         - PHPCBF auto-fix"
	@echo "  make phpstan        - Run PHPStan"
	@echo "  make test           - Run PHPUnit"
	@echo "  make test-coverage  - Run PHPUnit with coverage"
	@echo "  make logs           - Follow container logs"
	@echo "  make cache-clear    - Clear Symfony cache"
	@echo "  make permissions    - Fix var/ and vendor/ ownership"
	@echo "  make bootstrap      - Create Symfony project and quality tools"

build:
	$(DOCKER_COMPOSE) build

up:
	-$(DOCKER_COMPOSE) down --remove-orphans
	$(DOCKER_COMPOSE) up -d
	@echo "App: http://localhost:$${HTTP_PORT:-8088}"

down:
	$(DOCKER_COMPOSE) down

restart:
	$(DOCKER_COMPOSE) restart

shell:
	$(DOCKER_COMPOSE) exec -u app php bash

install:
	$(PHP) composer install

update:
	$(PHP) composer update

composer:
	$(PHP) composer $(ARGS)

console:
	$(PHP) php bin/console $(ARGS)

migrate:
	$(PHP) php bin/console doctrine:database:create --if-not-exists
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction

test-db:
	$(TEST_PHP) php bin/console doctrine:database:create --if-not-exists
	$(TEST_PHP) php bin/console doctrine:migrations:migrate --no-interaction

db-reset:
	$(PHP) php bin/console doctrine:database:drop --force --if-exists
	$(TEST_PHP) php bin/console doctrine:database:drop --force --if-exists
	$(MAKE) migrate test-db

cs:
	$(PHP) vendor/bin/phpcs --standard=phpcs.xml.dist

cs-fix:
	$(PHP) vendor/bin/phpcbf --standard=phpcs.xml.dist || true

phpstan:
	$(PHP) vendor/bin/phpstan analyse -c phpstan.neon.dist

test: test-db
	$(TEST_PHP) php bin/phpunit

test-coverage: test-db
	$(DOCKER_COMPOSE) exec -T -u app -e APP_ENV=test -e XDEBUG_MODE=coverage php php bin/phpunit --coverage-html var/coverage

logs:
	$(DOCKER_COMPOSE) logs -f --tail=100

cache-clear:
	$(PHP) php bin/console cache:clear

permissions:
	$(DOCKER_COMPOSE) exec -T -u root php sh -c "mkdir -p /app/var/cache /app/var/log && chown -R app:app /app/var /app/vendor"

bootstrap:
	$(DOCKER_COMPOSE) build
	$(DOCKER_COMPOSE) down --remove-orphans
	$(DOCKER_COMPOSE) up -d
	$(DOCKER_COMPOSE) exec -T -u root php sh -c "mkdir -p /app/var/cache /app/var/log /app/vendor && chown -R app:app /app"
	$(DOCKER_COMPOSE) run --rm --no-deps -u app php sh -c '\
		if [ ! -f composer.json ]; then \
			composer create-project symfony/skeleton:"7.4.*" /tmp/symfony --no-interaction && \
			cp -a /tmp/symfony/. /app/ && \
			rm -rf /tmp/symfony; \
		fi'
	$(PHP) composer require webapp --no-interaction
	$(PHP) composer require --dev \
		squizlabs/php_codesniffer \
		phpstan/phpstan \
		phpstan/phpstan-symfony \
		phpstan/phpstan-doctrine \
		--no-interaction
	@echo "Project ready. Run: make migrate && make test"
