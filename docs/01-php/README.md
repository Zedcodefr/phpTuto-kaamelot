# Partie 1 — PHP (pour développeurs C#)

> **Public** : les 3 développeurs back (le dev front suit en parallèle [la partie React](../04-react/)).
> **À votre rythme** : une étape est terminée quand elle est **comprise**. Si vous avancez vite, passez aux bonus ou à l'étape suivante. Ce qui compte, c'est de **comprendre** ce que vous écrivez.
> **Livrable** : une API REST en PHP « pur » (sans framework), construite à trois en **3 lots parallèles** (🅰️ HTTP & routage · 🅱️ Données & PDO · 🅲 Métier & validation), qui passe `make php-check` et alimente le front React.

## Objectifs

À la fin de cette partie, vous savez :

- expliquer le **modèle d'exécution** de PHP et ce qui le distingue d'ASP.NET Core ;
- lire et écrire du PHP moderne (8.4) **typé strictement**, orienté objet ;
- éviter les **pièges classiques** d'un développeur C# (tableaux copiés par valeur, `==`, références...) ;
- manipuler HTTP « à la main » : superglobales, en-têtes, JSON, codes de statut ;
- accéder à une base de données avec **PDO** et des requêtes préparées ;
- structurer une petite application : front controller, routeur, contrôleurs, repository.

Construire tout cela à la main est volontaire : en partie 2, vous verrez exactement **ce que Symfony fait pour vous**.

## Déroulé de l'étape 1

| # | Bloc | Quoi |
|---|---|---|
| 1 | Lancement | Vérification de l'installation (faite **avant** si possible), [contrat d'API](../api-contract.md), [charte IA](../00-charte-ia.md) |
| 2 | Cours condensé | [Principes](01-principes.md), [grammaire](02-grammaire.md), [PHP & HTTP](03-php-et-http.md) + quiz |
| 3 | [Mob programming](04-exercices-api-php-pur.md#phase-1--mob-programming-ex-1-et-2) | Exercices 1 et 2, à trois sur un écran |
| 4 | Lecture des squelettes | Qui possède quoi, pourquoi les signatures sont figées |
| 5 | **3 lots en parallèle** | Chacun son lot, jusqu'à **LOT TERMINÉ** |
| 6 | Intégration | `make php-check` vert, le front affiche les tâches (`make use-php`) |
| 7 | Revues orales + débrief | Chaque dev explique son propre code |

Un bloc terminé et compris ? Enchaînez sur le suivant.

## Les cours : à voir en séance / référence

Les cours sont complets, mais pendant le cours condensé on ne lit que l'essentiel. Le reste sert de **référence pendant les exercices**
(l'IA est aussi là pour ça : demandez-lui « l'équivalent C# de… », puis vérifiez dans le cours ou sur php.net).

| Chapitre | À voir en séance | Référence pendant les exercices |
|---|---|---|
| [1. Principes](01-principes.md) | [Le modèle « share nothing »](01-principes.md#12-le-modèle-dexécution--share-nothing-), [typage strict](01-principes.md#14-typage--dynamique-par-défaut-strict-par-choix), [Composer et l'autoload](01-principes.md#15-composer--nuget--lautoload) | OPcache, PSR, écosystème, Xdebug |
| [2. Grammaire](02-grammaire.md) | [Le tableau récapitulatif C# → PHP](02-grammaire.md#21-tableau-récapitulatif), [le tableau PHP](02-grammaire.md#25-le-tableau-php-array), [`==` vs `===`](02-grammaire.md#-vs---utilisez-toujours-), [les 15 pièges](02-grammaire.md#216-15-pièges-pour-un-dev-c) | Tout le reste : chaînes, closures, POO détaillée, enums, namespaces, dates… |
| [3. PHP & HTTP](03-php-et-http.md) | [Le front controller](03-php-et-http.md#31-le-front-controller), [`php://input`](03-php-et-http.md#pourquoi-le-json-nest-pas-dans-_post), [PDO et l'injection SQL](03-php-et-http.md#injection-sql) | En-têtes, JSON, transactions, routage, gestion d'erreurs : à relire **pendant** votre lot |
| [4. Exercices](04-exercices-api-php-pur.md) | Le fil rouge en 4 phases : mob → squelettes → **3 lots en parallèle** → intégration | |

À la fin de chaque cours : le **quiz** de l'app de formation (objectif ≥ 80 %).

## Comment on travaille à trois

Les classes de `apps/api-php/src` sont fournies sous forme de **squelettes** : signatures, constructeurs et interface du repository figés, corps des méthodes à écrire. Chaque méthode appartient à **un** lot, et chaque lot a son script de vérification, qui fonctionne sans le code des deux autres (faux repository en mémoire, réponses inspectées sans être envoyées, transaction annulée). Le détail est dans [les exercices](04-exercices-api-php-pur.md#organisation-à-3-devs-back).

## Et l'IA ?

Elle est autorisée **partout**, comme dans votre futur projet. Une seule exigence : **tout ce que vous commitez,
vous l'avez lu, exécuté et compris**, et vous savez l'expliquer ligne par ligne en revue de lot. Conseil pour
cette étape : essayez d'abord, puis servez-vous de l'IA pour débloquer, expliquer et vérifier — c'est la façon la
plus efficace d'apprendre la syntaxe. Voir la [charte IA](../00-charte-ia.md).

## Références

- Contrat d'API à respecter : [docs/api-contract.md](../api-contract.md) (partie 1)
- Code de départ : [apps/api-php](../../apps/api-php/)
- Manuel officiel : <https://www.php.net/manual/fr/>
- Nouveautés PHP 8.4 : <https://www.php.net/releases/8.4/fr.php>
- PHP The Right Way : <https://phptherightway.com/>

## Commandes utiles

```bash
make up              # démarre tout
make php-sh          # shell dans le conteneur PHP
make php-check-a    # 🅰️ vérifie le Lot A (HTTP & routage)      — Dev Back 1
make php-check-b    # 🅱️ vérifie le Lot B (Données & PDO)       — Dev Back 2
make php-check-c    # 🅲 vérifie le Lot C (Métier & validation) — Dev Back 3
make php-check-lots # les trois lots d'un coup
make php-check       # vérifie le contrat d'API (après intégration)
make php-lint        # vérifie la syntaxe
make logs s=api-php  # logs (erreurs PHP, error_log)
make php-db-reset    # remet la base dans son état initial
make php-psql        # console SQL
```
