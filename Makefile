# =============================================================================
#  TaskBoard - Makefile
#  Tout passe par Docker : personne n'a besoin d'installer PHP, Composer ou Node.
#  `make` ou `make help` affiche la liste des commandes.
# =============================================================================

# Le Makefile a besoin d'un shell Linux : lancé depuis cmd/PowerShell, `echo ... > .env` écrit un .env
# illisible par Docker (guillemets + CRLF). Sous Windows, on travaille dans WSL 2 (ou le devcontainer).
ifeq ($(OS),Windows_NT)
$(error Lancez make depuis un terminal WSL 2 (ou le devcontainer), pas depuis cmd/PowerShell. Supprimez ensuite le fichier .env s'il a été créé)
endif

# Les conteneurs tournent avec votre UID/GID : les fichiers générés (vendor/,
# node_modules/, migrations...) vous appartiennent, pas à root.
export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)

DC       := docker compose
PHP      := $(DC) exec api-php
SF       := $(DC) exec api-symfony
FRONT    := $(DC) run --rm --no-deps front
CONSOLE  := $(SF) php bin/console

# Arguments libres : make composer-php c="require monolog/monolog"
c ?=

.DEFAULT_GOAL := help
.PHONY: help

help: ## Affiche cette aide
	@awk 'BEGIN {FS = ":.*##"} \
		/^##@/ { printf "\n\033[1;33m%s\033[0m\n", substr($$0, 5) } \
		/^[a-zA-Z0-9_-]+:.*##/ { printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)
	@echo ""
	@echo "  URLs : front http://localhost:5173 | API PHP http://localhost:8081 | API Symfony http://localhost:8082 | Adminer http://localhost:8090"

##@ Démarrage
.PHONY: install up down restart ps logs stop build clean

install: build ## Première installation complète (build + dépendances + bases de données)
	$(DC) up -d db
	$(DC) run --rm --no-deps api-php composer install
	$(DC) run --rm --no-deps api-symfony composer install
	$(FRONT) npm install
	$(MAKE) up
	$(MAKE) php-db-reset
	$(MAKE) sf-db-reset
	@printf "\n✅ Installation terminée. Ouvrez http://localhost:5173\n"

build: ## Construit les images Docker
	$(DC) build

up: ## Démarre tous les conteneurs
	$(DC) up -d --remove-orphans
	@$(DC) ps --format "table {{.Service}}\t{{.Status}}\t{{.Ports}}"

down: ## Arrête tous les conteneurs
	$(DC) down --remove-orphans

restart: down up ## Redémarre tous les conteneurs

ps: ## Liste les conteneurs
	$(DC) ps

logs: ## Suit les logs (make logs s=api-php pour un seul service)
	$(DC) logs -f --tail=100 $(s)

stop: ## Arrête un seul service (make stop s=api-symfony), relancé par make up
	@test -n "$(s)" || (echo "Préciser le service : make stop s=api-symfony" && exit 1)
	$(DC) stop $(s)

clean: ## ⚠️  Supprime conteneurs, volumes (données BDD) et dépendances installées
	$(DC) down -v --remove-orphans
	rm -rf apps/api-php/vendor apps/api-symfony/vendor apps/api-symfony/var apps/front/node_modules

##@ Partie 1 - API en PHP pur (http://localhost:8081)
.PHONY: php-sh php-composer php-check php-check-a php-check-b php-check-c php-check-lots php-lint php-db-reset php-psql

php-sh: ## Ouvre un shell dans le conteneur PHP pur
	$(PHP) bash

php-composer: ## Lance Composer (make php-composer c="dump-autoload")
	$(PHP) composer $(c)

php-check: ## Vérifie que l'API respecte le contrat (docs/api-contract.md)
	$(PHP) php tools/contract-check.php http://localhost:8000

php-check-a: ## 🅰️  Vérifie le Lot A (HTTP & routage) — Dev Back 1
	$(PHP) php tools/checks/lot-a.php

php-check-b: ## 🅱️  Vérifie le Lot B (Données & PDO) — Dev Back 2
	$(PHP) php tools/checks/lot-b.php

php-check-c: ## 🅲  Vérifie le Lot C (Métier & validation) — Dev Back 3
	$(PHP) php tools/checks/lot-c.php

php-check-lots: ## Vérifie les 3 lots d'un coup
	-$(PHP) php tools/checks/lot-a.php
	-$(PHP) php tools/checks/lot-b.php
	-$(PHP) php tools/checks/lot-c.php

