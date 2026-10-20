# Backlog front — tickets F1 à F11

> Source de vérité fonctionnelle : **[le contrat d'API](../api-contract.md)**. Tout ce qui est affiché ou
> envoyé par le front doit s'y conformer : pas d'invention de champ, pas de route non documentée.
>
> Chaque ticket = une branche + une Pull Request relue (par un dev back de préférence, voir
> [collaboration](#collaboration-avec-les-devs-back)). Lancez `make front-lint` avant chaque PR : il échoue sur
> la moindre erreur **ou warning** (lint ou types), c'est la porte de merge.
>
> **IA** : autorisée sur tous les tickets ([charte](../00-charte-ia.md)). Ce qu'on attend : chaque ligne commitée
> est lue, comprise et testée. En revue et à la démo, vous expliquez **votre** code.

Vue d'ensemble — **F1 → F10 = socle**, **F11 et les points marqués « Bonus » = une fois le socle terminé**.
À votre rythme : un ticket est terminé quand il est compris ; si vous avancez vite, passez au ticket suivant ou aux bonus.

| Ticket | Pendant l'étape back | Sujet | Dépend du back | Notions |
|---|---|---|---|---|
| [F1](#f1--composant-taskitem--badge-de-statut) | 1 — PHP | Composant `TaskItem` + badge | — | props, composition, CSS |
| [F2](#f2--chargement-liste-vide-erreur) | 1 — PHP | Chargement / vide / erreur | — | état, rendu conditionnel |
| [F3](#f3--filtre-par-statut-côté-client) | 1 — PHP | Filtre par statut | — | select contrôlé, valeur dérivée |
| [F4](#f4--module-srcapi--hook-usetasks) | 1 — PHP | `src/api/` + `useTasks` | — | modules, custom hooks, AbortController |
| [F5](#f5--routing--page-détail) | 2 — Symfony | Routing + page détail | — | react-router, `useParams` |
| [F6](#f6--formulaire-de-création) | 2 et 3 — Symfony, Doctrine | Création de tâche | — | formulaires contrôlés, 422 |
| [F7](#f7--changer-le-statut-et-supprimer) | 3 — Doctrine | Statut (PATCH) + suppression | — | immutabilité, navigation |
| [F8](#f8--projets) | 4 — Projet | Projets | **B1** | lifting state, mocks |
| [F9](#f9--authentification) | 4 — Projet | Login / token | **B2** | Context, en-têtes, 401 |
| [F10](#f10--commentaires) | 4 — Projet | Commentaires | **B3** | composition, effets dépendants |
| [F11](#f11--recherche--pagination) | Bonus | Recherche + pagination | **B3** | query string, en-têtes de réponse |

Relisez aussi au moins une PR back pendant les étapes Symfony/Doctrine, et gardez une phase d'intégration avant la démo finale.

---

## Pendant l'étape PHP — le back écrit l'API en PHP pur

L'API PHP pur n'existe pas encore quand vous commencez. Travaillez contre l'**API Symfony**, déjà
complète pour les tâches :

```bash
make use-symfony    # le front appelle http://nginx-symfony
```

Quand les devs back annoncent que `make php-check` est tout vert, basculez avec `make use-php` et
vérifiez que **tout fonctionne à l'identique** : c'est la preuve que les deux API respectent le même contrat.

> ⚠️ L'API PHP pur ne couvre que la **partie 1** (les tâches). Les critères « fonctionne avec `make use-php` »
> (F4, F6) se vérifient **au moment du ticket**, pas en fin de parcours : après F9 (pas de `/api/auth`, donc
> plus de connexion ni d'écriture) et F11 (filtre `status` ignoré), le front n'est plus compatible avec l'API
> PHP pur. C'est normal : il suit désormais le contrat complet, que seule l'API Symfony implémente.

---

### F1 — Composant `TaskItem` + badge de statut


**Objectif** : sortir l'affichage d'une tâche de `TaskList` dans son propre composant, plus lisible.

**Notions** : [composants et props](01-principes.md#6-composants-et-props), [keys](02-particularites.md#8-les-key), [CSS](02-particularites.md#15-css).

**Consignes**

1. Créer `src/components/TaskItem.tsx` qui reçoit une prop `task: Task` (type des props déclaré explicitement).
2. Afficher : titre, description (seulement si non `null`), date de création au format français, badge de statut.
3. Le badge affiche un libellé français (`À faire` / `En cours` / `Terminé`) et une couleur par statut.
4. `TaskList` utilise `<TaskItem>` dans son `map` (la `key` reste dans `TaskList`).

**Critères d'acceptation**

- [ ] Aucune logique d'affichage d'une tâche ne reste dans `TaskList`.
- [ ] Une tâche sans description n'affiche ni texte vide ni « null ».
- [ ] La date s'affiche comme `15/10/2026 09:30`, **dans le fuseau horaire du navigateur** : `toLocaleString`
      convertit l'heure reçue (`2026-10-15T09:30:00+02:00`) en heure locale ; un navigateur réglé en UTC affichera `07:30`.
- [ ] Les libellés de statut sont définis à **un seul endroit** et typés de façon exhaustive (ajouter un statut au type doit provoquer une erreur de compilation tant qu'il n'a pas de libellé).
- [ ] `make front-lint` passe (sans erreur ni warning).

<details>
<summary>Indices</summary>

- Un `Record<TaskStatus, string>` oblige à fournir une entrée pour **chaque** valeur de l'union :

  ```ts
  const STATUS_LABELS: Record<TaskStatus, string> = {
    todo: 'À faire',
    // ...
  }
  ```

- Dates : `new Date(task.createdAt).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })`.
- Couleur : une classe par statut, `` className={`badge badge--${task.status}`} ``, et trois règles CSS.
- Description conditionnelle : `{task.description && <p>...</p>}`.

</details>

---

### F2 — Chargement, liste vide, erreur


**Objectif** : l'utilisateur doit toujours savoir ce qui se passe.

**Notions** : [useState](01-principes.md#7-létat--usestate), [useEffect](01-principes.md#10-les-effets--useeffect), [StrictMode](02-particularites.md#3-strictmode-et-les-effets-exécutés-deux-fois).

**Consignes**

1. Ajouter un état de chargement : afficher « Chargement… » tant que la réponse n'est pas arrivée.
2. Si la liste est vide : « Aucune tâche pour le moment ».
3. En cas d'erreur : message lisible + bouton « Réessayer » qui relance le chargement.

**Critères d'acceptation**

- [ ] On ne voit jamais « Aucune tâche » pendant le chargement.
- [ ] API injoignable → message d'erreur ; on rétablit l'API, on clique « Réessayer » → la liste s'affiche **sans recharger la page**.
      Deux façons de simuler la panne :
      - **sans toucher à la stack** (à préférer, elle est partagée avec les devs back) : DevTools → onglet Réseau →
        clic droit sur la requête `/api/tasks` → « Bloquer l'URL de requête » (ou passer le Réseau en « Hors ligne ») ;
        on débloque, puis « Réessayer » ;
      - **en arrêtant l'API** : `make stop s=api-symfony` (ou `s=api-php` si le front est sur `make use-php`), puis
        `make up` pour la relancer. ⚠️ Cela coupe l'API pour toute l'équipe : prévenez les devs back.
- [ ] Liste vide testable (ex. supprimer les tâches via `curl -X DELETE`, puis `make sf-db-reset` pour revenir).

<details>
<summary>Indices</summary>

- Trois états suffisent : `tasks`, `loading`, `error`. Ou un seul état « union discriminée » si vous voulez aller plus loin :

  ```ts
  type State =
    | { kind: 'loading' }
    | { kind: 'error'; message: string }
    | { kind: 'loaded'; tasks: Task[] }
  ```

- « Réessayer » : le chargement doit pouvoir être relancé. Sortez-le dans une fonction, ou faites dépendre
  l'effet d'un compteur (`[version]`) qu'on incrémente au clic.
- Pour voir l'état de chargement, l'onglet Réseau des DevTools permet de simuler une connexion lente (« Slow 3G »).
- Le linter refuse un `setLoading(true)` écrit **directement** dans l'effet (warning `set-state-in-effect`, donc
  `make front-lint` échoue) : passez `loading` à `true` dans le gestionnaire de « Réessayer » (comme `reload()`
  au [§11 du cours](02-particularites.md#11-custom-hooks-vos-services)), ou voir le [§2](02-particularites.md#2-le-tableau-de-dépendances-de-useeffect).

</details>

---

### F3 — Filtre par statut côté client


**Objectif** : n'afficher que les tâches d'un statut choisi dans une liste déroulante.

**Notions** : [formulaires contrôlés](02-particularites.md#9-formulaires-contrôlés), [valeur dérivée](01-principes.md#8-le-modèle-de-rendu), [remonter l'état](02-particularites.md#10-remonter-létat-et-composer-avec-children).

**Consignes**

1. Un `<select>` « Tous / À faire / En cours / Terminé », contrôlé par un état.
2. La liste affichée est **calculée** à partir de `tasks` et du filtre — **pas** de second état `filteredTasks`, **pas** de nouvel appel à l'API.
3. Afficher le compteur : « 2 tâche(s) sur 5 ».

**Critères d'acceptation**

- [ ] Changer de filtre ne déclenche aucune requête (onglet Réseau).
- [ ] Les options du select réutilisent les libellés de F1.
- [ ] Filtre sans résultat → message dédié (« Aucune tâche avec ce statut »), différent du message de F2.

<details>
<summary>Indices</summary>

- Type du filtre : `TaskStatus | 'all'`.
- `const visibleTasks = filter === 'all' ? tasks : tasks.filter(...)` directement dans le corps du composant.
- Générer les `<option>` à partir d'un tableau **typé** des statuts. Évitez `Object.entries(STATUS_LABELS)` : comme
  `Object.keys`, il renvoie des clés de type `string` (TypeScript ne peut pas garantir qu'un objet n'a pas d'autres
  clés à l'exécution), et vous perdez `TaskStatus`. Exportez plutôt la liste à côté des libellés :

  ```ts
  // une seule assertion, justifiée : les clés viennent d'un Record<TaskStatus, string>
  export const STATUSES = Object.keys(STATUS_LABELS) as TaskStatus[]
  ```

  ```tsx
  {STATUSES.map((status) => (
    <option key={status} value={status}>{STATUS_LABELS[status]}</option>
  ))}
  ```

  Variante sans aucun `as` : partir d'un tableau `as const` et en **déduire** le type
  (`export const STATUSES = ['todo', 'in_progress', 'done'] as const` puis
  `export type TaskStatus = (typeof STATUSES)[number]`) — le `Record<TaskStatus, string>` reste exhaustif.
- En bonus (F11), ce filtre passera côté serveur. Pour l'instant, tout est local.

</details>

---

### F4 — Module `src/api/` + hook `useTasks`


**Objectif** : plus aucun `fetch` dans les composants. Préparer le terrain pour la phase 2.

**Notions** : [appeler l'API proprement](02-particularites.md#12-appeler-lapi-proprement), [custom hooks](02-particularites.md#11-custom-hooks-vos-services), [AbortController](02-particularites.md#4-nettoyage-des-effets-et-abortcontroller).

**Consignes**

1. `src/api/client.ts` : `apiFetch<T>()` et `ApiError` (le cours donne une base, adaptez-la).
2. `src/api/tasks.ts` : `getTasks`, `getTask`, `createTask`, `updateTask`, `deleteTask` — signatures typées d'après le contrat, même si toutes ne sont pas encore utilisées.
3. `src/hooks/useTasks.ts` : renvoie `{ tasks, loading, error, reload }` ; les requêtes sont annulées au démontage.
4. `TaskList` n'utilise plus que `useTasks()`.

**Critères d'acceptation**

- [ ] `grep -r "fetch(" src/components` ne renvoie rien.
- [ ] En dev (StrictMode), la première requête apparaît **annulée** (« canceled ») dans l'onglet Réseau et aucun message d'erreur ne s'affiche.
- [ ] Une erreur `404` / `500` produit une `ApiError` avec le bon `status`.
- [ ] Tout fonctionne avec `make use-php` **et** `make use-symfony` (à vérifier maintenant : voir l'encadré ci-dessus sur F9/F11).

<details>
<summary>Indices</summary>

- `updateTask(id: number, input: Partial<TaskInput>)` : `Partial<T>` rend tous les champs facultatifs, parfait pour un PATCH.
- `deleteTask` renvoie `Promise<void>` → `apiFetch<void>(...)`.
- Pour distinguer une annulation d'une vraie erreur : `controller.signal.aborted`.

</details>

**Fin de l'étape PHP** : démo à l'équipe du front branché sur l'API PHP pur (`make use-php`).

---

## Pendant les étapes Symfony, Doctrine et Projet

Le front reste sur `make use-symfony` jusqu'à la fin.
Dépendance à installer avant F5 : `make npm c="install react-router"`.

Organisation conseillée des fichiers :

```
src/
├── api/          client.ts, tasks.ts, projects.ts, auth.ts, comments.ts
├── auth/         AuthContext.ts, AuthProvider.tsx, useAuth.ts (un fichier par export : voir F9)
├── components/   composants réutilisables (TaskItem, StatusBadge, ...)
├── hooks/        useTasks.ts, ...
├── pages/        un composant par route (TasksPage, TaskDetailPage, LoginPage, ...)
└── types.ts      types du contrat (à enrichir au fil des tickets)
```

---

### F5 — Routing + page détail


**Objectif** : deux pages, la liste (`/`) et le détail d'une tâche (`/tasks/:id`).

**Notions** : [routing](02-particularites.md#14-routing-avec-react-router), [dépendances de useEffect](02-particularites.md#2-le-tableau-de-dépendances-de-useeffect).

**Consignes**

1. `BrowserRouter` dans `main.tsx`, `Routes` dans `App.tsx`, une route `*` « Page introuvable ».
2. Dans la liste, le titre de chaque tâche est un `<Link>` vers son détail.
3. La page détail charge `GET /api/tasks/{id}` et affiche tous les champs. Lien « ← Retour à la liste ».
4. Tâche inexistante (`404`) → message « Cette tâche n'existe pas » (et pas une erreur générique).

**Critères d'acceptation**

- [ ] Touche F5 (rechargement de la page) sur `/tasks/1` → la tâche s'affiche directement.
- [ ] `/tasks/999999` affiche le message 404 dédié ; `/nimportequoi` affiche « Page introuvable ».
- [ ] Changer l'URL de `/tasks/1` à `/tasks/2` (sans recharger) affiche bien la tâche 2.
- [ ] Navigation entre pages sans rechargement complet (pas de `<a href>` interne).

<details>
<summary>Indices</summary>

- `useParams()` renvoie des **strings** : `Number(id)`, et gérer le cas `NaN` (`/tasks/abc`).
- L'effet de chargement dépend de l'id : `useEffect(..., [taskId])`.
- Remettre « Chargement… » quand l'id change : pas de `setLoading(true)` au début de l'effet (refusé par le linter),
  mais l'un des motifs du [§2 du cours](02-particularites.md#2-le-tableau-de-dépendances-de-useeffect)
  (ajuster l'état pendant le rendu, ou mémoriser l'id auquel correspond la tâche chargée).
- `err instanceof ApiError && err.status === 404`.

</details>

---

### F6 — Formulaire de création


**Objectif** : créer une tâche depuis la page liste.

**Notions** : [formulaires contrôlés](02-particularites.md#9-formulaires-contrôlés), [erreurs 422](02-particularites.md#afficher-les-erreurs-422-par-champ), [immutabilité](02-particularites.md#7-limmutabilité).

**Consignes**

1. Composant `NewTaskForm` : champs `title`, `description` (textarea), `status` (select, défaut `todo`).
2. `POST /api/tasks`. Description vide → envoyer `null` (contrat : `string | null`).
3. Succès : la tâche apparaît dans la liste **sans recharger toute la liste**, le formulaire se vide.
4. `422` : afficher chaque message sous le champ concerné (`violations[].propertyPath`).
5. Bouton désactivé pendant l'envoi **et** garde contre la double soumission ([§9 du cours](02-particularites.md#éviter-la-double-soumission)).

**Critères d'acceptation**

- [ ] Titre vide ou de 2 caractères → message sous le champ titre, **renvoyé par l'API** (testez en retirant temporairement tout contrôle côté front).
- [ ] Double-clic rapide sur « Créer » → une seule tâche créée.
- [ ] Les erreurs disparaissent à la soumission suivante.
- [ ] Fonctionne aussi avec `make use-php` (même format de `violations` grâce au contrat) — à vérifier avant F9.

<details>
<summary>Indices</summary>

- Le formulaire ne connaît pas la liste : il reçoit `onCreated: (task: Task) => void` et c'est la page qui ajoute la tâche à son état. Avec `useTasks`, exposez par exemple une fonction `addTask` en plus de `reload`, ou appelez simplement `reload()`.
- Symfony peut renvoyer **plusieurs** violations pour le même champ (vide **et** trop court) : regroupez-les par
  `propertyPath` (`Record<string, string[]>`, voir le [§12 du cours](02-particularites.md#afficher-les-erreurs-422-par-champ)).
  Un simple `Object.fromEntries(...)` ne garderait que la dernière.
- Les messages de violation peuvent être **en anglais** (« This value should not be blank. ») : affichez-les sous
  le champ, ou remplacez-les par vos propres messages français par champ. N'affichez jamais le `detail` d'une erreur :
  il est destiné au développeur.
- `disabled={submitting}` ne suffit pas pour le critère « double-clic » : ajoutez la garde `useRef` du §9.

</details>

---

### F7 — Changer le statut et supprimer


**Objectif** : faire vivre les tâches.

**Notions** : [immutabilité](02-particularites.md#7-limmutabilité), [mises à jour d'état](02-particularites.md#6-mises-à-jour-détat-asynchrones-et-regroupées), `useNavigate`.

**Consignes**

1. Sur la page détail **et** dans la liste : un select ou des boutons pour changer le statut → `PATCH /api/tasks/{id}` avec `{ "status": ... }` uniquement.
2. Sur la page détail : bouton « Supprimer » avec confirmation → `DELETE`, puis retour à la liste.
3. La liste se met à jour à partir de la **réponse du PATCH** (l'API renvoie la tâche à jour), sans recharger toute la liste.

**Critères d'acceptation**

- [ ] Le PATCH n'envoie que le champ modifié (onglet Réseau → Payload).
- [ ] Après suppression, on est sur `/` et la tâche n'apparaît plus.
- [ ] Si le PATCH échoue, l'ancien statut reste affiché et un message apparaît.

<details>
<summary>Indices</summary>

- Remplacer une tâche dans un tableau : `prev.map((t) => (t.id === updated.id ? updated : t))`.
- Confirmation : `window.confirm('Supprimer cette tâche ?')` est suffisant ici.
- Bonus : mise à jour **optimiste** (on change l'affichage avant la réponse, on annule en cas d'erreur).

</details>

---

### Avancer avant la livraison du back (F8 → F10)

Les tickets F8 → F10 dépendent de B1/B2/B3, livrés au fil de l'étape Projet. N'attendez pas : le contrat suffit pour commencer.

1. Ajoutez les nouveaux types dans `types.ts` **exactement** tels que décrits dans le contrat.
2. Écrivez les fonctions de `src/api/xxx.ts` avec une implémentation **mock** temporaire qui respecte les signatures :

   ```ts
   // src/api/projects.ts — version mock, à remplacer par apiFetch dès que B1 est livré
   const MOCK_PROJECTS: Project[] = [
     { id: 1, name: 'Formation PHP', description: null, createdAt: '2026-10-15T09:30:00+02:00', taskCount: 3 },
   ]

   export async function getProjects(): Promise<Project[]> {
     await new Promise((resolve) => setTimeout(resolve, 300)) // simule la latence réseau
     return MOCK_PROJECTS
   }
   ```

3. Construisez l'UI contre ces mocks. À la livraison back, seul le corps des fonctions de `src/api/` change.
4. Testez le cas d'erreur aussi : un mock peut `throw new ApiError(422, 'Validation failed', [...])`.

---

### F8 — Projets


**Dépend de** : B1 (Projets). **Contrat** : [partie 2A](../api-contract.md#partie-2a--projets-dev-back-1).

**Objectif** : afficher les projets, rattacher une tâche à un projet, filtrer les tâches par projet.

**Consignes**

1. Types `Project` et ajout de `project: { id: number; name: string } | null` dans `Task`.
2. Page `/projects` : liste des projets (nom, description, `taskCount`).
3. `TaskItem` et la page détail affichent le nom du projet s'il existe.
4. Formulaire de création de tâche (F6) : select « Projet » (optionnel) → `projectId`.
5. **Bonus** : formulaire de création de projet (`name` unique → afficher la `422`) ; page `/projects/:id` avec ses tâches
   (`GET /api/projects/{id}/tasks`, en réutilisant `TaskItem`) ; suppression d'un projet avec gestion du `409`.

**Critères d'acceptation**

- [ ] Une tâche sans projet s'affiche sans erreur (`project: null`).
- [ ] Un lien « Projets » dans la navigation, actif sur la page projets (`NavLink`).
- [ ] Bonus : le `409` produit un message explicite, pas une erreur générique.

<details>
<summary>Indices</summary>

- La liste des projets sert à deux endroits (page projets + select du formulaire) : un hook `useProjects()`.
- Select optionnel : `<option value="">Aucun projet</option>` et convertir `'' → null`, sinon `Number(value)`.

</details>

---

### F9 — Authentification


**Dépend de** : B2 (Utilisateurs & auth). **Contrat** : [partie 2B](../api-contract.md#partie-2b--utilisateurs--authentification-dev-back-2).

**Objectif** : se connecter, s'inscrire, envoyer le token, réagir aux `401` / `403`.

**Notions** : [Context & token](02-particularites.md#13-context--partager-lauthentification).

**Consignes**

1. Type `User`. `src/api/auth.ts` : `login`, `me` (et `register`, `getUsers` pour les bonus).
2. `AuthContext` : `user`, `token`, `signIn`, `signOut` ; token persisté dans `localStorage`.
3. Page `/login` (`401` → « Email ou mot de passe incorrect »).
4. `apiFetch` ajoute `Authorization: Bearer <token>` quand un token existe.
5. En-tête de l'application : « Connecté en tant que Alice · Se déconnecter » ou lien « Se connecter ».
6. Au chargement de l'application, si un token existe : `GET /api/me` ; en cas de `401`, le supprimer.
7. Les actions d'écriture (créer, modifier, supprimer) sont masquées ou désactivées si on n'est pas connecté.
8. **Bonus** : page `/register` (erreurs `422` par champ) ; afficher `author` et `assignee`, select « Assigné à »
   (`GET /api/users`) → `assigneeId` ; `403` sur la suppression d'une tâche (pas l'auteur) → « Vous ne pouvez supprimer que vos propres tâches ».

**Critères d'acceptation**

- [ ] Après la touche F5 (rechargement de la page), on est toujours connecté.
- [ ] Token expiré ou invalide (modifiez-le à la main dans DevTools → Application → Local Storage) → déconnexion propre, pas d'écran cassé :
      la liste des tâches s'affiche quand même (l'API Symfony répond `401` même sur un `GET` public quand le token
      est invalide, voir le [§13 du cours](02-particularites.md#réagir-à-un-401-et-à-un-403)).
- [ ] Un `403` affiche un message mais **ne déconnecte pas**.
- [ ] Bonus : Bob ne peut pas supprimer une tâche d'Alice (message 403) ; Alice (`ROLE_ADMIN`) peut tout supprimer.
- [ ] Le mot de passe n'est jamais stocké côté front.

Comptes de test : `alice@taskboard.local` / `bob@taskboard.local`, mot de passe `password`.

À partir de ce ticket, le front ne fonctionne plus avec `make use-php` (pas de `/api/auth` dans l'API PHP pur) : restez sur `make use-symfony`.

<details>
<summary>Indices</summary>

- `useAuth()` lève une erreur hors du `Provider` : placez `<AuthProvider>` autour de `<App />` dans `main.tsx` (à l'intérieur du `BrowserRouter` si le provider a besoin de `useNavigate`).
- oxlint avertit (`only-export-components`) quand un fichier exporte un composant **et** autre chose (un hook, un context) :
  comme les warnings font échouer `make front-lint`, séparez `AuthContext.ts`, `AuthProvider.tsx` et `useAuth.ts` (§13 du cours).
- Redirection après login : `navigate('/')`.
- Mock avant B2 : `login()` renvoie `{ token: 'fake', user: {...} }` si le mot de passe vaut `password`, sinon `throw new ApiError(401, 'Unauthorized')`.

</details>

---

### F10 — Commentaires


**Dépend de** : B3 (Commentaires). **Contrat** : [partie 2C](../api-contract.md#partie-2c--commentaires-filtres--pagination-dev-back-3).

**Objectif** : afficher et ajouter des commentaires sur la page détail d'une tâche.

**Consignes**

1. Type `Comment`. Ajout de `commentCount?: number` dans `Task` : ce champ est un **bonus côté back** (B3), il peut
   donc être absent. `TaskItem` affiche « 💬 3 » **seulement si l'API fournit `commentCount`**.
2. Composant `CommentSection` (prop `taskId`) sur la page détail : liste (du plus ancien au plus récent), formulaire d'ajout (`content`, 1 à 1000 caractères, compteur de caractères).
3. Auteur `null` → « Anonyme ».
4. **Bonus** : supprimer son propre commentaire (`DELETE /api/comments/{id}`), `403` sinon.

**Critères d'acceptation**

- [ ] Si l'API fournit `commentCount`, `TaskItem` affiche « 💬 3 » ; sinon, rien (ni « 💬 », ni « undefined »).
- [ ] Le nouveau commentaire apparaît en bas sans recharger la tâche entière.
- [ ] Passer de la tâche 1 à la tâche 2 n'affiche jamais les commentaires de la 1 (pensez à l'annulation, §4 du cours).
- [ ] Bonus : le bouton « Supprimer » n'apparaît que sur ses propres commentaires — le back reste l'arbitre (`403`).

<details>
<summary>Indices</summary>

- `CommentSection` est autonome (son propre état, son propre chargement) : la page détail se contente de `<CommentSection taskId={task.id} />`.
- Autre solution pour repartir d'un état propre à chaque changement de tâche : `<CommentSection key={task.id} ... />`.
- Champ optionnel : `{task.commentCount !== undefined && <span>💬 {task.commentCount}</span>}` (pas `task.commentCount && …`,
  qui afficherait `0`).

</details>

---

### F11 — Recherche + pagination

**Statut** : bonus, une fois le socle terminé

**Dépend de** : B3 (Filtres & pagination). **Contrat** : [partie 2C](../api-contract.md#filtres--pagination-sur-get-apitasks).

**Objectif** : déplacer les filtres côté serveur et paginer la liste. Ticket **bonus**, à faire une fois
F8 → F10 terminés (ou plus tard sur le projet).

**Consignes**

1. `getTasks` accepte `{ status?, projectId?, search?, page?, limit? }` et construit la query string.
2. Lire l'en-tête `X-Total-Count` → `getTasks` renvoie `{ tasks: Task[]; total: number }`.
3. Champ de recherche, filtre statut (remplace le filtre client de F3), filtre projet, pagination « ← Précédent · Page 2 / 5 · Suivant → » (20 par page).
4. Les filtres et la page sont dans l'URL (`/?status=todo&page=2`) : on peut partager le lien, la touche F5 (rechargement de la page) conserve l'état.
5. La recherche ne déclenche pas une requête à chaque frappe (bouton « Rechercher » **ou** délai de 300 ms).
6. Changer un filtre ramène à la page 1.

**Critères d'acceptation**

- [ ] Sans aucun paramètre, la liste est identique à celle de F2.
- [ ] « Précédent » désactivé en page 1, « Suivant » désactivé en dernière page.
- [ ] Le bouton « Précédent » du navigateur restaure les filtres précédents.
- [ ] Taper vite « symfony » ne provoque pas 7 requêtes, et l'affichage final correspond bien à « symfony ».
- [ ] Testé avec `make use-symfony` uniquement : l'API PHP pur ignore ces paramètres (filtre et pagination sans effet).

<details>
<summary>Indices</summary>

- Construire la query string : `new URLSearchParams()` + `params.set('status', status)` (n'ajoutez pas les paramètres vides).
- Lire un en-tête de réponse : `response.headers.get('X-Total-Count')` → `apiFetch` ne renvoie que le corps ; prévoyez une variante qui renvoie aussi la `Response`, ou faites un `fetch` dédié dans `getTasks`.
- Filtres dans l'URL : `useSearchParams()` de react-router devient **la** source de vérité (pas de `useState` en double).
- Délai (debounce) : un `useEffect` qui démarre un `setTimeout` et le **nettoie** (`clearTimeout`) dans sa fonction de retour.
- Nombre de pages : `Math.max(1, Math.ceil(total / limit))`.

</details>

---

## Collaboration avec les devs back

### Le contrat comme source de vérité

- Le front et le back travaillent **en parallèle** à partir de [docs/api-contract.md](../api-contract.md). Personne n'attend personne.
- Le contrat ne se modifie **pas** en douce : toute évolution (champ manquant, format de date…) se discute
  en équipe, puis on met à jour le document **dans la même PR** que le code.
- Les types de `src/types.ts` sont la traduction directe du contrat : en revue, on les compare ligne à ligne.

### Si l'API ne respecte pas le contrat

1. Vérifier d'abord **sans le front** : `curl -i http://localhost:8082/api/...` (ou `:8081` pour le PHP pur), puis l'onglet Réseau.
2. Pour la partie 1, `make php-check` / `make sf-check` vérifient automatiquement le contrat.
3. Si l'écart est confirmé : ouvrir un ticket/issue pour le dev back concerné avec la requête, la réponse reçue et la ligne du contrat.
4. **Ne pas contourner côté front** (convertir un champ mal nommé, deviner un format) : le bug resterait caché pour tous les autres clients de l'API.

### Revue de PR

- Les PR front sont relues par un dev back (il découvre React), les PR back peuvent être relues par le dev front (il vérifie le respect du contrat). Tout le monde apprend des deux côtés.
- Checklist de revue front :
  - [ ] `make front-lint` passe (lint + types, **aucun warning**).
  - [ ] Pas de `fetch` hors de `src/api/`, pas de `any`, `as` justifiés.
  - [ ] Chargement, erreur et liste vide gérés.
  - [ ] Pas de mutation d'état (`push`, affectation directe).
  - [ ] Les `key` sont des ids, pas des index.
  - [ ] Testé avec l'API réelle (et `make use-php` pour ce qui relève des tâches, jusqu'à F8).
  - [ ] La section « Ce que l'IA a produit / ce que j'ai vérifié » est remplie ([charte](../00-charte-ia.md#4-transparence--la-section-pr)).
