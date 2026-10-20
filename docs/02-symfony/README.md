# Partie 2 — Symfony (back)

> Public : développeurs C# / ASP.NET Core qui viennent d'écrire l'API TaskBoard en PHP pur (partie 1).
> Objectif : comprendre **ce que Symfony fait à votre place**, savoir où mettre chaque chose, puis livrer la partie 2 du [contrat d'API](../api-contract.md) à trois.

## Place dans le parcours

```
Étape 1 PHP pur ─▶ Étape 2 Symfony (cours) ─▶ Étape 3 Doctrine & DQL (cours + kata) ─▶ Étape 4 backlog + démo
                   ▲ ce dossier              ▲ docs/03-doctrine/                      ▲ 04-backlog-back.md
```

> **À votre rythme** : une étape est terminée quand elle est **comprise**. Si vous avancez vite, passez aux bonus ou à l'étape suivante. Ce qui compte, c'est de **comprendre** ce que vous écrivez.

| # | Bloc | Quoi |
|---|---|---|
| 1 | Cours Symfony | [Principes](01-principes.md), [particularités](02-particularites.md), [visite du projet](03-visite-du-projet.md) + quiz |
| 2 | [Doctrine & DQL](../03-doctrine/README.md) | ORM, relations, DQL + quiz |
| 3 | [Kata Doctrine](../03-doctrine/04-kata-doctrine.md) | K01 → K16 et K20 + débrief |
| 4 | [Backlog](04-backlog-back.md) — mise en place | Entités et migrations de chacun, point d'équipe |
| 5 | [Backlog](04-backlog-back.md) — réalisation | Socle obligatoire, puis bonus |
| 6 | Intégration et clôture | `make sf-check`, `make sf-test`, front branché, démo, oraux, rétro |

Doctrine, survolé dans les [particularités](02-particularites.md), est approfondi dans une partie dédiée —
[Doctrine & DQL](../03-doctrine/README.md), avec un kata de 21 tests (`make sf-kata`) — **avant** d'attaquer le backlog.

## Sommaire : à voir en séance / référence

Les cours sont complets ; pendant le cours, on ne voit que l'essentiel. Le reste est une **référence** à consulter pendant
le kata et le backlog (avec l'IA si besoin : demandez-lui, puis vérifiez dans le cours ou la doc officielle).

| # | Document | À voir en séance | Référence pendant le backlog |
|---|---|---|---|
| 1 | [Principes](01-principes.md) | [Le cycle de vie d'une requête](01-principes.md#2-le-cycle-de-vie-dune-requête), [le grand tableau d'équivalences ASP.NET Core ↔ Symfony](01-principes.md#3-le-grand-tableau-déquivalences), [ce qui va vous surprendre](01-principes.md#5-ce-qui-va-vous-surprendre) | Zoom sur le conteneur, la configuration, les événements |
| 2 | [Particularités](02-particularites.md) | [Autowiring](02-particularites.md#4-autowiring-en-détail), [Serializer et références circulaires](02-particularites.md#6-serializer), [Validator](02-particularites.md#7-validator), [outils de diagnostic](02-particularites.md#11-outils-de-diagnostic) | Flex, cache, Doctrine (vu en détail dans la partie suivante), sécurité, limite du PATCH, bonnes pratiques |
| 3 | [Visite du projet](03-visite-du-projet.md) | [PHP pur → Symfony](03-visite-du-projet.md#2-php-pur--symfony--la-table-de-correspondance), [TaskController](03-visite-du-projet.md#3-lecture-commentée-de-taskcontroller), [parcours d'un POST](03-visite-du-projet.md#4-parcours-dun-post-apitasks-de-bout-en-bout) | Arborescence, commandes ; exercice `priority` en bonus |
| — | [Doctrine & DQL](../03-doctrine/README.md) + kata | voir le README de la partie | |
| 4 | [Backlog back](04-backlog-back.md) | Socle obligatoire / bonus, travail à 3 sans conflit, définition of done | |

À la fin de chaque cours : le **quiz** de l'app de formation (objectif ≥ 80 %). L'IA est autorisée partout ;
l'exigence est de comprendre et d'assumer chaque ligne commitée (voir la [charte IA](../00-charte-ia.md)).

## Objectifs pédagogiques

À la fin de l'étape 4, chaque dev back sait :

- expliquer le chemin d'une requête HTTP dans Symfony (Kernel, événements, résolveurs d'arguments) ;
- créer une entité Doctrine avec relations, générer et exécuter une migration, écrire une requête QueryBuilder / DQL (approfondi en partie 3) ;
- recevoir et valider des données avec des DTO (`#[MapRequestPayload]`, `#[MapQueryString]`, contraintes) ;
- contrôler la sortie JSON (groupes de sérialisation, DTO de sortie) ;
- brancher un service, un listener d'événement ou un voter grâce à l'autowiring ;
- sécuriser une API par token (firewall, authenticator `access_token`, voter) ;
- écrire des tests fonctionnels `WebTestCase` ;
- diagnostiquer avec `bin/console debug:*` et le profiler.

## Avant de commencer

```bash
make up                 # tout démarre
make sf-db-reset        # base Symfony + fixtures
make sf-check           # le contrat partie 1 est vert sur l'API Symfony
make sf-test            # les tests PHPUnit passent
make use-symfony        # le front React parle maintenant à Symfony
```

Gardez ces liens ouverts :

- Documentation officielle : <https://symfony.com/doc/7.4/index.html>
- Doctrine ORM : <https://www.doctrine-project.org/projects/doctrine-orm/en/current/index.html>
- Votre propre API PHP pur (`apps/api-php`) : comparez systématiquement avec ce que vous avez écrit à la main.