php-lint: ## Vérifie la syntaxe de tous les fichiers PHP
	$(PHP) sh -c 'find public src -name "*.php" -exec php -l {} \; | grep -v "No syntax errors" || echo "OK : aucune erreur de syntaxe"'

php-db-reset: ## Recrée la table tasks et ses données de départ
	$(DC) exec -T db psql -U app -d taskboard_php -v ON_ERROR_STOP=1 < apps/api-php/database/schema.sql
	@echo "✅ Base taskboard_php réinitialisée"

php-psql: ## Console SQL sur la base de l'API PHP pur
	$(DC) exec db psql -U app -d taskboard_php

##@ Partie 2 - API Symfony (http://localhost:8082)
.PHONY: sf-sh sf sf-composer sf-cc sf-routes sf-entity sf-migration sf-migrate sf-db-reset sf-test sf-kata sf-atelier dql sql sf-check sf-psql

sf-sh: ## Ouvre un shell dans le conteneur Symfony
	$(SF) bash

sf: ## Lance bin/console (make sf c="debug:router")
	$(CONSOLE) $(c)

sf-composer: ## Lance Composer (make sf-composer c="require symfony/uid")
	$(SF) composer $(c)

sf-cc: ## Vide le cache Symfony
	$(CONSOLE) cache:clear

sf-routes: ## Liste les routes de l'API
	$(CONSOLE) debug:router --show-controllers

sf-entity: ## Crée / modifie une entité Doctrine (assistant interactif)
	$(CONSOLE) make:entity

sf-migration: ## Génère une migration à partir des entités
	$(CONSOLE) make:migration

sf-migrate: ## Exécute les migrations en attente
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

sf-db-reset: ## Recrée la base Symfony + migrations + fixtures
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	$(CONSOLE) doctrine:fixtures:load --no-interaction

sf-test: ## Lance les tests PHPUnit (make sf-test c="--filter=TaskControllerTest")
	$(SF) php bin/console doctrine:database:create --env=test --if-not-exists
	$(SF) php bin/console doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration -q
	$(SF) php bin/phpunit --testsuite app $(c)

sf-kata: ## Kata Doctrine & DQL (make sf-kata c="--filter=K05")
	$(SF) php bin/console doctrine:database:create --env=test --if-not-exists -q
	$(SF) php bin/console doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration -q
	$(SF) php bin/console doctrine:fixtures:load --env=test --group=kata --no-interaction -q
	$(SF) php bin/phpunit --testsuite kata --testdox $(c)

sf-atelier: ## Atelier pratique Symfony, étape 2 (make sf-atelier c="--filter=A1")
	$(SF) php bin/console doctrine:database:create --env=test --if-not-exists -q
	$(SF) php bin/console doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration -q
	$(SF) php bin/phpunit --testsuite atelier --testdox $(c)

dql: ## Exécute une requête DQL (make dql q="SELECT a.name FROM App\Kata\Entity\Author a")
	$(CONSOLE) doctrine:query:dql --hydrate=array "$(q)"

sql: ## Exécute une requête SQL sur la base Symfony (make sql q="SELECT * FROM task")
	$(CONSOLE) dbal:run-sql "$(q)"

sf-check: ## Vérifie que l'API Symfony respecte le contrat (même script que la partie 1)
	$(DC) exec api-php php tools/contract-check.php http://nginx-symfony

sf-psql: ## Console SQL sur la base Symfony
	$(DC) exec db psql -U app -d taskboard

##@ Front React (http://localhost:5173)
.PHONY: front-sh npm front-lint front-build use-php use-symfony

front-sh: ## Ouvre un shell dans le conteneur Node
	$(DC) exec front sh

npm: ## Lance npm (make npm c="install react-router")
	$(FRONT) npm $(c)

front-lint: ## Lint (les warnings bloquent) + vérification des types TypeScript
	$(FRONT) sh -c "npm run lint -- --deny-warnings && npx tsc -b"

front-build: ## Build de production du front
	$(FRONT) npm run build

# .env (ignoré par Git) : choix local de l'API appelée par le front ; absent = API PHP pur (voir compose.yaml)
use-php: ## Le front appelle l'API PHP pur
	@echo "API_TARGET=http://api-php:8000" > .env
	$(DC) up -d front
	@echo "✅ Le front utilise l'API PHP pur"

use-symfony: ## Le front appelle l'API Symfony
	@echo "API_TARGET=http://nginx-symfony" > .env
	$(DC) up -d front
	@echo "✅ Le front utilise l'API Symfony"

# Cibles du formateur (support de formation, déploiement) : absentes du projet distribué aux participants
-include formation.mk
