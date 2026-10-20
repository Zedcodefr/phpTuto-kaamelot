# Formation Vue → PHP · Symfony

Vous savez construire des interfaces avec **Vue 3** : composants, `ref`/`computed`, Pinia, Vue Router,
appels `fetch`/axios vers une API. Cette formation vous fait passer **de l'autre côté de l'API** :
vous allez écrire le serveur que vos composants appelaient, en **PHP** puis avec **Symfony** et **Doctrine**.

Le projet fil rouge est **TaskBoard**, un gestionnaire de tâches volontairement simple. Tout le fonctionnel
est figé dans [le contrat d'API](../api-contract.md) : vous vous concentrez uniquement sur la technique.

## Public et objectifs

| Vous savez déjà… | Vous saurez à la fin… |
|---|---|
| Consommer une API REST avec `fetch` / axios | **Concevoir et écrire** cette API, codes HTTP et erreurs compris |
| Manipuler des objets et des tableaux JSON | Modéliser des **tables relationnelles** et écrire du **SQL** |
| Gérer un état réactif (Pinia, `ref`) | Raisonner **sans état** : chaque requête repart de zéro, la base est la source de vérité |
| Valider un formulaire côté client | **Valider, authentifier et autoriser** côté serveur (le seul endroit qui compte) |
| Utiliser npm, Vite, TypeScript | Utiliser **Composer**, PHP 8.4 typé, **Symfony 7.4** et **Doctrine ORM 3** |

## Ce qui change quand on passe du front au back

| Côté front (Vue) | Côté back (PHP / Symfony) |
|---|---|
| Le code tourne **dans le navigateur** de l'utilisateur, qui peut tout lire et tout modifier | Le code tourne **sur le serveur** : c'est le seul endroit de confiance |
| Un composant vit tant que la page est ouverte, l'état persiste en mémoire | Un script PHP **naît et meurt à chaque requête** : aucun état en mémoire entre deux requêtes |
| La « donnée » est un objet JSON reçu de l'API | La donnée est une **ligne en base**, avec des contraintes, des relations et des transactions |
| Une erreur affiche un message à **un** utilisateur | Une erreur peut corrompre des données de **tous** les utilisateurs |
| Réactivité : on modifie l'état, la vue suit | Requête → traitement → réponse : on décrit **une transformation**, pas un état |

## Les 5 étapes

| Étape | Contenu | Résultat |
|---|---|---|
| **1 — Fondamentaux back-end** | [Le back-end et HTTP](01-fondamentaux/01-le-back-end-et-http.md) · [SQL et bases relationnelles](01-fondamentaux/02-sql-et-bases-relationnelles.md) · [Sécurité côté serveur](01-fondamentaux/03-securite-cote-serveur.md) | Vous ouvrez la « boîte noire » que le front appelait |
| **2 — PHP** | [Cours PHP](02-php/README.md) · [API Tâches en PHP pur](02-php/04-exercices-api-php-pur.md), en **3 lots parallèles** (🅰️ HTTP & routage · 🅱️ Données & PDO · 🅲 Métier & validation) | `make php-check` vert, les tâches s'affichent dans le front |
| **3 — Symfony** | [Cours Symfony](03-symfony/README.md) : principes, particularités, visite du projet | Vous retrouvez dans Symfony tout ce que vous avez écrit à la main |
| **4 — Doctrine & DQL** | [Cours Doctrine](04-doctrine/README.md) · [kata](04-doctrine/04-kata-doctrine.md) (`make sf-kata`) | Les requêtes « dans les doigts » |
| **5 — Projet Symfony** | [Backlog](05-projet/backlog-back.md) à trois : projets · authentification · filtres & commentaires · [bonus : brancher un front Vue](05-projet/bonus-front-vue.md) | Le contrat d'API implémenté, testé, démontré |

**À votre rythme** : une étape est terminée quand elle est comprise. Si vous avancez vite, passez aux bonus
ou à l'étape suivante. Organisation détaillée : [00-organisation.md](00-organisation.md).

## L'IA

L'IA est autorisée partout. Une seule règle : **on ne commite que du code lu, compris et assumé**.
Voir la [charte IA](../00-charte-ia.md), commune à toutes les formations.

## Démarrer

1. Suivez le parcours sur **https://formation.zedcode.fr/vue** (cours, quiz, progression).
2. Téléchargez le projet de départ depuis la page *Ressources* du support, puis :
   ```bash
   make install   # build, dépendances, bases de données (quelques minutes)
   make help      # toutes les commandes
   ```
3. Ouvrez http://localhost:5173 (le front fourni), puis lisez [l'organisation](00-organisation.md),
   [la charte IA](../00-charte-ia.md) et [le contrat d'API](../api-contract.md).

Prérequis : **Docker** et **make**. PHP, Composer, Node et PostgreSQL tournent dans des conteneurs :
rien d'autre à installer. Sous Windows, travaillez dans **WSL 2**.

## Le projet en un schéma

```
┌───────────────────────────┐  /api/*   ┌───────────────────────────┐
│  Front fourni (Vite)      │ ─proxy─▶  │ Étape 2 : API PHP pur     │ :8081
│  :5173  (React, lecture   │           │ Étapes 3-5 : API Symfony  │ :8082
│  seule pour vous)         │           └─────────────┬─────────────┘
└───────────────────────────┘                         ▼
                                           PostgreSQL 17 :5433  (Adminer :8090)
```

Le front fourni est écrit en React : vous n'avez pas à le modifier, il sert à **voir** votre API fonctionner.
Il appelle toujours `/api/...` — exactement comme un projet Vite + Vue avec un `server.proxy`.
