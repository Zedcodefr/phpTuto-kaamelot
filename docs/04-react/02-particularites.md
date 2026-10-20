# React — particularités, pièges et idiomes

> Prérequis : [01 — Principes](01-principes.md).
> Ce chapitre rassemble ce qui surprend un dev C# et les façons « idiomatiques » de structurer une petite application.
> Les exemples utilisent les types du projet (`src/types.ts`) et le [contrat d'API](../api-contract.md).

Sommaire

1. [Les règles des hooks](#1-les-règles-des-hooks)
2. [Le tableau de dépendances de `useEffect`](#2-le-tableau-de-dépendances-de-useeffect)
3. [StrictMode et les effets exécutés deux fois](#3-strictmode-et-les-effets-exécutés-deux-fois)
4. [Nettoyage des effets et `AbortController`](#4-nettoyage-des-effets-et-abortcontroller)
5. [Les closures périmées (stale closures)](#5-les-closures-périmées-stale-closures)
6. [Mises à jour d'état asynchrones et regroupées](#6-mises-à-jour-détat-asynchrones-et-regroupées)
7. [L'immutabilité](#7-limmutabilité)
8. [Les `key`](#8-les-key)
9. [Formulaires contrôlés](#9-formulaires-contrôlés)
10. [Remonter l'état et composer avec `children`](#10-remonter-létat-et-composer-avec-children)
11. [Custom hooks, vos services](#11-custom-hooks-vos-services)
12. [Appeler l'API proprement](#12-appeler-lapi-proprement)
13. [Context : partager l'authentification](#13-context--partager-lauthentification)
14. [Routing avec react-router](#14-routing-avec-react-router)
15. [CSS](#15-css)
16. [Outillage navigateur](#16-outillage-navigateur)
17. [React 19 en deux mots](#17-react-19-en-deux-mots)
18. [Quand NE PAS ajouter de bibliothèque](#18-quand-ne-pas-ajouter-de-bibliothèque)

---

## 1. Les règles des hooks

Les fonctions `useXxx` (`useState`, `useEffect`, `useContext`, vos custom hooks…) sont des **hooks**.
React les identifie **par leur ordre d'appel** à chaque rendu. D'où deux règles absolues :

1. N'appeler les hooks **qu'au premier niveau** d'un composant ou d'un custom hook :
   jamais dans un `if`, une boucle, un callback, ni après un `return` anticipé.
2. N'appeler les hooks **que depuis des composants React ou des custom hooks** (pas depuis une fonction utilitaire normale).

```tsx
// ❌ l'ordre des hooks change selon "error"
if (error) {
  return <p>{error}</p>
}
const [filter, setFilter] = useState('all')

// ✅ tous les hooks d'abord, les return conditionnels ensuite
const [filter, setFilter] = useState('all')
if (error) {
  return <p>{error}</p>
}
```

`make front-lint` détecte ces erreurs (règle `react/rules-of-hooks`). Il échoue sur les erreurs **et** sur les
warnings : un avertissement du linter bloque la PR au même titre qu'une erreur de type.

## 2. Le tableau de dépendances de `useEffect`

| Écriture | Exécuté… |
|---|---|
| `useEffect(fn)` | après **chaque** rendu (rarement voulu) |
| `useEffect(fn, [])` | après le **premier** rendu seulement |
| `useEffect(fn, [taskId])` | après le premier rendu **et** à chaque fois que `taskId` change |

Règle : **toute valeur du composant (props, état, fonction locale) utilisée dans l'effet doit être dans
les dépendances**. Sinon l'effet travaille avec une valeur périmée (voir §5).

```tsx
function TaskDetail({ taskId }: { taskId: number }) {
  const [task, setTask] = useState<Task | null>(null)

  useEffect(() => {
    fetch(`/api/tasks/${taskId}`)
      .then((r) => r.json() as Promise<Task>)
      .then(setTask)
  }, [taskId]) // ✅ quand on navigue de /tasks/1 à /tasks/2, on recharge
  // ...
}
```

Vous n'êtes pas seul : `make front-lint` signale une dépendance manquante (règle `exhaustive-deps`) et, comme
les warnings bloquent, l'oubli ne passe pas. Ne faites pas taire la règle par un commentaire `oxlint-disable` :
corrigez l'effet (dépendance primitive, `useEffectEvent` ci-dessous, ou logique déplacée dans un gestionnaire d'événement).

Pièges classiques :

- **Boucle infinie** : un effet sans dépendances qui fait un `setState` → rendu → effet → `setState`…
- Un **objet ou une fonction** créés dans le rendu sont *nouveaux à chaque rendu* : les mettre en dépendance relance l'effet à chaque fois. Dépendez plutôt de valeurs primitives (`taskId`, `status`).
- Un effet **n'est pas un gestionnaire d'événement** : « quand l'utilisateur clique sur Enregistrer, faire un POST » s'écrit dans `onSubmit`, pas dans un `useEffect`. `useEffect` sert à **synchroniser** le composant avec l'extérieur (charger des données, s'abonner…).
- Une **valeur dérivée** ne nécessite pas d'effet : `const visibleTasks = tasks.filter(...)` directement dans le rendu.
- Un `setState` appelé **directement** dans le corps de l'effet (ex. `setLoading(true)` en première ligne) provoque
  un rendu en cascade : le linter le signale (`set-state-in-effect`), donc `make front-lint` échoue. Les `setState`
  appelés plus tard, dans le `.then()` d'une requête, ne sont pas concernés. Solutions : mettre à jour l'état dans le
  gestionnaire d'événement qui provoque le changement (`reload()` au §11), le **dériver**, ou l'ajuster pendant le rendu (ci-dessous).

### Une fonction en dépendance : `useEffectEvent`

Cas fréquent : l'effet doit appeler une fonction reçue en props (un callback `onLoaded`, une fonction `load`
passée à un hook générique…). La mettre en dépendance relance l'effet **à chaque rendu** (le parent recrée la
fonction à chaque fois) ; l'oublier déclenche le linter. Depuis **React 19.2** (le projet est en 19.x),
`useEffectEvent` règle le problème : il renvoie une fonction qui lit toujours les **dernières** props et
qui n'a **pas** à figurer dans les dépendances.

```tsx
import { useEffect, useEffectEvent, useState } from 'react'

function TaskDetail({ taskId, onLoaded }: { taskId: number; onLoaded: (task: Task) => void }) {
  const [task, setTask] = useState<Task | null>(null)
  // lit toujours le onLoaded le plus récent, sans être une dépendance
  const notifyLoaded = useEffectEvent((loaded: Task) => onLoaded(loaded))

  useEffect(() => {
    const controller = new AbortController()
    getTask(taskId, controller.signal)
      .then((loaded) => {
        setTask(loaded)
        notifyLoaded(loaded)
      })
      .catch(() => {}) // gestion d'erreur : voir §4
    return () => controller.abort()
  }, [taskId]) // ✅ l'effet ne dépend que de ce qui doit le relancer
  // ...
}
```

Règles : une fonction `useEffectEvent` s'appelle **uniquement depuis un effet** (ou un callback lancé par un effet),
jamais pendant le rendu ni depuis un `onClick`, et ne se passe pas à un autre composant.
En C#, c'est la différence entre capturer une **variable** dans une lambda (figée au rendu, §5) et lire un
**champ** toujours à jour.

### Ajuster l'état quand une prop change, pendant le rendu

Pour remettre un état à zéro quand une prop change (repasser en « Chargement… » quand `taskId` change), le
réflexe C# serait un effet qui fait `setLoading(true)` : rendu inutile, et le linter le refuse. React propose
de comparer avec la valeur précédente **pendant le rendu** :

```tsx
const [loading, setLoading] = useState(true)
const [prevTaskId, setPrevTaskId] = useState(taskId)

if (taskId !== prevTaskId) {
  // React jette le rendu en cours et recommence immédiatement avec le nouvel état
  setPrevTaskId(taskId)
  setLoading(true)
  setTask(null)
}
```

Ce motif reste une exception (seulement avec une condition, sinon boucle infinie). Souvent, plus simple encore :
mémoriser **l'id auquel correspond la donnée chargée** et en déduire le chargement
(`const loading = loaded?.id !== taskId`), ou recréer le composant avec une `key` (§8).

## 3. StrictMode et les effets exécutés deux fois

`src/main.tsx` enveloppe l'application dans `<StrictMode>`. **En développement uniquement**, React monte
chaque composant, le démonte, puis le remonte : vos effets s'exécutent donc **deux fois**
(vous verrez deux `GET /api/tasks` dans l'onglet Réseau).

Pourquoi ? Pour vous forcer à écrire des effets **correctement nettoyés** : un utilisateur qui navigue
vite produira exactement ce scénario en production. Ne retirez pas `StrictMode` : corrigez l'effet (§4).
En production (`make front-build`), il n'y a qu'une exécution.

## 4. Nettoyage des effets et `AbortController`

Un effet peut renvoyer une **fonction de nettoyage**, appelée avant la ré-exécution de l'effet et au
démontage du composant (≈ `Dispose()`). Pour un `fetch`, on annule la requête avec un `AbortController`
(≈ `CancellationToken`) :

```tsx
useEffect(() => {
  const controller = new AbortController()

  fetch(`/api/tasks/${taskId}`, { signal: controller.signal })
    .then((r) => r.json() as Promise<Task>)
    .then(setTask)
    .catch((e: unknown) => {
      if (controller.signal.aborted) return // annulation volontaire : on ignore
      setError(String(e))
    })

  return () => controller.abort() // nettoyage
}, [taskId])
```

Sans ce nettoyage, si l'utilisateur passe vite de la tâche 1 à la tâche 2, la réponse de la tâche 1 peut
arriver **après** celle de la 2 et l'écraser (« race condition »).

## 5. Les closures périmées (stale closures)

Chaque rendu crée de nouvelles fonctions qui « capturent » les valeurs **de ce rendu-là**
(exactement comme une lambda C# capture une variable).

```tsx
const [count, setCount] = useState(0)

useEffect(() => {
  const id = setInterval(() => {
    setCount(count + 1) // ❌ "count" vaut toujours 0 ici : capturé au 1er rendu
  }, 1000)
  return () => clearInterval(id)
}, []) // dépendance manquante
```

Deux corrections possibles : ajouter `count` aux dépendances, ou — mieux ici — utiliser la forme
fonctionnelle du setter (§6) : `setCount((c) => c + 1)`.

## 6. Mises à jour d'état asynchrones et regroupées

`setX(...)` ne modifie **pas** la variable immédiatement : elle programme un nouveau rendu.
React **regroupe** (batch) plusieurs mises à jour d'un même événement en un seul rendu.

```tsx
function handleClick() {
  setCount(count + 1)
  setCount(count + 1)
  console.log(count) // affiche l'ANCIENNE valeur
  // résultat : +1 seulement, car les deux lignes calculent "ancienne valeur + 1"
}

function handleClickOk() {
  setCount((c) => c + 1) // forme fonctionnelle : part de la valeur la plus récente
  setCount((c) => c + 1) // résultat : +2
}
```

Règle : **quand la nouvelle valeur dépend de l'ancienne, utilisez `setX((prev) => ...)`.**

## 7. L'immutabilité

React détecte un changement en comparant les **références** (`Object.is`). Si vous modifiez un tableau
ou un objet « en place », la référence ne change pas → **pas de re-rendu** (ou un rendu incohérent).

C'est l'inverse des réflexes C# (`list.Add`, `task.Status = ...`). On crée **toujours une nouvelle copie** :

| Intention | ❌ Mutation (C#-style) | ✅ Immuable |
|---|---|---|
| Ajouter | `tasks.push(t)` | `setTasks((prev) => [...prev, t])` |
| Supprimer | `tasks.splice(i, 1)` | `setTasks((prev) => prev.filter((x) => x.id !== id))` |
| Modifier un élément | `tasks[i].status = 'done'` | `setTasks((prev) => prev.map((x) => (x.id === updated.id ? updated : x)))` |
| Modifier un objet | `form.title = 'x'` | `setForm((prev) => ({ ...prev, title: 'x' }))` |
| Trier | `tasks.sort(...)` (trie en place !) | `[...tasks].sort(...)` ou `tasks.toSorted(...)` |

`...` (spread) ≈ copie superficielle ; c'est l'équivalent du `with` des `record` C# :
`task with { Status = "done" }` ⇔ `{ ...task, status: 'done' }`.

## 8. Les `key`

Dans une liste, chaque élément doit avoir une `key` **stable et unique parmi ses frères**.
React s'en sert pour savoir quel élément a été ajouté, supprimé ou déplacé.

```tsx
{tasks.map((task) => <TaskItem key={task.id} task={task} />)}   // ✅ l'id venant de la base
{tasks.map((task, index) => <TaskItem key={index} task={task} />)} // ❌ l'index change si on supprime/trie
```

Avec `key={index}`, supprimer la 1re tâche « décale » l'état interne (un champ en cours de saisie,
une case cochée) sur la mauvaise ligne. La `key` se met sur l'élément renvoyé par `map`, pas à l'intérieur du composant.

Astuce : changer la `key` d'un composant force React à le **recréer** (état remis à zéro) —
pratique pour réinitialiser un formulaire.

## 9. Formulaires contrôlés

Un champ **contrôlé** tire sa valeur de l'état React (`value`) et la met à jour via `onChange`.
L'état React est la seule source de vérité (≈ ViewModel).

```tsx
type TaskForm = { title: string; description: string; status: TaskStatus }

export function NewTaskForm({ onCreated }: { onCreated: (task: Task) => void }) {
  const [form, setForm] = useState<TaskForm>({ title: '', description: '', status: 'todo' })

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault() // sinon le navigateur recharge la page (comportement HTML natif)
    const task = await createTask({ ...form, description: form.description || null })
    onCreated(task)
    setForm({ title: '', description: '', status: 'todo' })
  }

  return (
    <form onSubmit={handleSubmit}>
      <input
        value={form.title}
        onChange={(e) => setForm({ ...form, title: e.target.value })}
      />
      <select
        value={form.status}
        onChange={(e) => setForm({ ...form, status: e.target.value as TaskStatus })}
      >
        <option value="todo">À faire</option>
        <option value="in_progress">En cours</option>
        <option value="done">Terminé</option>
      </select>
      <button type="submit">Créer</button>
    </form>
  )
}
```

Remarques :

- `e.target.value` est **toujours une `string`** (même pour `<input type="number">`) → convertir (`Number(...)`).
- Un `<select>` renvoie une `string` : `as TaskStatus` est acceptable car les `<option>` sont maîtrisées.
- Ne pas mélanger `value` et `defaultValue` ; un champ contrôlé ne doit jamais recevoir `undefined`
  (utilisez `''`).
- Désactivez le bouton pendant l'envoi (`disabled={submitting}`) : c'est un retour visuel, mais **pas** une
  protection suffisante contre les doubles POST (ci-dessous).

### Éviter la double soumission

`disabled={submitting}` ne protège pas la **logique** : si deux soumissions sont traitées avant le rendu suivant
(deux clics dans la même tâche JavaScript, un script, Entrée puis clic…), les deux appels de `handleSubmit`
voient encore `submitting === false` et **deux POST partent** (c'est reproductible).

La raison : **l'état est un snapshot par rendu**. `submitting` est une constante de la fonction composant,
capturée par `handleSubmit` au moment du rendu ; `setSubmitting(true)` ne la modifie pas, il programme un rendu
suivant avec une nouvelle valeur (§6). Il faut une garde **synchrone**, qui change tout de suite : `useRef`.

```tsx
const [submitting, setSubmitting] = useState(false) // pour l'affichage (bouton grisé)
const inFlight = useRef(false) // pour la logique : modifié immédiatement, sans rendu

async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
  e.preventDefault()
  if (inFlight.current) return // 2e soumission avant le rendu suivant : ignorée
  inFlight.current = true
  setSubmitting(true)
  try {
    onCreated(await createTask({ ...form, description: form.description || null }))
    setForm({ title: '', description: '', status: 'todo' })
  } finally {
    inFlight.current = false
    setSubmitting(false)
  }
}

// <button type="submit" disabled={submitting}>Créer</button>
```

| C# (WPF / Blazor) | React |
|---|---|
| champ privé `_isBusy` lu et écrit immédiatement | `useRef` : `inFlight.current`, conservé entre les rendus, **sans** re-rendu |
| propriété `IsBusy` + `INotifyPropertyChanged` | `useState` : valeur figée pour le rendu en cours, mise à jour au rendu suivant |
| `if (_isBusy) return; _isBusy = true;` (thread UI unique, pas de `lock`) | `if (inFlight.current) return; inFlight.current = true` |

Les actions de formulaire de React 19 (§17 : `useActionState`, `useFormStatus`) fournissent l'état « en cours »
sans `useState`, mais elles **mettent les envois en file** au lieu de les ignorer : la garde reste nécessaire si
un second envoi ne doit pas partir.

## 10. Remonter l'état et composer avec `children`

**Lifting state up** : quand deux composants frères ont besoin de la même donnée, l'état monte dans leur
**parent commun**, qui la redistribue en props.

```tsx
export function TasksPage() {
  const [tasks, setTasks] = useState<Task[]>([])
  // NewTaskForm ajoute, TaskList affiche : l'état vit dans le parent
  return (
    <>
      <NewTaskForm onCreated={(task) => setTasks((prev) => [...prev, task])} />
      <TaskList tasks={tasks} />
    </>
  )
}
```

**Composition** : un composant peut recevoir du JSX via la prop spéciale `children` (≈ `RenderFragment ChildContent` de Blazor).

```tsx
import type { ReactNode } from 'react'

export function Card({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="card">
      <h2>{title}</h2>
      {children}
    </section>
  )
}

<Card title="Commentaires">
  <CommentList taskId={task.id} />
</Card>
```

En React, on **compose** des composants ; on ne fait **pas d'héritage** de composants.

## 11. Custom hooks, vos services

Un custom hook est une fonction qui commence par `use` et qui appelle d'autres hooks.
C'est **le** moyen de sortir la logique (chargement, état, erreurs) des composants, comme on extrait un service en C#.
Chaque composant qui l'appelle obtient **sa propre copie** de l'état.

```tsx
// src/hooks/useTasks.ts
import { useEffect, useState } from 'react'
import { getTasks } from '../api/tasks'
import type { Task } from '../types'

export function useTasks() {
  const [tasks, setTasks] = useState<Task[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [version, setVersion] = useState(0) // incrémenter = recharger

  useEffect(() => {
    const controller = new AbortController()

    getTasks(controller.signal)
      .then((data) => {
        setTasks(data)
        setError(null)
      })
      .catch((e: unknown) => {
        if (!controller.signal.aborted) setError(e instanceof Error ? e.message : String(e))
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })

    return () => controller.abort()
  }, [version])

  function reload() {
    setLoading(true)
    setVersion((v) => v + 1)
  }

  return { tasks, loading, error, reload }
}
```

```tsx
// dans un composant : plus aucune logique de chargement
const { tasks, loading, error, reload } = useTasks()
```

| C# | React |
|---|---|
| `ITaskService` injecté | `useTasks()` appelé dans le composant |
| état dans le service (scoped) | état **propre à chaque composant appelant** |
| état partagé (singleton) | **Context** (§13) |

## 12. Appeler l'API proprement

Ne dispersez pas des `fetch` dans les composants. Centralisez dans `src/api/` :

```
src/api/
├── client.ts     apiFetch<T>() : en-têtes, token, erreurs → ApiError
├── tasks.ts      getTasks(), getTask(id), createTask(), updateTask(), deleteTask()
├── projects.ts   ...
└── auth.ts       ...
```

### `client.ts` : un seul endroit pour les en-têtes et les erreurs

```ts
// src/api/client.ts
export type Violation = { propertyPath: string; title: string }

export class ApiError extends Error {
  readonly status: number
  readonly violations: Violation[]

  constructor(status: number, message: string, violations: Violation[] = []) {
    super(message)
    this.status = status
    this.violations = violations
  }
}

export async function apiFetch<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  if (init.body !== undefined) {
    headers.set('Content-Type', 'application/json')
  }

  const response = await fetch(path, { ...init, headers })

  if (!response.ok) {
    // le corps d'erreur suit le format "problem details" du contrat (peut être vide, ex. 401)
    const problem: { detail?: string; violations?: Violation[] } = await response.json().catch(() => ({}))
    throw new ApiError(response.status, problem.detail ?? `HTTP ${response.status}`, problem.violations ?? [])
  }

  if (response.status === 204) {
    return undefined as T // DELETE : pas de corps
  }

  return (await response.json()) as T
}
```

> ⚠️ Le `detail` d'une erreur est destiné au **développeur** (console, ticket au dev back), pas à l'utilisateur :
> en dev, Symfony y met des messages techniques (`"App\Entity\Task" object not found by …`). L'interface choisit
> **son** message à partir du `status` (`404` → « Cette tâche n'existe pas », `409` → « Ce projet contient encore
> des tâches ») ; pour un `422`, elle affiche les violations par champ (ci-dessous).

> 💡 Pourquoi pas `constructor(readonly status: number, ...)` comme en C# 12 (constructeur primaire) ?
> Le `tsconfig` du projet active `erasableSyntaxOnly` : seule la syntaxe TypeScript « effaçable » est
> autorisée. Les *parameter properties* et les `enum` TypeScript génèrent du code JS, ils sont donc refusés.
> On déclare les champs explicitement, et on remplace les `enum` par des unions de littéraux.

### `tasks.ts` : des fonctions typées d'après le contrat

```ts
// src/api/tasks.ts
import type { Task, TaskStatus } from '../types'
import { apiFetch } from './client'

export type TaskInput = { title: string; description?: string | null; status?: TaskStatus }

export function getTasks(signal?: AbortSignal): Promise<Task[]> {
  return apiFetch<Task[]>('/api/tasks', { signal })
}

export function createTask(input: TaskInput): Promise<Task> {
  return apiFetch<Task>('/api/tasks', { method: 'POST', body: JSON.stringify(input) })
}

// updateTask (PATCH, Partial<TaskInput>), deleteTask (DELETE)... sur le même modèle
```

### Afficher les erreurs `422` par champ

Un même champ peut recevoir **plusieurs** violations (titre vide : « vide » **et** « trop court ») : on les
regroupe par `propertyPath` (≈ le `ModelStateDictionary` d'ASP.NET, une liste d'erreurs par clé).

```tsx
type FieldErrors = Record<string, string[]> // { title: ['...', '...'], global: ['...'] }

// [{ propertyPath: 'title', title: 'A' }, { propertyPath: 'title', title: 'B' }] → { title: ['A', 'B'] }
function toFieldErrors(violations: Violation[]): FieldErrors {
  const errors: FieldErrors = {}
  for (const v of violations) {
    errors[v.propertyPath] = [...(errors[v.propertyPath] ?? []), v.title]
  }
  return errors
}

const [errors, setErrors] = useState<FieldErrors>({})

async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
  e.preventDefault()
  setErrors({})
  try {
    onCreated(await createTask(form))
  } catch (err) {
    if (err instanceof ApiError && err.status === 422) {
      setErrors(toFieldErrors(err.violations))
    } else {
      setErrors({ global: ['Erreur inattendue, réessayez.'] })
    }
  }
}

// dans le JSX
{errors.title?.map((message) => <p key={message} className="field-error">{message}</p>)}
```

Les messages des violations viennent du validateur du back et peuvent être **en anglais**
(« This value should not be blank. ») : affichez-les tels quels sous le champ, ou remplacez-les par vos propres
messages français par champ. Ne vous appuyez jamais sur leur texte exact (il peut changer) : seul `propertyPath` est un repère stable.

Le front **ne revalide pas** toutes les règles métier : la validation qui fait foi est celle du back.
Un `required` HTML ou un `maxLength` est un confort, pas une sécurité.

## 13. Context : partager l'authentification

Certaines données sont utiles partout : l'utilisateur connecté, son token. Les passer de props en props
(« prop drilling ») devient vite pénible. Le **Context** rend une valeur accessible à tout le sous-arbre
(≈ un service *scoped* injecté, ou un `CascadingValue` Blazor).

Trois petits fichiers, car `make front-lint` refuse qu'un fichier `.tsx` exporte un composant **et** autre chose
(warning `only-export-components`, qui casse le rechargement à chaud de Vite) :

```tsx
// src/auth/AuthContext.ts
import { createContext } from 'react'
import type { User } from '../types'

export type AuthContextValue = {
  user: User | null
  token: string | null
  signIn: (token: string, user: User) => void
  signOut: () => void
}

export const AuthContext = createContext<AuthContextValue | null>(null)
```

```tsx
// src/auth/AuthProvider.tsx
import { useState, type ReactNode } from 'react'
import type { User } from '../types'
import { AuthContext } from './AuthContext'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(() => localStorage.getItem('token')) // init paresseuse
  const [user, setUser] = useState<User | null>(null)

  function signIn(newToken: string, newUser: User) {
    localStorage.setItem('token', newToken)
    setToken(newToken)
    setUser(newUser)
  }

  function signOut() {
    localStorage.removeItem('token')
    setToken(null)
    setUser(null)
  }

  return <AuthContext value={{ user, token, signIn, signOut }}>{children}</AuthContext>
}
```

```tsx
// src/auth/useAuth.ts
import { useContext } from 'react'
import { AuthContext, type AuthContextValue } from './AuthContext'

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (context === null) {
    throw new Error('useAuth() doit être utilisé sous <AuthProvider>')
  }
  return context
}
```

```tsx
// main.tsx
<AuthProvider>
  <App />
</AuthProvider>

// n'importe où dans l'arbre
const { user, signOut } = useAuth()
```

(React 19 permet d'écrire `<AuthContext value=...>` ; avant : `<AuthContext.Provider value=...>`.)

### Le token `Authorization: Bearer`

Le [contrat](../api-contract.md#partie-2b--utilisateurs--authentification-dev-back-2) demande
`Authorization: Bearer <token>` pour les `POST`/`PATCH`/`DELETE`. Le plus simple : `apiFetch` lit le token
dans `localStorage` et ajoute l'en-tête (un seul endroit !).

```ts
const token = localStorage.getItem('token')
if (token) {
  headers.set('Authorization', `Bearer ${token}`)
}
```

Pièges :

- `localStorage` est lisible par **tout** script de la page : une faille XSS permet de voler le token.
  Acceptable pour cette formation ; en production on préfère souvent un cookie `HttpOnly`. React échappe
  le contenu par défaut — n'utilisez jamais `dangerouslySetInnerHTML` avec des données utilisateur.
- Le token **expire** (24 h) : voir ci-dessous la réaction à un `401`.
- Au rechargement de la page, l'état React repart de zéro : on relit le token depuis `localStorage`
  et on recharge l'utilisateur via `GET /api/me`.
- Masquer un bouton « Supprimer » n'est **pas** une sécurité : c'est le back qui refuse (403).

### Réagir à un `401` et à un `403`

Piège : l'API Symfony vérifie le token dès qu'un en-tête `Authorization` est présent. Un token **invalide ou
expiré** donne donc un `401` **même sur un `GET` public** (le contrat le précise). Sans traitement, un vieux token
resté dans `localStorage` casse toute l'application, liste comprise.

Bonne pratique, centralisée dans `apiFetch` :

1. sur un `401` alors qu'un token a été envoyé : **oublier le token** et **prévenir l'`AuthProvider`** (déconnexion propre) ;
2. si la requête était un `GET` : la **rejouer une fois sans token** (un `GET` n'a pas d'effet de bord) ;
   un `POST`/`PATCH`/`DELETE` n'est **jamais** rejoué automatiquement : on affiche « connectez-vous » ;
3. un `403` (authentifié mais pas autorisé) → message « vous n'avez pas le droit », **pas** de déconnexion.

`apiFetch` n'est pas un composant : il ne peut pas appeler `useAuth()`. On le relie à l'`AuthProvider` par un
petit module d'abonnement (≈ un `event` C#) :

```ts
// src/auth/tokenStorage.ts — module sans React, partagé par apiFetch et AuthProvider
type Listener = () => void
const listeners = new Set<Listener>()

/** S'abonner à « l'API a refusé le token ». Renvoie la fonction de désabonnement (≈ -= sur un event). */
export function onTokenRejected(listener: Listener): () => void {
  listeners.add(listener)
  return () => {
    listeners.delete(listener)
  }
}

export function rejectToken(): void {
  localStorage.removeItem('token')
  listeners.forEach((listener) => listener())
}
```

```ts
// src/api/client.ts — dans apiFetch, à la place du simple fetch
let response = await fetch(path, { ...init, headers })

if (response.status === 401 && token) {
  rejectToken() // token expiré ou invalide : on l'oublie et on prévient l'AuthProvider
  if ((init.method ?? 'GET') === 'GET') {
    headers.delete('Authorization')
    response = await fetch(path, { ...init, headers }) // GET public : rejoué UNE fois, sans token
  }
}
```

```tsx
// src/auth/AuthProvider.tsx — l'état React suit le token effacé par apiFetch
// (importer useEffect depuis 'react' et onTokenRejected depuis './tokenStorage')
useEffect(
  () =>
    onTokenRejected(() => {
      setToken(null)
      setUser(null)
    }),
  [], // abonnement au montage ; la fonction renvoyée (désabonnement) sert de nettoyage
)
```

## 14. Routing avec react-router

Installer : `make npm c="install react-router"`.

```tsx
// main.tsx
import { BrowserRouter } from 'react-router'

<BrowserRouter>
  <App />
</BrowserRouter>
```

```tsx
// App.tsx
import { Link, Route, Routes } from 'react-router'

export default function App() {
  return (
    <>
      <nav>
        <Link to="/">Tâches</Link> · <Link to="/projects">Projets</Link>
      </nav>
      <Routes>
        <Route path="/" element={<TasksPage />} />
        <Route path="/tasks/:id" element={<TaskDetailPage />} />
        <Route path="*" element={<p>Page introuvable</p>} />
      </Routes>
    </>
  )
}
```

```tsx
// pages/TaskDetailPage.tsx
import { useNavigate, useParams } from 'react-router'

export function TaskDetailPage() {
  const { id } = useParams()          // string | undefined, toujours une chaîne !
  const navigate = useNavigate()
  const taskId = Number(id)
  // ... charger la tâche avec useEffect(..., [taskId])
  // après suppression : navigate('/')
}
```

| Blazor | react-router |
|---|---|
| `@page "/tasks/{Id:int}"` | `<Route path="/tasks/:id" element={...} />` |
| `[Parameter] public int Id` | `const { id } = useParams()` (string → `Number(id)`) |
| `<NavLink href="...">` | `<Link to="...">` / `<NavLink to="...">` (classe `active`) |
| `NavigationManager.NavigateTo(...)` | `const navigate = useNavigate(); navigate('/')` |
| query string | `const [params, setParams] = useSearchParams()` |

N'utilisez pas `<a href>` pour la navigation interne : cela recharge toute l'application.
Le serveur de dev Vite renvoie `index.html` pour toutes les URLs, donc la touche F5 (rechargement de la page) sur `/tasks/3` fonctionne.

## 15. CSS

Pas de bibliothèque imposée. Un fichier CSS global (`src/index.css`) suffit, plus éventuellement
un fichier par composant importé dans le composant (`import './TaskItem.css'`) — attention, le CSS
importé reste **global** (pas d'isolation comme le `.razor.css` de Blazor). Pour éviter les collisions,
préfixez les classes par le nom du composant (`.task-item__badge`) ou utilisez les **CSS Modules**
(`TaskItem.module.css`, supportés nativement par Vite).

Classes conditionnelles : `` className={`badge badge--${task.status}`} ``.

## 16. Outillage navigateur

- **React Developer Tools** (extension Chrome / Firefox) : arbre des composants, props et état de chacun,
  surbrillance des composants qui se re-rendent. Indispensable.
- **Onglet Réseau** (F12) : vérifier l'URL, la méthode, les en-têtes (`Authorization`), le corps envoyé, le code de retour et la réponse. Premier réflexe quand « ça ne marche pas » : *est-ce le front ou l'API ?*
- **Console** : erreurs React (souvent très explicites : « Each child in a list should have a unique key… »).
- `make logs s=front` : erreurs de compilation Vite / proxy (ex. API injoignable).
- `make front-lint` : à lancer avant chaque PR ; il échoue sur la moindre erreur **ou warning**.

## 17. React 19 en deux mots

Vous croiserez dans la documentation récente :

- **Actions de formulaire** : `<form action={async (formData) => {...}}>` avec `useActionState` et
  `useFormStatus` pour gérer l'envoi et l'état « en cours » sans `onSubmit` / `preventDefault`
  (les envois successifs sont mis en file, pas ignorés : voir la garde `useRef` du §9).
- **`useEffectEvent`** (React 19.2) : appeler depuis un effet une fonction qui lit les dernières props/état
  sans la mettre dans les dépendances (§2).
- **`use(promise)`** : lire une promesse (ou un context) directement dans le rendu, avec `<Suspense>`.
- `ref` passé comme une prop normale, `<Context value>` sans `.Provider`.

Ces API sont intéressantes (et `useEffectEvent` est à utiliser dès que le cas du §2 se présente), mais ce cours reste sur le socle (`useState`, `useEffect`, formulaires contrôlés)
qui est universel et qu'il faut maîtriser d'abord. Libre à vous d'expérimenter une fois les tickets faits.

## 18. Quand NE PAS ajouter de bibliothèque

Pour une application de cette taille, **n'ajoutez pas** :

| Tentation | Pourquoi pas ici | Quand y penser |
|---|---|---|
| Redux / Zustand / MobX | `useState` + custom hooks + un Context d'auth suffisent | état partagé complexe dans beaucoup d'écrans |
| Axios | `fetch` + `apiFetch` (30 lignes) font le travail | intercepteurs complexes, upload avec progression |
| TanStack Query / SWR | excellent, mais masque ce qu'on veut apprendre (effets, chargement, annulation) | dès qu'il y a beaucoup de données serveur à mettre en cache |
| Bibliothèque de formulaires | 3 petits formulaires | formulaires longs, validation dynamique |
| Bibliothèque de composants UI | quelques classes CSS suffisent | design system d'entreprise |

Seule dépendance prévue dans le backlog : **`react-router`**. Toute autre ajout se discute en équipe
(chaque dépendance est du code à maintenir et à mettre à jour, exactement comme un package NuGet).

---

Suite : [03 — Exercices front](03-exercices-front.md)
