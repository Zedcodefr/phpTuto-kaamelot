# Contrat d'API TaskBoard

> **Ce document est la spécification fonctionnelle du projet.** Il est volontairement figé et précis :
> vous n'avez pas à réfléchir au fonctionnel, seulement à la technique.
> Le front React, l'API PHP pur et l'API Symfony respectent **exactement** ce contrat,
> c'est ce qui permet de basculer le front d'une API à l'autre (`make use-php` / `make use-symfony`).

Sommaire

- [Conventions générales](#conventions-générales)
- [Partie 1 — Tâches (PHP pur, puis repris en Symfony)](#partie-1--tâches)
- [Partie 2 — Projets (Dev Back 1)](#partie-2a--projets-dev-back-1)
- [Partie 2 — Utilisateurs & authentification (Dev Back 2)](#partie-2b--utilisateurs--authentification-dev-back-2)
- [Partie 2 — Commentaires, filtres & pagination (Dev Back 3)](#partie-2c--commentaires-filtres--pagination-dev-back-3)

---

## Conventions générales

| Sujet | Règle |
|---|---|
| Préfixe | Toutes les routes commencent par `/api` |
| Format | JSON en entrée et en sortie (`Content-Type: application/json`) |
| Nommage JSON | `camelCase` (`createdAt`, `projectId`) |
| Dates | ISO 8601 avec fuseau : `"2026-10-15T09:30:00+02:00"` |
| Identifiants | Entiers auto-incrémentés |
| Listes | Tableau JSON brut `[ {...}, {...} ]` (la pagination passe par un en-tête, voir partie 2C) |
| Création | `201 Created` + l'objet créé dans le corps + en-tête `Location: /api/<ressource>/<id>` **pour les ressources lisibles à cette adresse** (tâches, projets). Pas de `Location` pour un commentaire ni pour `register` : il n'existe ni `GET /api/comments/{id}` ni `GET /api/users/{id}` |
| Suppression | `204 No Content`, corps vide |

### Format des erreurs

Inspiré de la [RFC 9457 « Problem Details »](https://www.rfc-editor.org/rfc/rfc9457) — c'est le format
que Symfony produit nativement, l'API PHP pur l'imite.

```json
// 404 Not Found
{ "title": "Not Found", "status": 404, "detail": "Task 42 not found" }

// 422 Unprocessable Content (erreur de validation)
{
  "title": "Validation Failed",
  "status": 422,
  "violations": [
    { "propertyPath": "title", "title": "This value should not be blank." }
  ]
}
```

| Code | Quand |
|---|---|
| `400` | JSON illisible |
| `401` | Authentification requise ou token invalide (partie 2B) |
| `403` | Authentifié mais pas autorisé (partie 2B) |
| `404` | Ressource inexistante ou route inconnue |
| `409` | Conflit métier (ex. suppression d'un projet qui contient des tâches) |
| `422` | Données invalides → `violations[]` |

Le front ne dépend que de : `status` HTTP, `detail` (facultatif) et `violations[].propertyPath` / `violations[].title`.
Les autres champs (`title`, `type`, et en mode dev Symfony `class`/`trace`) sont informatifs et peuvent différer entre les deux API.

- **`detail` est destiné au développeur** (logs, onglet Réseau), pas à l'affichage : son texte peut être technique
  (Symfony : `"App\Entity\Task" object not found by "EntityValueResolver".`) et différer d'une API à l'autre. Le front
  affiche **ses propres messages**, choisis d'après le code HTTP.
- Les messages de validation (`violations[].title`) sont ceux de Symfony, **en anglais** (« This value should not be blank. ») ;
  le front peut les afficher tels quels sous le champ ou les remplacer par les siens.

---

## Partie 1 — Tâches

### Objet `Task`

```json
{
  "id": 1,
  "title": "Installer l'environnement",
  "description": "make install puis make up",
  "status": "done",
  "createdAt": "2026-10-15T09:30:00+02:00"
}
```

| Champ | Type | Règles |
|---|---|---|
| `id` | int | Généré par la base |
| `title` | string | **Obligatoire**, 3 à 255 caractères |
| `description` | string \| null | Facultatif, 2000 caractères max. Une chaîne vide `""` est enregistrée comme `null`, en `POST` comme en `PATCH` (en `PATCH`, `""` efface donc la description) |
| `status` | string | `todo` \| `in_progress` \| `done` — défaut `todo` |
| `createdAt` | string (date) | Généré à la création, non modifiable |

### Endpoints

| Méthode | Route | Corps | Réponse |
|---|---|---|---|
| `GET` | `/api/health` | — | `200` `{ "status": "ok" }` |
| `GET` | `/api/tasks` | — | `200` `Task[]` triées par `id` croissant |
| `GET` | `/api/tasks/{id}` | — | `200` `Task` · `404` |
| `POST` | `/api/tasks` | `{ "title", "description"?, "status"? }` | `201` `Task` · `400` · `422` |
| `PATCH` | `/api/tasks/{id}` | n'importe quel sous-ensemble de `{ "title", "description", "status" }` | `200` `Task` · `404` · `422` |
| `DELETE` | `/api/tasks/{id}` | — | `204` · `404` |

Exemples :

```bash
curl http://localhost:8081/api/tasks
curl -X POST http://localhost:8081/api/tasks -H 'Content-Type: application/json' \
     -d '{"title": "Apprendre PHP", "description": "Les bases"}'
curl -X PATCH http://localhost:8081/api/tasks/1 -H 'Content-Type: application/json' -d '{"status": "done"}'
curl -X DELETE http://localhost:8081/api/tasks/1
```

✅ `make php-check` (ou `make sf-check`) vérifie automatiquement toute la partie 1.

---

## Partie 2A — Projets (Dev Back 1)

Une tâche appartient (facultativement) à un projet.

### Objet `Project`

```json
{ "id": 1, "name": "Formation PHP", "description": null, "createdAt": "2026-10-15T09:30:00+02:00", "taskCount": 3 }
```

| Champ | Règles |
|---|---|
| `name` | **Obligatoire**, 2 à 100 caractères, **unique** (sinon `422` sur `name`) |
| `description` | Facultatif, 2000 caractères max |
| `taskCount` | Calculé : nombre de tâches du projet |

### Endpoints

| Méthode | Route | Réponse |
|---|---|---|
| `GET` | `/api/projects` | `200` `Project[]` triés par `name` |
| `GET` | `/api/projects/{id}` | `200` `Project` · `404` |
| `GET` | `/api/projects/{id}/tasks` | `200` `Task[]` du projet · `404` |
| `POST` | `/api/projects` | `201` · `422` |
| `PATCH` | `/api/projects/{id}` | `200` · `404` · `422` |
| `DELETE` | `/api/projects/{id}` | `204` · `404` · `409` si le projet contient encore des tâches |

### Impact sur `Task`

- `Task` gagne un champ `project` : `{ "id": 1, "name": "Formation PHP" }` ou `null`.
- `POST` / `PATCH /api/tasks` acceptent `"projectId": 1` (ou `null` pour détacher).
  Projet inexistant → `422` sur `projectId`.

---

## Partie 2B — Utilisateurs & authentification (Dev Back 2)

### Objet `User` (jamais de mot de passe dans les réponses !)

```json
{ "id": 1, "email": "alice@taskboard.local", "displayName": "Alice" }
```

### Endpoints

| Méthode | Route | Corps | Réponse |
|---|---|---|---|
| `POST` | `/api/auth/register` | `{ "email", "password", "displayName" }` | `201` `User` · `422` (email invalide ou déjà pris, mot de passe < 8 caractères, displayName 2 à 50) |
| `POST` | `/api/auth/login` | `{ "email", "password" }` | `200` `{ "token": "...", "user": User }` · `401` (identifiants incorrects, même réponse que l'email existe ou non) · `422` (champ absent, vide ou qui n'est pas une chaîne) · `400` (JSON illisible) |
| `GET` | `/api/me` | — | `200` `User` · `401` |
| `GET` | `/api/users` | — | `200` `User[]` triés par `displayName` (pour le choix d'un assigné) |

### Règles d'accès

- Le token est envoyé dans l'en-tête `Authorization: Bearer <token>`. Il expire au bout de 24 h.
- Toutes les requêtes **GET** restent publiques… **sans token** : un token **présent mais invalide ou expiré** donne
  `401` même sur une route publique (le token est vérifié avant les règles d'accès). Sur un `401`, le client doit
  oublier ce token (déconnexion) et peut rejouer la requête sans `Authorization`.
- Un `401` a un corps au format d'erreur ci-dessus (`{ "title": "Unauthorized", "status": 401, "detail": ... }`).
- `POST`, `PATCH`, `DELETE` (tâches, projets, commentaires) exigent un token → sinon `401`.
- `DELETE /api/tasks/{id}` : seul l'**auteur** de la tâche ou un `ROLE_ADMIN` → sinon `403`.

### Impact sur `Task`

- `author` : `User` (ou `null` pour les tâches créées avant l'authentification), renseigné automatiquement à la création.
- `assignee` : `User` ou `null`. `POST` / `PATCH` acceptent `"assigneeId": 2` (ou `null`). Utilisateur inexistant → `422` sur `assigneeId`.

Comptes de démonstration (fixtures) : `alice@taskboard.local` (ROLE_ADMIN), `bob@taskboard.local` — mot de passe `password`.

---

## Partie 2C — Commentaires, filtres & pagination (Dev Back 3)

### Objet `Comment`

```json
{ "id": 1, "content": "Je m'en occupe", "createdAt": "2026-10-15T09:30:00+02:00", "author": User | null }
```

| Méthode | Route | Réponse |
|---|---|---|
| `GET` | `/api/tasks/{id}/comments` | `200` `Comment[]` du plus ancien au plus récent · `404` |
| `POST` | `/api/tasks/{id}/comments` | `{ "content" }` (1 à 1000 caractères, pas uniquement des espaces) → `201` + `Comment` (sans `Location`) · `404` · `422` |
| `DELETE` | `/api/comments/{id}` | `204` · `404` · `403` si on n'est pas l'auteur (dès que 2B est livré) |

`Task` gagne `commentCount` (int). **Facultatif** : c'est un bonus côté back ; tant qu'il n'est pas livré, le champ est
absent et le front n'affiche rien (il le traite comme optionnel).

### Filtres & pagination sur `GET /api/tasks`

| Paramètre | Exemple | Effet |
|---|---|---|
| `status` | `?status=todo` | Filtre sur le statut (valeur invalide → `422`) |
| `projectId` | `?projectId=3` | Filtre sur le projet |
| `search` | `?search=php` | Recherche insensible à la casse dans `title` et `description` |
| `page` | `?page=2` | Page (défaut `1`, min `1`) |
| `limit` | `?limit=20` | Taille de page (défaut `50`, max `100`) |

- La réponse reste un **tableau brut** ; le nombre total de résultats est dans l'en-tête `X-Total-Count`.
- Sans paramètre, le comportement est identique à la partie 1 (rien ne casse côté front).

### Suivi automatique

- `Task` gagne `updatedAt` (date ou `null`), mis à jour automatiquement à chaque modification.
- `Task` gagne `completedAt` : renseigné quand le statut passe à `done`, remis à `null` sinon.
