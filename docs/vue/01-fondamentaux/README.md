# Étape 1 — Fondamentaux back-end

Jusqu'ici, l'API était pour vous une **boîte noire** : on lui envoie un `fetch`, elle renvoie du JSON
(ou une erreur qu'on affiche dans un toast). Cette étape **ouvre la boîte**, avant d'écrire la moindre ligne de PHP.

| Vu du front | Ce qu'on découvre ici |
|---|---|
| `await fetch('/api/tasks')` | Qui reçoit la requête, qui l'exécute, d'où viennent les données |
| `response.status === 422` | Pourquoi le serveur a choisi **ce** code-là, et comment il le décide |
| Un tableau d'objets JSON | Des **tables**, des **lignes**, des **relations** et du **SQL** |
| La règle `required` d'un formulaire | La validation qui compte vraiment : celle du **serveur** |
| Un token dans `localStorage` | Ce que le serveur en fait : authentification, autorisation |

## Les cours

1. [Le back-end et HTTP](01-le-back-end-et-http.md) — le rôle du serveur, le trajet d'une requête, les codes HTTP, REST, le *stateless*.
2. [SQL et bases relationnelles](02-sql-et-bases-relationnelles.md) — du JSON aux tables, les requêtes essentielles, **exercices sur la vraie base du projet**.
3. [Sécurité côté serveur](03-securite-cote-serveur.md) — ne jamais faire confiance au client, injection SQL, mots de passe, authentification, autorisation, CORS, secrets.

Chaque cours se termine par un **quiz** et des **questions à expliquer à l'oral** dans le support en ligne.

## À voir en séance / référence

| À voir en séance | Référence à consulter pendant les exercices |
|---|---|
| Le trajet d'une requête, les codes de statut et quand les utiliser, *stateless* | Le détail des en-têtes HTTP, l'idempotence |
| Tables, clés, `JOIN`, `GROUP BY`, et les exercices SQL | Index, transactions, normalisation en détail |
| « Ne jamais faire confiance au client », injection SQL, 401 vs 403 | Hash des mots de passe, CORS, secrets, erreurs en production |

**À votre rythme** : l'étape est terminée quand vous savez expliquer, avec vos mots, ce qui se passe entre
le `fetch` d'un composant et la ligne en base. Si c'est déjà clair pour vous, passez à l'[étape 2 — PHP](../02-php/README.md).
