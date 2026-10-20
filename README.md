# TaskBoard — Formations PHP · Symfony

Projet fil rouge pour apprendre **PHP**, **Symfony** et **Doctrine** sur un projet concret. Le fonctionnel
(un gestionnaire de tâches) est volontairement simple et **entièrement spécifié** dans
[le contrat d'API](docs/api-contract.md) : l'équipe se concentre uniquement sur la technique.

## Deux formations, un même projet

| Formation | Public | Équipe | Parcours | Support en ligne |
|---|---|---|---|---|
| **C# → PHP · Symfony · React** | Devs C# / .NET | 3 devs back + 1 dev front | [docs/00-organisation.md](docs/00-organisation.md) puis `docs/01-php` → `02-symfony` → `03-doctrine`, front `04-react` | https://formation.zedcode.fr |
| **Vue → PHP · Symfony** | Devs front Vue 3 qui passent au back | 3 devs back | [docs/vue/README.md](docs/vue/README.md) : fondamentaux back-end (HTTP, SQL, sécurité) → PHP → Symfony → Doctrine → projet | https://formation.zedcode.fr/vue |

Les deux formations partagent les applications (`apps/api-php`, `apps/api-symfony`, `apps/front`), le contrat d'API,
le kata Doctrine et la [charte IA](docs/00-charte-ia.md). Seuls les cours et l'organisation sont adaptés au public.
La suite de ce README décrit la formation C# ; la formation Vue a sa propre présentation dans [docs/vue/](docs/vue/README.md).

Formation C# : équipe cible **3 devs back** + **1 dev front** (charge front volontairement plus légère).
Parcours **à son rythme**, découpé en étapes, back et front en parallèle. Une étape est terminée quand elle est comprise :
si vous avancez vite, passez aux bonus ou à l'étape suivante — voir [« Socle, bonus et suite du projet »](docs/00-organisation.md#socle-bonus-et-suite-du-projet).
L'IA est autorisée partout ; on ne commite que du code lu, compris et assumé : [charte IA](docs/00-charte-ia.md).

```
┌──────────────────────┐   /api/*    ┌───────────────────────────┐
│  Front React (Vite)  │ ──proxy──▶  │ Partie 1 : API PHP pur    │ :8081
│  :5173               │             │ Partie 2 : API Symfony    │ :8082
└──────────────────────┘             └─────────────┬─────────────┘
                                                   ▼
                                        PostgreSQL 17 :5433  (Adminer :8090)
```

## Démarrage

Prérequis : **Docker** (Docker Desktop / OrbStack) et **make**. Rien d'autre : PHP, Composer, Node
et npm tournent dans des conteneurs.
Sous Windows : travailler dans **WSL 2** (cloner le dépôt dans le système de fichiers Linux, pas dans `/mnt/c`).

```bash
make install   # première fois : build, dépendances, bases de données (quelques minutes)
make help      # toutes les commandes
```

**VS Code (devcontainer)** : avec l'extension *Dev Containers*, ouvrez le dossier puis *Reopen in Container*.
Vous n'avez alors besoin que de Docker (pas de `make` à installer) : le terminal intégré a `make` et pilote la stack
avec le Docker de votre machine, et les extensions PHP / Xdebug / oxc sont installées. Toutes les commandes du parcours
(`make ...`, `curl http://localhost:8081/...`) fonctionnent telles quelles ; les URL s'ouvrent dans le navigateur de la machine.

| URL | Quoi |
|---|---|
| http://localhost:5173 | Front React |
| http://localhost:8081/api/health | API PHP pur (partie 1) |
| http://localhost:8082/api/health | API Symfony (partie 2) |
| http://localhost:8082/_profiler | Profiler Symfony (requêtes SQL, temps, logs...) |
| http://localhost:8090 | Adminer — serveur `db`, utilisateur `app`, mot de passe `app`, base `taskboard` ou `taskboard_php` |

Le front appelle toujours `/api/...` ; Vite redirige vers l'API choisie :
`make use-php` (partie 1) ou `make use-symfony` (partie 2).

## Déroulé

Prérequis : `make install` fait sur chaque poste **avant de commencer** ([checklist](docs/00-organisation.md#avant-de-commencer-chaque-participant)).

| Étape | Back (×3) | Front (×1), en parallèle |
|---|---|---|
| **1 — PHP** | Lancement ([contrat d'API](docs/api-contract.md), [charte IA](docs/00-charte-ia.md)) · [cours PHP](docs/01-php/README.md) condensé · [API Tâches en PHP pur](docs/01-php/04-exercices-api-php-pur.md) dans `apps/api-php`, en **3 lots parallèles** (🅰️ HTTP & routage · 🅱️ Données & PDO · 🅲 Métier & validation) · intégration · revues orales | [Cours React](docs/04-react/README.md) + [F1 à F4](docs/04-react/03-exercices-front.md) |
| ⭐ fin de l'étape 1 | `make php-check-a/-b/-c` puis `make php-check` verts, tâches affichées dans le front (`make use-php`) | |
| **2 — Symfony** | [Cours Symfony](docs/02-symfony/README.md) (principes, différences avec ASP.NET Core, visite du projet) | F5 → F7 |
| **3 — Doctrine & DQL** | [Cours Doctrine](docs/03-doctrine/README.md) (ORM, relations, DQL) · [kata](docs/03-doctrine/04-kata-doctrine.md) (`make sf-kata`) | (suite F5 → F7) |
| **4 — Projet** | [Backlog back](docs/02-symfony/04-backlog-back.md) dans `apps/api-symfony` : socle par dev (projets · auth · filtres & commentaires), puis bonus · démo, oraux, rétro | F8 → F10 (F11 bonus) |

Déroulé détaillé : [organisation](docs/00-organisation.md#le-déroulé-par-étapes). Une équipe qui avance vite
enchaîne sur les bonus ou l'étape suivante ; ce qui n'est pas fait (bonus, fin du contrat de la partie 2) se poursuit sur le projet.

Chaque cours se termine par un **quiz** et des **questions à expliquer à l'oral** ; les lots, le kata et la démo
donnent lieu à une **revue orale** courte avec le formateur, sur son propre code.

### Commandes clés du parcours

| | |
|---|---|
| `make php-check-a` / `-b` / `-c` | Vérifie un lot de la partie 1, indépendamment des deux autres |
| `make php-check` | Vérifie l'API PHP pur complète contre le contrat |
| `make sf-kata` | Kata Doctrine & DQL (`c="--filter=K05"` pour un seul exercice) |
| `make dql q="SELECT ..."` | Exécute une requête DQL |
| `make sf-test` · `make sf-check` | Tests PHPUnit · contrat sur l'API Symfony |
| `make front-lint` | Lint + types du front |

## Contenu du dépôt

```
apps/
├── api-php/        Partie 1 — squelette : /api/health + classes à signatures fournies (lots A, B, C)
│   └── tools/      contract-check.php (contrat) + checks/lot-a|b|c.php (un script de vérification par lot)
├── api-symfony/    Parties 2 à 4 — Symfony 7.4 LTS, l'API Tâches de la partie 1 est déjà portée (+ tests)
│   └── src/Kata/   kata Doctrine & DQL (domaine « bibliothèque » isolé, 21 tests dans tests/Kata)
└── front/          React 19 + TypeScript + Vite — un composant TaskList, à enrichir par le dev front
docs/
├── 00-organisation.md     rôles, déroulé par étapes, socle et bonus
├── 00-charte-ia.md        usage de l'IA (autorisée partout) et contrôle de la compréhension
├── api-contract.md        ← spécification fonctionnelle, source de vérité
├── 01-php/                cours + exercices (étape 1)
├── 02-symfony/            cours (étape 2) + backlog (étape 4)
├── 03-doctrine/           cours + kata (étape 3)
└── 04-react/              cours + backlog front
docker/                    images et configuration (PHP 8.4, nginx, PostgreSQL)
compose.yaml · Makefile
```

## Stack

| | Version | Équivalent .NET |
|---|---|---|
| PHP | 8.4 (FPM + serveur intégré) | C# / CLR |
| Composer | 2 | NuGet |
| Symfony | 7.4 LTS | ASP.NET Core |
| Doctrine ORM | 3 | Entity Framework Core |
| PHPUnit | 13 | xUnit |
| PostgreSQL | 17 | SQL Server |
| React | 19 + TypeScript 6 | Blazor |
| Vite | 8 | MSBuild + dev server |

## Débogage pas-à-pas (Xdebug)

```bash
XDEBUG_MODE=debug make up
```

- **VS Code** : extension *PHP Debug*, configurations fournies dans `.vscode/launch.json`. Fonctionne aussi dans le
  devcontainer (il publie le port 9003 sur la machine, que les conteneurs PHP appellent via `host.docker.internal`).
- **PhpStorm** : *Settings › PHP › Servers* → ajouter un serveur nommé `taskboard-php` (host `localhost`, port `8081`)
  avec le path mapping `apps/api-php` → `/app`, et un second nommé `taskboard-symfony` (port `8082`)
  avec `apps/api-symfony` → `/app`. Puis activer l'écoute des connexions de debug (icône 📞).

Revenir sans Xdebug (plus rapide) : `make up`.
