# API TaskBoard — Symfony 7.4 (partie 2)

L'API Tâches de la partie 1 est **déjà portée** ici (même contrat, mêmes données).
Point de départ du [backlog back](../../docs/02-symfony/04-backlog-back.md).
Visite guidée : [docs/02-symfony/03-visite-du-projet.md](../../docs/02-symfony/03-visite-du-projet.md).

| | |
|---|---|
| URL | http://localhost:8082 — profiler : http://localhost:8082/_profiler |
| Console | `make sf c="debug:router"` |
| Tests | `make sf-test` (base `taskboard_test`) |
| Contrat partie 1 | `make sf-check` |
| Base de données | `make sf-db-reset` (drop + migrations + fixtures) |
| Nouvelle entité / champ | `make sf-entity` puis `make sf-migration` puis `make sf-migrate` |
