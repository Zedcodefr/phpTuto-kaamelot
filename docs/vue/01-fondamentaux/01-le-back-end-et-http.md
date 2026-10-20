# 1. Le back-end et HTTP

Côté Vue, une API se résume souvent à ça :

```ts
const tasks = ref<Task[]>([])
onMounted(async () => {
  const response = await fetch('/api/tasks')
  tasks.value = await response.json()
})
```

Ce cours décrit tout ce qui se passe **après** le `fetch`, et les décisions que le serveur prend pour vous répondre.

## 1.1 Ce que fait un back-end

Un back-end a quatre responsabilités que le front **ne peut pas** assumer, parce que le code du front
s'exécute sur la machine de l'utilisateur, qui peut le lire, le modifier ou l'ignorer.

| Responsabilité | Exemple TaskBoard | Pourquoi pas dans le front ? |
|---|---|---|
| **Persister** | Enregistrer une tâche en base, la relire plus tard | Le navigateur oublie tout (et chaque utilisateur a le sien) |
| **Valider** | Refuser un titre vide → `422` | Un `curl` contourne n'importe quel formulaire Vue |
| **Autoriser** | Seul l'auteur d'une tâche peut la supprimer → `403` | Masquer le bouton « Supprimer » n'empêche pas d'appeler l'API |
| **Orchestrer** | Créer une tâche, mettre à jour un compteur, journaliser, le tout de façon cohérente | Plusieurs écritures doivent réussir **ou échouer ensemble** (transaction) |

Le front, lui, **présente** et **ergonomise** : il affiche, guide, prévalide pour le confort.
Le back **garantit**.

## 1.2 Le trajet d'une requête

```
Navigateur                                 Serveur (Docker)
──────────                                 ────────────────
TaskList.vue                               nginx (serveur web)            :8082
  fetch('/api/tasks', { method: 'POST',      │  « est-ce un fichier ? non → index.php »
         body: JSON.stringify(...) })        ▼
  │                                        PHP-FPM exécute public/index.php
  ▼                                          │  1. lit la requête (méthode, chemin, corps)
proxy Vite (dev)  ── HTTP ──────────────▶    │  2. route : POST /api/tasks → TaskController::create
                                             │  3. valide le JSON          → 422 si invalide
                                             │  4. INSERT INTO task ...    ──▶ PostgreSQL
                                             │  5. construit la réponse JSON ◀── ligne insérée
  ◀───────────────────────────────────────── 201 Created + Location + JSON
  │
tasks.value.push(await response.json())
```

Points clés pour un dev front :

