# Organisation de la formation

## Objectif

Chacun doit repartir capable de travailler sur le vrai projet qui suit :

- **Devs back** : lire et écrire du PHP moderne idiomatique ; comprendre ce que fait un framework après avoir
  d'abord écrit une API à la main ; maîtriser l'essentiel de Doctrine (mapping, relations, DQL, Unit of Work) ;
  développer à trois sur une API REST Symfony testée.
- **Dev front** : construire une petite application React + TypeScript qui consomme une API REST
  (routing, formulaires, gestion d'erreurs, authentification).

On **ne cherche pas** à concevoir un produit : le fonctionnel est figé dans [le contrat d'API](api-contract.md).
Toute question fonctionnelle se tranche en relisant le contrat ; s'il est ambigu, le formateur décide et le contrat est mis à jour.

**L'IA est autorisée partout. Commiter du code qu'on n'a pas lu et compris ne l'est pas** : lisez la [charte IA](00-charte-ia.md) avant de commencer.

## Avant de commencer (chaque participant)

- [ ] Docker (Docker Desktop / OrbStack, ou WSL 2 sous Windows) et `make` installés.
  Sous VS Code, le **devcontainer** fourni suffit : Docker + extension *Dev Containers*, puis *Reopen in Container*
  (`make` et les extensions PHP / Xdebug y sont déjà ; toutes les commandes du parcours s'y lancent telles quelles).
- [ ] Projet récupéré (application de formation → **Ressources** → `taskboard-projet.zip`, ou dépôt Git).
- [ ] `make install` terminé sans erreur, puis http://localhost:5173 affiche le front.
- [ ] `make sf-check` affiche « TOUT EST VERT ».
- [ ] La charte IA et le contrat d'API parcourus.

Une installation qui échoue au moment de démarrer bloque tout le monde : on la fait **avant**.

## Le déroulé par étapes

Le parcours se fait **à votre rythme**. Une étape est terminée quand elle est **comprise** :
si vous avancez vite, passez aux bonus ou à l'étape suivante.

| Étape | Back (×3) | Front (×1), en parallèle |
|---|---|---|
| **Lancement** | Objectifs, [contrat d'API](api-contract.md), [charte IA](00-charte-ia.md) | idem |
| **1 — PHP** | Cours condensé : [principes](01-php/01-principes.md), [grammaire et pièges](01-php/02-grammaire.md), [PHP & HTTP](01-php/03-php-et-http.md) + quiz | [React — principes](04-react/01-principes.md) et [particularités](04-react/02-particularites.md) + quiz |
| | [Exercices](01-php/04-exercices-api-php-pur.md) : exercices 1-2 **en mob**, lecture des squelettes, lots 🅰️ 🅱️ 🅲 en parallèle (`make php-check-a` / `-b` / `-c`), intégration (`make php-check` vert, front branché avec `make use-php`) | F1 → F4 sur l'API Symfony (`make use-symfony`), puis `make use-php` pour la démo commune |
| | **Revues orales des lots** + débrief | Démo commune |
| **2 — Symfony** | [Symfony](02-symfony/README.md) : principes, particularités, [visite du projet](02-symfony/03-visite-du-projet.md) + quiz | F5 — routing + page détail |
| | [Atelier pratique](02-symfony/05-atelier-pratique.md) : 5 exercices avec tests (`make sf-atelier`), seul ou en binôme sur `atelier/<prénom>` (jamais mergée), puis débrief | (suite F5) |
| **3 — Doctrine & DQL** | [Doctrine](03-doctrine/README.md) : ORM et mapping, relations, DQL et QueryBuilder + quiz | F6 — formulaire de création (422) |
| | [Kata Doctrine](03-doctrine/04-kata-doctrine.md) : K01 → K16 + K20 obligatoires, le reste en bonus ; débrief K06 / K12 / K20 | F7 — statut et suppression · relecture d'une PR back |
| **4 — Projet** | [Backlog](02-symfony/04-backlog-back.md) : chacun pose ses entités et migrations (ordre de passage sur `Task`), puis livre son **socle obligatoire**, puis des bonus. B1 projets · B2 authentification · B3 filtres/pagination + commentaires | F8 projets · F9 authentification · F10 commentaires (mocks conformes au contrat en attendant les livraisons back) ; F11 en bonus |
| | Intégration (`make sf-test`, `make sf-check`, front branché sur Symfony), **démo, revues orales, rétrospective** | idem |

Une équipe qui termine une étape enchaîne directement sur la suivante : il n'y a rien à « attendre ».

## Socle, bonus et suite du projet

| | Socle (dans la formation) | Bonus / suite sur le projet |
|---|---|---|
| PHP | Toute l'API Tâches en PHP pur (`make php-check` vert) | Bonus des exercices |
| Doctrine | Cours + kata K01 → K16 et K20 | K17 à K21 restants |
| Backlog Symfony | Le **socle** de chaque dev (voir le [backlog](02-symfony/04-backlog-back.md)) | Tickets bonus, parties restantes du contrat |
| Front | F1 → F10 | F11 et les bonus des tickets |

Prérequis : des développeurs C# confirmés (POO, SQL, HTTP, Git maîtrisés), l'IA utilisée selon la [charte](00-charte-ia.md),
l'environnement installé **avant** de commencer, un formateur disponible pour les oraux et les déblocages.

Les cours sont **condensés en séance** (on va à l'essentiel et aux pièges) ; les documents complets servent de
**référence** pendant les exercices et sur le vrai projet. Ce qui n'est pas fait (bonus, parties restantes du contrat)
se poursuit naturellement sur le projet : la formation n'est pas une course, c'est la compréhension qui est évaluée.

## Rôles

| Rôle | Étape 1 — PHP pur | Étape 3 — Doctrine | Étape 4 — Projet Symfony |
|---|---|---|---|
| Dev Back 1 | 🅰️ **Lot A — HTTP & routage** : `Request::fromGlobals`, `JsonResponse::send`, `Router`, exceptions, `index.php` | Kata individuel | **Projets** : relations Doctrine |
| Dev Back 2 | 🅱️ **Lot B — Données & PDO** : `Task`, `Connection`, `TaskRepository` | Kata individuel | **Utilisateurs & auth** : Security, tokens |
| Dev Back 3 | 🅲 **Lot C — Métier & validation** : `Request::json`, `TaskValidator`, `TaskController` | Kata individuel | **Filtres, pagination, commentaires** |
| Dev Front | Cours React + F1 → F4 | F5 → F7 (pendant les étapes 2 et 3) | F8 → F10 (F11 bonus) |

**Comment on travaille à trois sans s'attendre :**

- **Étape 1** : les signatures de toutes les classes sont fournies dans `apps/api-php/src`, ce sont des squelettes qui lèvent `TODO Lot X`.
  Chaque dev a sa commande de vérification, indépendante des deux autres lots :
  `make php-check-a`, `make php-check-b`, `make php-check-c`. Le Lot C teste son contrôleur avec un faux repository
  en mémoire. L'intégration (`make php-check`) se fait quand les trois lots sont verts.
  Détails : [exercices PHP](01-php/04-exercices-api-php-pur.md).
- **Étape 3** : chacun fait le kata, sur sa branche `kata/<prénom>`, qui n'est jamais mergée.
- **Étape 4** : chaque dev possède ses fichiers ; les fichiers partagés (`Task.php`…) suivent un ordre de passage.
  Voir [Travailler à 3 sans se marcher dessus](02-symfony/04-backlog-back.md#travailler-à-3-sans-se-marcher-dessus).

## Rituels

- **Court point de synchro** au début de chaque étape (et à chaque reprise de séance) : ce que j'ai fait, ce que je fais, ce qui me bloque.
- **Quiz** dans l'application de formation à la fin de chaque cours (objectif ≥ 80 %).
- **Revues orales courtes** avec le formateur : à la fin des lots (étape 1), après le kata (étape 3), à la démo finale.
  On explique **son** code, qu'on ait utilisé l'IA ou non (voir [la charte](00-charte-ia.md#8-comment-la-compréhension-est-vérifiée)).
- **Revue de code croisée** entre devs back sur chaque PR ; le dev front relit au moins une PR back (et inversement)
  pour voir l'autre côté du contrat.
- **Démo** à la fin de l'étape 1 et à la fin du projet : chacun présente un morceau de code dont il est fier et un piège rencontré.

## Git

- Une branche par lot, ticket ou kata : `feature/lot-a-router`, `feature/B1-2-project-crud`, `feature/F6-create-form`,
  `kata/<prénom>` (jamais mergée).
- PR petite et ciblée. La description contient ce qui a été fait, comment tester, et la section
  **« Ce que l'IA a produit / ce que j'ai vérifié »** ([modèle dans la charte](00-charte-ia.md#4-transparence--la-section-pr)).
- On ne merge pas une PR qui casse `make php-check-<lot>`, `make php-check`, `make sf-test` ou `make front-lint`.
- Migrations Doctrine : une par ticket, générée **après** avoir rebasé sur la branche principale ;
  on ne modifie jamais une migration déjà mergée.

## Règles d'or

1. **Toujours passer par `make`** : pas de `composer`, `php` ou `npm` installés en local.
   Si une commande manque, on l'ajoute au Makefile.
2. **Le contrat d'API fait foi.** Le front code contre le contrat, pas contre le comportement observé.
3. **L'IA est autorisée partout ; le code commité est lu, compris et assumé.** Règle des 3 questions avant chaque commit
   ([charte IA](00-charte-ia.md)).
4. **Lire les messages d'erreur en entier** (`make logs s=api-php`, `make logs s=api-symfony`, profiler Symfony,
   console du navigateur) avant de demander de l'aide.
5. **Comparer avec .NET est encouragé**, mais on cherche à écrire du PHP ou du React idiomatique,
   pas du C# avec une autre syntaxe.

## Le support de formation en ligne

Tous les cours, les quiz et les téléchargements sont dans l'**application de formation**, dont le formateur vous
envoie le lien :

- le parcours avec votre profil (back ou front) et votre progression ;
- chaque cours, suivi d'un quiz et des questions à savoir expliquer à l'oral ;
- les **ressources** : le projet de départ à télécharger (`taskboard-projet.zip`) et les cours en Markdown.

Votre progression et vos scores sont enregistrés **dans votre navigateur** : ils ne sont pas transmis au formateur.

## Dépannage rapide

| Problème | Solution |
|---|---|
| `port is already allocated` | Un autre service utilise le port : l'arrêter ou changer le port dans `compose.yaml` |
| `vendor/autoload.php` introuvable | `make install` (ou `make php-composer c=install`) |
| Classe introuvable après création d'un fichier | Vérifier namespace ↔ dossier (PSR-4) et le nom du fichier = nom de la classe |
| `make php-check-x` affiche « … à implémenter » | Normal : la méthode lève encore `TODO Lot X`, c'est votre travail |
| Symfony ne voit pas ma modification de config | `make sf-cc` |
| Kata : « Données du kata absentes » | Lancer `make sf-kata`, pas `bin/phpunit` directement (les fixtures du kata doivent être chargées) |
| Le front affiche « Impossible de charger les tâches » | L'API choisie répond-elle ? `curl localhost:8081/api/tasks` ; `make use-php` / `make use-symfony` |
| Permissions sur les fichiers générés (Linux) | Les conteneurs tournent avec votre UID : relancer via `make`, pas `docker compose` directement |
| Devcontainer : `curl localhost:…` ne répond pas | La stack est-elle démarrée (`make up`) ? Puis relancer les relais de ports : `bash .devcontainer/forward-ports.sh` |
| Devcontainer : Xdebug ne s'arrête pas sur le point d'arrêt | Le port 9003 est-il pris sur la machine (PhpStorm, autre devcontainer) ? `XDEBUG_MODE=debug make up` lancé ? |
| Repartir de zéro | `make clean && make install` (⚠️ supprime les données) |
