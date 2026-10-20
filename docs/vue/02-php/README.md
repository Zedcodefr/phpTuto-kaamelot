# Étape 2 — PHP, pour un dev Vue

Vous savez construire une interface avec Vue 3, consommer une API avec `fetch`, typer avec TypeScript.
Cette étape vous fait passer **de l'autre côté de l'appel HTTP** : vous écrivez l'API que le front consomme,
en **PHP pur**, sans framework. C'est volontaire : quand vous arriverez sur Symfony, vous saurez exactement
ce que le framework fait à votre place.

> **Prérequis** : [étape 1 — Fondamentaux back-end](../01-fondamentaux/README.md) (HTTP côté serveur, SQL, sécurité).
> **Spécification** : le [contrat d'API](../../api-contract.md), partie 1 (les tâches). Tout le fonctionnel y est décrit.
> **Règles IA** : la [charte IA](../../00-charte-ia.md). L'IA est autorisée ; ce que vous commitez, vous l'avez lu et compris.
> **À votre rythme** : une étape est terminée quand elle est **comprise**. Si vous avancez vite, passez aux bonus ou à l'étape suivante.

## Objectifs

À la fin de l'étape, chacun sait :

- expliquer le **modèle d'exécution** de PHP (une requête = un script exécuté de zéro) et ce qu'il change par rapport à Node ;
- lire et écrire du **PHP moderne** idiomatique : typage strict, classes, enums, tableaux, closures ;
- traiter une requête HTTP **à la main** : lire le corps JSON, router, répondre avec le bon code et les bons en-têtes ;
- interroger PostgreSQL avec **PDO** et des requêtes préparées ;
- expliquer ligne par ligne le lot qu'il a écrit.

## Déroulé

| Ordre | Contenu | Format |
|---|---|---|
| 1 | [Principes de PHP](01-principes.md) | cours + quiz |
| 2 | [La grammaire PHP pour un dev JS / TS](02-grammaire.md) | cours + quiz |
| 3 | [PHP et HTTP, comparé à Express](03-php-et-http.md) | cours + quiz |
| 4 | [Exercices : l'API TaskBoard en PHP pur, à trois](04-exercices-api-php-pur.md) | mob programming, puis lots A / B / C en parallèle, puis intégration |
| 5 | Revue orale de lot | chaque dev explique son code au formateur |

Le front React fourni (`apps/front`) sert uniquement à **voir le résultat** de votre API : vous n'avez pas à y toucher.

## À voir en séance / à garder comme référence

Les cours sont complets pour servir de **référence pendant les exercices**. En séance, concentrez-vous sur l'essentiel :

| À voir en séance | Référence (à consulter au besoin) |
|---|---|
| [Le modèle share-nothing](01-principes.md#12-le-grand-changement--share-nothing) | [L'écosystème](01-principes.md#19-lécosystème), [les PSR](01-principes.md#18-les-psr) |
| [Pas d'async, et pourtant ça tient](01-principes.md#13-pas-dasync-pas-de-promise--et-pourtant-ça-tient) | [Xdebug](01-principes.md#déboguer-avec-xdebug) |
| [Les types, vérifiés à l'exécution](01-principes.md#15-les-types--vérifiés-à-lexécution) | [Heredoc et nowdoc](02-grammaire.md#heredoc-et-nowdoc) |
| [Le tableau récapitulatif JS / TS → PHP](02-grammaire.md#tableau-récapitulatif-js--ts--php) | [Traits](02-grammaire.md#traits) |
| [Le tableau PHP et sa copie par valeur](02-grammaire.md#24-le-tableau-php-array) | [Property hooks](02-grammaire.md#property-hooks-et-visibilité-asymétrique-php-84) |
| [Closures : capture explicite](02-grammaire.md#closures--capture-explicite) | [Attributs](02-grammaire.md#210-attributs) |
| [Les 15 pièges pour un dev JS](02-grammaire.md#215-les-15-pièges-pour-un-dev-js) | [Transactions](03-php-et-http.md#transactions) |
| [Pourquoi le JSON n'est pas dans `$_POST`](03-php-et-http.md#pourquoi-le-json-nest-pas-dans-_post) | [Routage manuel](03-php-et-http.md#36-routage-manuel) |
| [PDO et l'injection SQL](03-php-et-http.md#35-pdo--parler-à-la-base-de-données) | [Gestion d'erreurs centralisée](03-php-et-http.md#37-gestion-derreurs-centralisée) |

## Commandes utiles

| Commande | Effet |
|---|---|
| `make up` | Démarre tous les conteneurs |
| `make php-sh` | Shell dans le conteneur PHP (`php -a` pour une console interactive, ≈ `node` sans argument) |
| `make php-check-a` / `-b` / `-c` | Vérifie **un lot**, indépendamment des autres |
| `make php-check-lots` | Les trois lots d'un coup |
| `make php-check` | Vérifie tout le contrat HTTP de la partie 1 |
| `make php-lint` | Erreurs de syntaxe dans tous les fichiers PHP |
| `make logs s=api-php` | Erreurs PHP et `error_log()` (≈ la console du terminal où tourne `node`) |
| `make php-db-reset` | Remet la table `task` dans son état initial |
| `make php-psql` | Console SQL sur la base `taskboard_php` |
| `make use-php` | Le front appelle votre API : <http://localhost:5173> |
| `make php-composer c="..."` | Lance Composer (≈ `npm ...`) |

Personne n'installe PHP ni Composer sur son poste : tout passe par Docker et le Makefile, comme si `node` et `npm`
n'existaient que dans un conteneur.
