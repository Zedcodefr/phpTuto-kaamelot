# Organisation — formation Vue → PHP · Symfony

## Objectif

À la fin du parcours, chaque dev doit être capable de :

- expliquer ce qui se passe **côté serveur** entre l'appel `fetch` d'un composant et la réponse JSON ;
- écrire du **SQL** correct et lire le SQL généré par un ORM ;
- lire et écrire du **PHP moderne** idiomatique (pas du JavaScript avec des `$`) ;
- comprendre ce que fait un framework, **après** avoir tout écrit à la main ;
- développer une API REST **Symfony + Doctrine** testée, sécurisée et conforme à un contrat.

Le fonctionnel est figé dans [le contrat d'API](../api-contract.md). Toute question fonctionnelle se tranche
en relisant le contrat ; s'il est ambigu, le formateur décide et le contrat est mis à jour.

## Rôles

L'équipe compte **3 devs back**. Il n'y a pas de dev front dédié : le front fourni (React) sert à visualiser l'API.

| Rôle | Étape 2 — PHP pur | Étape 5 — Projet Symfony |
|---|---|---|
| Dev Back 1 | 🅰️ **Lot A** — HTTP & routage : `Request`, `JsonResponse`, `Router`, exceptions | **B1** — Projets (relations Doctrine) |
| Dev Back 2 | 🅱️ **Lot B** — Données & PDO : entité, connexion, repository | **B2** — Utilisateurs & authentification |
| Dev Back 3 | 🅲 **Lot C** — Métier & validation : contrôleur, validateur, lecture du JSON | **B3** — Filtres, pagination, commentaires |

Les rôles peuvent tourner entre l'étape 2 et l'étape 5 : celui qui a fait les données en PHP pur
gagne à faire l'authentification ensuite, et inversement.

Les lots de l'étape 2 sont détaillés dans [les exercices PHP](02-php/04-exercices-api-php-pur.md).
Chaque lot a sa propre commande de vérification (`make php-check-a`, `-b`, `-c`) qui ne dépend pas des deux autres :
les trois devs avancent **en parallèle**, puis on intègre.

## Le déroulé par étapes

**À votre rythme** : une étape est terminée quand elle est comprise. Si vous avancez vite, passez aux bonus
ou à l'étape suivante.

| Étape | Ce qu'on fait | Fin de l'étape |
|---|---|---|
| **Lancement** | Installation vérifiée, lecture du [contrat](../api-contract.md) et de la [charte IA](../00-charte-ia.md) | Tout le monde voit le front sur http://localhost:5173 |
| **1 — Fondamentaux back-end** | [HTTP côté serveur](01-fondamentaux/01-le-back-end-et-http.md), [SQL](01-fondamentaux/02-sql-et-bases-relationnelles.md) (exercices sur la vraie base), [sécurité](01-fondamentaux/03-securite-cote-serveur.md) + quiz | Chacun sait expliquer le trajet d'une requête et écrire un `JOIN` |
| **2 — PHP** | [Cours PHP](02-php/README.md) + quiz, exercices 1-2 en **mob programming**, puis **lots A/B/C en parallèle**, puis intégration | `make php-check` vert, front sur `make use-php`, revues orales des lots |
| **3 — Symfony** | [Cours Symfony](03-symfony/README.md) + quiz, visite guidée du projet, [atelier pratique](03-symfony/04-atelier-pratique.md) (`make sf-atelier`) | Chacun sait relier chaque morceau du PHP pur à son équivalent Symfony ; atelier vert + débrief |
| **4 — Doctrine & DQL** | [Cours Doctrine](04-doctrine/README.md) + quiz, [kata](04-doctrine/04-kata-doctrine.md) individuel | Kata socle vert (`make sf-kata`), débrief collectif |
| **5 — Projet** | [Backlog](05-projet/backlog-back.md) : chaque dev livre son **socle**, puis attaque les bonus | `make sf-test` et `make sf-check` verts, démo, revues orales, rétro |

### Socle et bonus

- Le **socle** est ce que chaque dev doit livrer et savoir expliquer : lots de l'étape 2, kata socle,
  tickets « Socle » du backlog.
- Les **bonus** sont pour ceux qui avancent vite : katas bonus, tickets « Bonus » du backlog,
  [brancher un front Vue](05-projet/bonus-front-vue.md) sur l'API.
- Ce qui n'est pas fait à la fin de la formation (bonus, fin du contrat d'API) se poursuit sur le projet.

## Avant de commencer (chaque participant)

