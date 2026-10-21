# Branche `formateur` — le code attendu

Cette branche part du projet de départ (`main`) et contient une solution complète des parties back :

| Partie | Où | Vérification |
|---|---|---|
| 1 — API PHP pur (lots A, B, C) | `apps/api-php/src`, `apps/api-php/public` | `make php-check-a` / `-b` / `-c`, `make php-check` |
| 2 — Kata Doctrine & DQL | `apps/api-symfony/src/Kata/Repository`, `Library.php` | `make sf-kata` (21 tests) |
| 2 — Backlog Symfony (B1 projets, B2 auth, B3 filtres et commentaires, socle et bonus) | `apps/api-symfony` | `make sf-test` (46 tests), `make sf-check` |

Après `git checkout formateur` : `make install` (ou `make sf-db-reset` si la stack tourne déjà), puis les commandes ci-dessus.

- `make sf-check` se connecte en `alice@taskboard.local` (fixtures) : les écritures sont protégées (B2-4b).
  `apps/api-php/tools/contract-check.php` accepte pour cela `API_TOKEN` ou `API_LOGIN="email:motdepasse"`.
- Le front (`apps/front`) est celui du projet de départ : il n'envoie pas de token, donc ses créations et modifications
  reçoivent 401 sur l'API Symfony de cette branche (`make use-symfony`). Les lectures fonctionnent.
- Il existe souvent plusieurs bonnes réponses : en revue orale, ce qui compte est que le stagiaire sache expliquer
  son choix (SQL généré, côté propriétaire d'une relation, codes HTTP du contrat).