- Entre le `fetch` et la réponse, **tout** est du code back : routage, validation, SQL, sérialisation.
- La « vérité » est dans **PostgreSQL**, pas dans le store Pinia : votre `tasks.value` n'est qu'une **copie**.
- En PHP, le script `index.php` **naît à chaque requête et meurt juste après** (vous le verrez à l'étape 2).

## 1.3 HTTP côté serveur

### Les méthodes

| Méthode | Sens | Corps | Sûre ? | Idempotente ? | TaskBoard |
|---|---|---|---|---|---|
| `GET` | Lire | non | ✅ | ✅ | `GET /api/tasks` |
| `POST` | Créer / déclencher | oui | ❌ | ❌ | `POST /api/tasks` |
| `PATCH` | Modifier **une partie** | oui | ❌ | ✅ en pratique | `PATCH /api/tasks/3` `{"status":"done"}` |
| `PUT` | Remplacer **entièrement** | oui | ❌ | ✅ | (non utilisé dans le contrat) |
| `DELETE` | Supprimer | non | ❌ | ✅ | `DELETE /api/tasks/3` |

- **Sûre** : ne modifie rien. Un `GET` ne doit **jamais** écrire en base (les navigateurs, proxys et robots
  se permettent de le rejouer ou de le précharger).
- **Idempotente** : l'appeler 1 fois ou 10 fois laisse la base dans le même état. Supprimer la tâche 3 deux fois :
  elle est supprimée (la seconde fois, on répond `404`, mais l'état est le même). Créer deux fois : **deux** tâches.
  C'est pour ça qu'un double clic sur « Créer » est un vrai sujet côté back.

### Les codes de statut : quand renvoyer lequel

C'est **le serveur** qui choisit le code. Le front se contente de réagir à `response.ok` / `response.status`.

| Code | Signification | Quand le renvoyer (TaskBoard) |
|---|---|---|
| `200 OK` | Succès avec contenu | `GET` d'une liste ou d'une tâche, `PATCH` réussi |
| `201 Created` | Ressource créée | `POST /api/tasks` réussi, **avec** l'en-tête `Location` |
| `204 No Content` | Succès sans corps | `DELETE` réussi |
| `400 Bad Request` | Requête illisible | JSON malformé (`{title:`) |
| `401 Unauthorized` | Qui êtes-vous ? | Pas de token, ou token invalide/expiré |
| `403 Forbidden` | Je sais qui vous êtes, mais non | Supprimer la tâche d'un autre |
| `404 Not Found` | Ressource ou route inexistante | `GET /api/tasks/99999` |
| `405 Method Not Allowed` | La route existe, pas cette méthode | `PUT /api/tasks/1` |
| `409 Conflict` | Règle métier violée par l'état actuel | Supprimer un projet qui contient encore des tâches |
| `422 Unprocessable Content` | JSON valide mais données invalides | Titre vide, statut inconnu → liste des `violations` |
| `500 Internal Server Error` | Bug côté serveur | Exception non prévue : c'est **toujours** de votre faute, jamais du client |

> Piège classique d'un dev front qui écrit son premier back : tout renvoyer en `200` avec
> `{ "success": false }` dans le corps. Le front ne peut plus utiliser `response.ok`, les outils de monitoring
> ne voient pas les erreurs, et le contrat devient flou. **Le code HTTP porte le résultat.**

`400` ou `422` ? `400` = « je ne peux même pas lire ce que vous m'envoyez » ; `422` = « j'ai lu, mais c'est invalide ».

### Les en-têtes qui comptent côté serveur

| En-tête | Sens | Qui le pose |
|---|---|---|
| `Content-Type: application/json` | Format du corps | Le client sur la requête, le serveur sur la réponse |
| `Location: /api/tasks/6` | Où trouver la ressource créée | Le serveur, sur un `201` |
| `Authorization: Bearer <token>` | Qui fait la requête | Le client ; le serveur le **vérifie** à chaque requête |
| `Access-Control-Allow-Origin` | Quelles origines peuvent lire la réponse (CORS) | Le **serveur** (voir [sécurité](03-securite-cote-serveur.md)) |
| `X-Total-Count: 42` | Nombre total de résultats (pagination du contrat) | Le serveur |

Une vraie réponse de l'API Symfony du projet :

```bash
curl -i -X POST http://localhost:8082/api/tasks \
     -H 'Content-Type: application/json' -d '{"title":"Apprendre le back"}'
```
```http
HTTP/1.1 201 Created
Content-Type: application/json
Location: /api/tasks/6

{"id":6,"title":"Apprendre le back","description":null,"status":"todo","createdAt":"2026-..."}
```

### Le corps JSON

Le corps est du **texte**. Côté front, `JSON.stringify` / `response.json()` ; côté PHP, `json_encode` / `json_decode`.
Le serveur doit traiter ce texte comme **hostile** : il peut être vide, malformé, avoir des champs en trop,
des types faux (`"title": 123`), ou être énorme.

## 1.4 REST et ressources

REST, dans la pratique : on expose des **ressources** (des noms) et on agit dessus avec les **méthodes** HTTP (des verbes).

| ✅ Ressource + méthode | ❌ Verbe dans l'URL |
|---|---|
| `POST /api/tasks` | `POST /api/createTask` |
| `PATCH /api/tasks/3` | `POST /api/tasks/3/update` |
| `DELETE /api/tasks/3` | `GET /api/deleteTask?id=3` (un `GET` qui écrit !) |
| `GET /api/projects/1/tasks` | `GET /api/getTasksOfProject?project=1` |
| `GET /api/tasks?status=todo&page=2` | `POST /api/tasks/search` (pour une simple lecture filtrée) |

Comme pour les routes de Vue Router, une bonne URL se lit comme une adresse : `/api/projects/1/tasks`
= « les tâches du projet 1 ».

## 1.5 *Stateless* : pas de Pinia côté serveur

Dans une app Vue, l'état vit en mémoire tant que l'onglet est ouvert : un store Pinia garde l'utilisateur connecté,
les filtres, le panier. **Un serveur HTTP ne garde rien entre deux requêtes** (et en PHP, c'est littéral :
le processus repart de zéro à chaque fois).

| Côté front | Côté back |
|---|---|
| `const auth = useAuthStore()` garde l'utilisateur | Chaque requête **reprouve** qui elle est (token dans `Authorization`) |
| Le store contient la liste des tâches | La liste est **relue en base** à chaque `GET` |
| Une variable globale partagée entre composants | Une variable globale PHP est **vide** à la requête suivante |
| On peut faire `tasks.value.push()` et c'est « enregistré » | Rien n'est enregistré tant qu'on n'a pas fait d'`INSERT` |

Conséquences :

- **La base de données est la source de vérité.** Tout ce qui doit survivre à la requête va en base (ou dans un cache explicite).
- Deux requêtes peuvent arriver **en même temps** pour deux utilisateurs : on ne peut pas compter sur « l'ordre des clics ».
- C'est ce qui permet de lancer **plusieurs serveurs** derrière un répartiteur de charge : n'importe lequel peut répondre.

## 1.6 Le contrat d'API comme frontière

Le [contrat d'API](../../api-contract.md) est l'accord entre le front et le back. Vous l'avez peut-être déjà subi
côté front (« le back a renommé `created_at` en `createdAt` sans prévenir ») : cette fois, **c'est vous qui le tenez**.

Lecture guidée (ouvrez le contrat à côté) :

1. **Conventions générales** : préfixe `/api`, JSON en `camelCase`, dates ISO 8601, liste = tableau brut.
   → Ce sont des engagements : le front fourni s'appuie dessus.
2. **Format des erreurs** : `status`, `detail`, `violations[].propertyPath` / `violations[].title`.
   → C'est ce qui permet au front d'afficher l'erreur **sous le bon champ** du formulaire.
3. **Objet `Task`** : chaque champ a un type et des règles (`title` 3 à 255 caractères…).
   → Ces règles deviendront votre **validation serveur**.
4. **Tableau des endpoints** : méthode, route, corps, **codes de réponse possibles**.
   → Chaque ligne est un cas de test : `make php-check` / `make sf-check` les vérifient automatiquement.

Si vous trouvez une ambiguïté dans le contrat, on ne l'interprète pas en silence : on la remonte, et le contrat est corrigé.

## 1.7 Outillage : observer ce qui se passe

| Outil | Ce qu'il montre | Exemple |
|---|---|---|
| **Onglet Réseau** du navigateur | Requête et réponse vues par le front | Le `422` et son corps quand un formulaire est invalide |
| **`curl -i`** | La même chose, sans navigateur ni front | `curl -i http://localhost:8082/api/tasks/99999` → `404` |
| **`make logs s=api-php`** | Les erreurs PHP et vos `error_log()` | Une exception non attrapée → `500` |
| **Profiler Symfony** | Tout ce qu'a fait le serveur : route, contrôleur, **requêtes SQL**, temps | http://localhost:8082/_profiler (lien `X-Debug-Token-Link` dans chaque réponse) |
| **Adminer** | Le contenu de la base | http://localhost:8090 |

Quelques commandes à essayer tout de suite :

```bash
curl -i http://localhost:8082/api/tasks                      # 200 + tableau JSON
curl -i http://localhost:8082/api/tasks/99999                # 404
curl -i -X PUT http://localhost:8082/api/tasks/1             # 405 : la route existe, pas cette méthode
curl -i -X POST http://localhost:8082/api/tasks \
     -H 'Content-Type: application/json' -d '{"title":""}'   # 422 + violations
curl -i -X POST http://localhost:8082/api/tasks \
     -H 'Content-Type: application/json' -d '{title:'        # 400 : JSON illisible
```

## À retenir

- Le back **persiste, valide, autorise, orchestre**. Le front présente.
- Le **code HTTP** porte le résultat ; le corps porte le détail.
- `400` = illisible, `422` = invalide, `401` = qui êtes-vous, `403` = non, `409` = conflit avec l'état actuel.
- Le serveur est **sans état** : la base est la source de vérité, chaque requête se suffit à elle-même.
- Le **contrat d'API** est la frontière : vous le tenez, et des scripts le vérifient.

Suite : [SQL et bases relationnelles](02-sql-et-bases-relationnelles.md).