- [ ] Docker et make installés (sous Windows : dépôt cloné dans WSL 2, pas dans `/mnt/c`)
- [ ] Projet de départ téléchargé depuis la page *Ressources* du support, puis `make install` sans erreur
- [ ] http://localhost:5173 affiche le front, http://localhost:8082/api/health répond `{"status":"ok"}`
- [ ] `make php-check-a` affiche « à implémenter » (c'est normal : c'est votre travail)
- [ ] Un éditeur PHP prêt : PhpStorm, ou VS Code avec les extensions recommandées du projet (*PHP Intelephense*, *PHP Debug*,
  *YAML*... : VS Code les propose à l'ouverture du dossier, cliquez *Installer*).
  Sous VS Code, le plus simple est le **devcontainer** fourni : extension *Dev Containers*, puis *Reopen in Container*
  (`make` et les extensions y sont déjà ; seul Docker est nécessaire sur la machine)

## Rituels (par événement)

| Quand | Quoi |
|---|---|
| **Début de chaque étape** | Point d'équipe : objectif de l'étape, qui fait quoi, points de vigilance |
| **Blocage** | On le dit tout de suite au point suivant ou au formateur : pas de héros solitaire |
| **Fin des lots (étape 2)** | Intégration à trois, puis **revue orale** de chaque lot avec le formateur |
| **Fin du kata (étape 4)** | Débrief collectif sur quelques katas clés (fetch join, mise à jour de masse, côté propriétaire d'une relation) |
| **Chaque PR** | Revue croisée par un autre dev back |
| **Fin du projet (étape 5)** | **Démo** : chacun présente un morceau de code dont il est fier et un piège rencontré, puis rétro |

## Contrôles de compréhension

L'IA est autorisée partout ([charte](../00-charte-ia.md)) : on vérifie donc la **compréhension**, pas la production de code.

| Dispositif | Quand | Principe |
|---|---|---|
| **Quiz** | Fin de chaque cours, dans le support | Seuil conseillé : 80 %. Répondez d'abord seul. |
| **Questions à expliquer à l'oral** | Affichées sous chaque quiz | Préparez-les avec vos mots, sur votre code |
| **Revue orale** | Fin des lots, du kata, du projet | Courte, avec le formateur, sur **votre** code, assistant fermé |
| **Pull request** | Chaque ticket | Section « Ce que l'IA a produit / ce que j'ai vérifié » |

Avoir utilisé l'IA est normal. Ne pas savoir expliquer son code ne l'est pas.

## Git

- Une branche par lot, kata ou ticket : `feature/lot-b-repository`, `kata/<prénom>`, `feature/B1-2-project-tasks`.
- PR petite et ciblée, description = ce qui a été fait + comment tester.
- On ne merge pas une PR qui casse `make php-check`, `make sf-test` ou `make sf-check`.
- Migrations Doctrine : une par ticket, générée **après** avoir rebasé, jamais modifiée une fois mergée.
- Le kata ne se merge pas : chacun garde sa branche `kata/<prénom>`.

## Règles d'or

1. **Toujours passer par `make`** : pas de PHP, Composer ni Node installés en local. Si une commande manque, on l'ajoute au Makefile.
2. **Le contrat d'API fait foi** : on code contre le contrat, pas contre ce que « le front a l'air d'attendre ».
3. **Le serveur ne fait jamais confiance au client** : validation, authentification et autorisation se font côté back,
   même si le front vérifie déjà (voir [sécurité côté serveur](01-fondamentaux/03-securite-cote-serveur.md)).
4. **On filtre en SQL, pas en PHP** : on ne charge pas toute une table pour faire un `array_filter`, comme on ne
   ferait pas un `.filter()` sur un million d'éléments dans le navigateur.
5. **Lire les erreurs en entier** (`make logs s=api-php`, profiler Symfony, réponse HTTP dans l'onglet Réseau) avant de demander de l'aide.
6. **[Charte IA](../00-charte-ia.md)** : tout ce qui est commité est lu, compris et assumé.

## Le support en ligne

Le parcours, les cours, les quiz et le téléchargement du projet sont sur **https://formation.zedcode.fr/vue**.
La progression et les scores sont enregistrés dans votre navigateur.

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
| Une requête SQL « ne marche pas » dans psql | Avez-vous terminé par `;` ? Êtes-vous sur la bonne base (`taskboard_php` ou `taskboard`) ? |
| Permissions sur les fichiers générés (Linux) | Les conteneurs tournent avec votre UID : relancer via `make`, pas `docker compose` directement |
| Devcontainer : `curl localhost:…` ne répond pas | La stack est-elle démarrée (`make up`) ? Puis relancer les relais de ports : `bash .devcontainer/forward-ports.sh` |
| Devcontainer : Xdebug ne s'arrête pas sur le point d'arrêt | Le port 9003 est-il pris sur la machine (PhpStorm, autre devcontainer) ? `XDEBUG_MODE=debug make up` lancé ? |
| Repartir de zéro | `make clean && make install` (⚠️ supprime les données) |
