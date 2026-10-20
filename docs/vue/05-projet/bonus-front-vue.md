# Bonus — brancher un front Vue sur votre API

> **Bonus, une fois le socle du backlog terminé.** Vous êtes devs Vue : construisez un petit front Vue 3 + TypeScript
> qui consomme **l'API que vous avez écrite**. L'objectif n'est pas de faire un beau front, c'est de **vivre le contrat
> des deux côtés** : voir, depuis un composant, l'effet de chacun de vos choix back (codes HTTP, format d'erreur,
> en-têtes, CORS, authentification).

Sommaire

- [Mise en place](#mise-en-place)
- [V1 — Liste des tâches](#v1--liste-des-tâches)
- [V2 — Création avec affichage des erreurs 422](#v2--création-avec-affichage-des-erreurs-422)
- [V3 — Changer le statut, supprimer](#v3--changer-le-statut-supprimer)
- [V4 — Connexion et en-tête Authorization (après B2)](#v4--connexion-et-en-tête-authorization-après-b2)
- [V5 — Filtres et pagination via X-Total-Count (après B3)](#v5--filtres-et-pagination-via-x-total-count-après-b3)
- [V6 — Sans proxy : CORS côté Symfony](#v6--sans-proxy--cors-côté-symfony)
- [Ce qu'on doit en retenir](#ce-quon-doit-en-retenir)

Règles :
- le **[contrat d'API](../../api-contract.md)** reste la source de vérité : si votre front a besoin d'un endpoint qui n'y est pas, on en parle, on ne le bricole pas ;
- chacun sur sa branche `bonus/front-vue-<prénom>` ; le dossier `apps/front-vue` n'est **pas** mergé dans `main` sans accord du formateur ;
- l'IA est autorisée comme partout ([charte IA](../../00-charte-ia.md)) — et vous êtes en terrain connu : soyez exigeants sur le code qu'elle vous propose.

---

## Mise en place

### 1. Créer le projet (sans installer Node sur votre machine)

Depuis la racine du dépôt :

```bash
docker run --rm -u $(id -u):$(id -g) -e HOME=/tmp -v "$PWD/apps:/apps" -w /apps node:24-alpine \
  npm create vite@latest front-vue -- --template vue-ts --no-interactive

docker run --rm -u $(id -u):$(id -g) -e HOME=/tmp -v "$PWD/apps/front-vue:/app" -w /app node:24-alpine npm install
```

Si Node est installé sur votre machine, `cd apps/front-vue && npm install` fonctionne aussi.

### 2. Le proxy `/api` (pas de CORS, comme le front fourni)

Le front appelle toujours `/api/...` et Vite redirige vers l'API. Dans `apps/front-vue/vite.config.ts` :

```ts
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// API Symfony depuis l'hôte : http://localhost:8082 (API PHP pur : http://localhost:8081)
const apiTarget = process.env.API_TARGET ?? 'http://localhost:8082'

export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5175,
    proxy: {
      '/api': { target: apiTarget, changeOrigin: true },
    },
  },
})
```

### 3. Lancer le serveur de dev

- **Avec Node en local** : `cd apps/front-vue && npm run dev` → http://localhost:5175.
- **Avec Docker, via un service compose** que vous ajoutez vous-même dans un fichier `compose.override.yaml` à la racine
  (Docker Compose le lit automatiquement ; ne le commitez pas si l'équipe n'en veut pas) :

  ```yaml
  services:
    front-vue:
      image: node:24-alpine
      user: "${HOST_UID:-1000}:${HOST_GID:-1000}"
      working_dir: /app
      command: sh -c "npm run dev -- --host 0.0.0.0"
      ports:
        - "5175:5175"
      environment:
        HOME: /tmp
        API_TARGET: http://nginx-symfony   # nom du service dans le réseau Docker, pas localhost
      volumes:
        - ./apps/front-vue:/app
  ```

  puis `docker compose up -d front-vue` (ou `make up`). Dans un conteneur, `localhost` désigne le conteneur
  lui-même : c'est pour ça que la cible devient `http://nginx-symfony`.

**Acceptation** : http://localhost:5175 affiche la page de démo Vite, et `fetch('/api/health')` dans la console du
navigateur renvoie `{"status":"ok"}`.

---

## V1 — Liste des tâches

**Objectif** : afficher `GET /api/tasks` : titre, statut, date de création.
**Côté back, observez** : la forme exacte du JSON produit par le Serializer, le format des dates (RFC 3339), le tri.

Consignes :
1. `src/types.ts` : types `Task` et `TaskStatus` recopiés **depuis le contrat** (pas depuis une réponse observée).
2. `src/api/client.ts` : une fonction `request<T>(path, options)` qui fait le `fetch`, lève une erreur typée si `!response.ok`, et ne lit pas de corps sur un `204`.
3. Un composable `useTasks()` (état `tasks`, `loading`, `error`, fonction `reload()`), et un composant `TaskList.vue`.

**Acceptation** : la liste s'affiche avec `make use-symfony` **et** en pointant le proxy sur l'API PHP pur (`API_TARGET=http://localhost:8081`) — si les deux API respectent le contrat, rien ne change.

<details>
<summary>Indice : un client qui respecte le format d'erreur du contrat</summary>

```ts
export type Violation = { propertyPath: string; title: string }

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly detail?: string,
    public readonly violations: Violation[] = [],
  ) {
    super(detail ?? `HTTP ${status}`)
  }
}

export async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(path, {
    ...init,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...init.headers },
  })
  if (response.status === 204) return undefined as T
  const body = await response.json().catch(() => null)
  if (!response.ok) throw new ApiError(response.status, body?.detail, body?.violations ?? [])
  return body as T
}
```
</details>

---

## V2 — Création avec affichage des erreurs 422

**Objectif** : formulaire titre + description + statut ; `POST /api/tasks` ; les violations 422 s'affichent **sous le champ concerné**.
**Côté back, observez** : `#[MapRequestPayload]` + contraintes du DTO → `violations[].propertyPath`. Désactivez
volontairement votre validation côté formulaire : la réponse du serveur doit suffire à guider l'utilisateur.

**Acceptation** :
- titre vide → message sous le champ titre, venu du serveur ;
- statut inconnu (forcez-le dans les DevTools) → message sous le champ statut ;
- succès → la liste se met à jour, le formulaire se vide ; l'en-tête `Location` est visible dans l'onglet Réseau.

<details>
<summary>Indice : des erreurs par champ</summary>

```ts
const fieldErrors = ref<Record<string, string[]>>({})

try {
  await request<Task>('/api/tasks', { method: 'POST', body: JSON.stringify(form) })
} catch (e) {
  if (e instanceof ApiError && e.status === 422) {
    fieldErrors.value = {}
    for (const v of e.violations) {
      ;(fieldErrors.value[v.propertyPath] ??= []).push(v.title)
    }
  } else {
    throw e
  }
}
```
</details>

---

## V3 — Changer le statut, supprimer

**Objectif** : un `<select>` de statut par tâche (`PATCH` partiel) et un bouton supprimer (`DELETE`, confirmation).
**Côté back, observez** : le PATCH ne renvoie que la tâche modifiée ; le DELETE renvoie un `204` **sans corps** ;
une tâche supprimée entre-temps (dans un autre onglet) renvoie `404`.

**Acceptation** : le statut change sans recharger toute la liste ; supprimer deux fois la même tâche (deux onglets)
affiche une erreur compréhensible au lieu d'une exception dans la console.

<details>
<summary>Indice : mettre à jour un seul élément de la liste</summary>

Remplacez l'élément par la tâche renvoyée par le serveur (`tasks.value = tasks.value.map(t => t.id === updated.id ? updated : t)`)
plutôt que de modifier l'objet localement : c'est le serveur qui fait foi (règles métier, `updatedAt` du ticket B3-3…).
</details>

---

## V4 — Connexion et en-tête Authorization (après B2)

**Dépend de B2-3 (login) et B2-4b (écritures protégées).**

**Objectif** : écran de connexion (`POST /api/auth/login`), token conservé, envoyé en `Authorization: Bearer <token>`
sur toutes les requêtes ; un `401` renvoie vers l'écran de connexion ; un `403` affiche « action non autorisée ».
**Côté back, observez** : le firewall `stateless` (aucun cookie), l'authenticator `access_token`, la différence 401 / 403.

**Acceptation** :
- sans connexion, la liste reste visible (les `GET` sont publics) mais créer une tâche renvoie vers la connexion ;
- connecté en `bob@taskboard.local`, la création fonctionne ; `GET /api/me` affiche « Bob » dans l'en-tête de page ;
- token supprimé ou expiré → retour propre à l'écran de connexion. Attention : l'API Symfony répond `401` **même
  sur un `GET` public** quand le token envoyé est invalide ; sur un `401`, oubliez le token, mettez le store à jour
  et rejouez le `GET` une fois sans token (la liste reste visible). Un `403` ne déconnecte pas.

<details>
<summary>Indice : un store Pinia pour l'authentification</summary>

Un store `useAuthStore` qui contient `token` et `user`, persiste le token dans `localStorage`, et que `request()` lit
pour ajouter l'en-tête. Le guard `router.beforeEach` peut rediriger vers `/login` pour l'ergonomie — mais rappelez-vous
que **la protection réelle est côté Symfony** (`access_control`) : votre guard n'est qu'un confort d'affichage.
</details>

---

## V5 — Filtres et pagination via X-Total-Count (après B3)

**Dépend de B3-1.**

**Objectif** : filtre par statut, champ de recherche, pagination (`page`, `limit`) ; le nombre total de pages est
calculé depuis l'en-tête `X-Total-Count`.
**Côté back, observez** : `#[MapQueryString]` + DTO `TaskFilter` ; un `limit=500` renvoie `422` ; le Paginator Doctrine
compte le total.

**Acceptation** : changer de page ou de filtre met à jour l'URL du front (`?status=todo&page=2`, via Vue Router) et
la requête API ; un rechargement de page conserve l'état ; « page 3 sur 5 » est juste.

<details>
<summary>Indice : lire un en-tête de réponse</summary>

`request()` renvoie le corps ; pour la pagination, il vous faut aussi `response.headers.get('X-Total-Count')`.
Prévoyez une variante qui renvoie `{ data, total }`. Avec le proxy Vite, l'en-tête est lisible directement ;
sans proxy (V6), il faudra que le back l'**expose** explicitement.
</details>

---

## V6 — Sans proxy : CORS côté Symfony

**Objectif** : supprimer le proxy et appeler `http://localhost:8082/api/...` directement depuis http://localhost:5175,
puis rendre ça possible **côté serveur**.
**Côté back** : c'est le ticket transverse T-2 du [backlog](backlog-back.md#t-2-bonus--cors).

Étapes :
1. Retirez le proxy, faites pointer `request()` sur `http://localhost:8082`. Observez l'erreur CORS dans la console : le navigateur bloque la **réponse** (la requête, elle, est bien arrivée au serveur — vérifiez dans le profiler).
2. Côté Symfony : `make sf-composer c="require nelmio/cors-bundle"`, lisez la recette (`config/packages/nelmio_cors.yaml`).
3. Autorisez l'origine `http://localhost:5175`, les méthodes `GET POST PATCH DELETE`, l'en-tête `Authorization`, et **exposez** `X-Total-Count` et `Location` (`expose_headers`).

**Acceptation** : tout fonctionne sans proxy ; vous savez expliquer pourquoi `curl` n'a jamais eu de problème de CORS
alors que le navigateur, si.

---

## Ce qu'on doit en retenir

À la fin du bonus, vous savez expliquer, avec un exemple tiré de votre propre code des deux côtés :

- pourquoi un `422` avec `violations[].propertyPath` est plus utile au front qu'un `400` avec un message global ;
- pourquoi `204` n'a pas de corps et ce que ça implique pour le client ;
- pourquoi la validation et les droits se décident côté serveur, et ce que valent un `v-if` ou un guard de route ;
- ce qu'est le CORS, qui le décide (le navigateur) et qui le configure (le serveur) ;
- comment un en-tête (`X-Total-Count`, `Location`, `Authorization`) circule entre Symfony et un composant Vue.
