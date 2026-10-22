# Étape 3 — Symfony

> Vous avez écrit une API à la main en PHP pur. Vous allez maintenant découvrir le framework qui fait ce travail pour
> vous, et retrouver dans Symfony **chaque brique** que vous avez codée : routeur, requête, réponse JSON, erreurs,
> validation, accès aux données.

## Objectifs

À la fin de cette étape, vous savez :

- expliquer ce qu'est un framework back et en quoi il diffère de Vue (requête → réponse, pas d'arbre réactif) ;
- dérouler le **cycle de vie d'une requête** Symfony et dire où brancher du code (listeners, sécurité, contrôleur) ;
- expliquer l'**injection de dépendances** et l'**autowiring**, en partant du câblage manuel de votre `index.php` ;
- lire et modifier un contrôleur d'API : routes, DTO validés, sérialisation, erreurs JSON ;
- utiliser les outils du quotidien : `bin/console`, `debug:*`, profiler, MakerBundle.

## Déroulé

| Ordre | Contenu | Type |
|---|---|---|
| 1 | [Les principes](01-principes.md) : framework back, cycle de vie, injection de dépendances, tableau d'équivalences Vue / Node ↔ Symfony | Cours + [quiz](#quiz-et-oral) |
| 2 | [Les particularités](02-particularites.md) : Flex, cache, autowiring, Serializer, Validator, erreurs, sécurité, PATCH | Cours (lecture puis référence) |
| 3 | [La visite du projet](03-visite-du-projet.md) : `apps/api-symfony` lu à côté de votre `apps/api-php` | Lecture guidée du code |
| 4 | [L'atelier pratique](04-atelier-pratique.md) : route et service, contrainte de validation, écouteur d'événements, exception métier, commande console | 5 exercices avec tests (`make sf-atelier`) + débrief |
| ➜ | Étape suivante : [Doctrine & DQL](../04-doctrine/README.md), puis le [projet](../05-projet/backlog-back.md) | |

**À votre rythme** : l'étape est terminée quand vous savez expliquer le parcours d'une requête dans le projet.
Si vous avancez vite, faites l'[exercice bonus `priority`](03-visite-du-projet.md#6-exercice-bonus--le-champ-priority)
ou lisez les sections de référence.

## À voir en séance vs référence

| À voir en séance | Référence (à consulter pendant le projet) |
|---|---|
| [Un framework back, c'est quoi ?](01-principes.md#1-un-framework-back-cest-quoi-) | [Flex et les recettes](02-particularites.md#1-flex-et-les-recettes) |
| [Le cycle de vie d'une requête](01-principes.md#3-le-cycle-de-vie-dune-requête) | [Structure des dossiers](02-particularites.md#2-structure-des-dossiers), [cache compilé](02-particularites.md#3-le-cache-compilé) |
| [L'injection de dépendances](01-principes.md#4-linjection-de-dépendances-à-partir-de-zéro) | [Autowiring en détail](02-particularites.md#4-autowiring-en-détail) |
| [Le grand tableau d'équivalences](01-principes.md#5-le-grand-tableau-déquivalences-vue--node--symfony) | [Serializer](02-particularites.md#6-serializer), [Validator](02-particularites.md#7-validator) |
| [Ce qui va vous surprendre](01-principes.md#7-ce-qui-va-vous-surprendre) | [Erreurs JSON](02-particularites.md#8-erreurs-json), [Sécurité (survol)](02-particularites.md#9-sécurité-survol) |
| [PHP pur → Symfony](03-visite-du-projet.md#2-php-pur--symfony--la-table-de-correspondance) | [La limite du PATCH](02-particularites.md#10-la-limite-du-patch-avec-un-dto-nullable) |
| [Lecture de TaskController](03-visite-du-projet.md#3-lecture-commentée-de-taskcontroller) | [Outils de diagnostic](02-particularites.md#11-outils-de-diagnostic), [bonnes pratiques](02-particularites.md#12-bonnes-pratiques) |
| [Parcours d'un POST](03-visite-du-projet.md#4-parcours-dun-post-apitasks-de-bout-en-bout) | [Les commandes du Makefile](03-visite-du-projet.md#5-les-commandes-du-makefile) |

## Commandes utiles

| Commande | Usage |
|---|---|
| `make sf c="..."` | n'importe quelle commande `bin/console` (≈ `npm run ...`) |
| `make sf-routes` | toutes les routes de l'API avec leur contrôleur |
| `make sf-test` | tests PHPUnit de l'API (`c="--filter=TaskControllerTest"` pour filtrer) |
| `make sf-check` | vérifie que l'API Symfony respecte le contrat (le même script qu'à l'étape 2) |
| `make sf-atelier` | tests de l'[atelier pratique](04-atelier-pratique.md) (`c="--filter=A1"` pour un seul exercice) |
| `make sf-cc` | vide le cache Symfony |
| `make logs s=api-symfony` | logs du conteneur PHP-FPM |
| http://localhost:8082/_profiler | le **profiler** : requêtes SQL, événements, validation, sécurité… pour chaque requête |
| `make use-symfony` | le front fourni (http://localhost:5173) appelle l'API Symfony |

## Quiz et oral

Chaque cours se termine par un quiz dans le support en ligne. Préparez aussi de quoi expliquer, avec vos mots
et sur le code du projet :

- le trajet d'un `POST /api/tasks`, du navigateur jusqu'à l'`INSERT` ;
- pourquoi `TaskController` reçoit un `TaskRepository` sans jamais faire `new TaskRepository(...)` ;
- ce qui se passe si on envoie `{"title": ""}` et qui produit la réponse 422 ;
- pourquoi une validation dans le formulaire Vue ne suffit pas.

L'IA est autorisée pour comprendre et expérimenter ([charte IA](../../00-charte-ia.md)) : ce qui compte, c'est que
vous sachiez expliquer ce que vous avez lu et écrit.
