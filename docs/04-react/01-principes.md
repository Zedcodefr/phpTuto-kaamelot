# React — les principes (pour un dev C#)

> Lecture : en testant au fur et à mesure dans `apps/front` (`make up` puis http://localhost:5173).
> Chaque sauvegarde de fichier recharge la page instantanément : expérimentez !

Sommaire

1. [SPA vs rendu serveur](#1-spa-vs-rendu-serveur)
2. [React : une bibliothèque, pas un framework](#2-react--une-bibliothèque-pas-un-framework)
3. [L'écosystème : npm, package.json, Vite](#3-lécosystème--npm-packagejson-vite)
4. [TypeScript vu depuis C#](#4-typescript-vu-depuis-c)
5. [JSX / TSX](#5-jsx--tsx)
6. [Composants et props](#6-composants-et-props)
7. [L'état : `useState`](#7-létat--usestate)
8. [Le modèle de rendu](#8-le-modèle-de-rendu)
9. [Flux de données unidirectionnel et événements](#9-flux-de-données-unidirectionnel-et-événements)
10. [Les effets : `useEffect`](#10-les-effets--useeffect)
11. [Lecture commentée de `TaskList.tsx`](#11-lecture-commentée-de-tasklisttsx)
12. [Le chemin d'une requête `/api/tasks`](#12-le-chemin-dune-requête-apitasks)

---

## 1. SPA vs rendu serveur

| | Rendu serveur (ASP.NET MVC / Razor Pages, Twig en Symfony) | SPA (React, Blazor WebAssembly) |
|---|---|---|
| Qui produit le HTML ? | Le serveur, à chaque requête | Le navigateur, en JavaScript |
| Changement de page | Requête HTTP, page entière rechargée | Le JS remplace une partie du DOM, pas de rechargement |
| Dialogue avec le back | Formulaires HTML, redirections | Appels HTTP JSON (`fetch`) vers une API |
| Ce que sert le serveur web front | Des pages | **Un seul** `index.html` + un bundle JS + CSS |

Dans TaskBoard, les rôles sont donc bien séparés :

```
Navigateur ──(JS React)──► affiche l'UI
          ──(fetch JSON)──► API PHP pur ou Symfony ──► PostgreSQL
```

Le back ne sait rien de React, le front ne sait rien de PHP. **Le seul lien entre les deux est le
[contrat d'API](../api-contract.md).**

## 2. React : une bibliothèque, pas un framework

React ne fait **qu'une chose** : transformer un état en interface et la tenir à jour.
Il n'y a pas de routing, pas de client HTTP, pas d'injection de dépendances, pas de formulaires « magiques » intégrés.

| Besoin | Angular / Blazor (framework) | React |
|---|---|---|
| Composants & rendu | inclus | **React** |
| Routing | inclus | lib : `react-router` |
| Appels HTTP | `HttpClient` | `fetch` natif du navigateur (suffisant) |
| DI | conteneur intégré | aucun → modules ES, hooks, Context |
| Formulaires / validation | inclus | à la main (ou lib) |

Conséquence : on ajoute des bibliothèques **seulement quand le besoin apparaît** (voir
[quand NE PAS ajouter de lib](02-particularites.md#18-quand-ne-pas-ajouter-de-bibliothèque)).

## 3. L'écosystème : npm, package.json, Vite

| .NET | JavaScript | Dans le projet |
|---|---|---|
| NuGet | **npm** (registre + CLI) | `make npm c="install react-router"` |
| `.csproj` (dépendances, scripts de build) | **`package.json`** | `apps/front/package.json` |
| `packages.lock.json` | **`package-lock.json`** (à committer !) | |
| dossier `~/.nuget/packages` | **`node_modules/`** — local au projet, énorme, jamais commité | |
| `dotnet build` / `dotnet watch` | **Vite** : serveur de dev + bundler | `npm run dev` (lancé par Docker) |
| Hot Reload | **HMR** (Hot Module Replacement) : le module modifié est remplacé à chaud, l'état est souvent conservé | |
| Analyseurs Roslyn | **oxlint** (linter) + `tsc` (vérification de types) | `make front-lint` |

`dependencies` = nécessaire au runtime (react, react-dom) ; `devDependencies` = outillage de build
(TypeScript, Vite, oxlint, types). Le navigateur ne voit jamais TypeScript : Vite compile en JavaScript.

Versions du projet : **React 19, TypeScript 6 (mode `strict`), Vite 8**.

Structure de `apps/front` :

```
index.html            la seule page HTML ; contient <div id="root"> et charge src/main.tsx
vite.config.ts        configuration de Vite (dont le proxy /api)
src/
├── main.tsx          point d'entrée (≈ Program.cs) : monte <App /> dans #root
├── App.tsx           composant racine
├── components/
│   └── TaskList.tsx  le seul composant métier fourni
├── types.ts          types TypeScript du contrat d'API
└── index.css         styles globaux
```

## 4. TypeScript vu depuis C#

TypeScript = JavaScript + un système de types **vérifié à la compilation uniquement**.

### 4.1 Correspondances rapides

| C# | TypeScript |
|---|---|
| `int`, `double`, `decimal` | `number` (un seul type numérique) |
| `string`, `bool` | `string`, `boolean` |
| `string?` (nullable) | `string \| null` (et `undefined` existe aussi !) |
| `List<Task>` / `Task[]` | `Task[]` ou `Array<Task>` |
| `Dictionary<string, int>` | `Record<string, number>` |
| `enum Status { Todo, Done }` | `type Status = 'todo' \| 'done'` (union de littéraux) |
| `class TaskDto { ... }` | `type Task = { ... }` ou `interface Task { ... }` |
| `var x = 5;` | `const x = 5` (immuable) / `let x = 5` (réassignable) — **jamais `var`** |
| `Task<T>` + `await` | `Promise<T>` + `await` (même idée) |
| `(x) => x * 2` | `(x) => x * 2` (identique !) |
| `using MonNamespace;` | `import { TaskList } from './components/TaskList'` |
| `public class` | `export function` / `export type` |
| `obj?.Prop ?? "défaut"` | `obj?.prop ?? 'défaut'` (identique) |
| `(Task)obj` | `obj as Task` — **aucune vérification au runtime** |
| `==` | `===` (toujours la version stricte en JS/TS) |

### 4.2 Typage structurel (et pas nominal)

En C#, deux classes avec les mêmes propriétés sont incompatibles (typage **nominal**).
En TypeScript, seule la **forme** compte (typage **structurel**) :

```ts
type Task = { id: number; title: string }
type Ticket = { id: number; title: string }

const t: Task = { id: 1, title: 'A' } // pas de "new", un simple objet littéral suffit
const k: Ticket = t                   // ✅ OK : même forme
```

### 4.3 `type` ou `interface` ?

Les deux décrivent la forme d'un objet. Dans ce projet on utilise **`type`** (plus polyvalent :
unions, alias). Une `interface` TS n'a rien à voir avec une interface C# implémentée par une classe :
c'est juste une description de forme. **On n'écrit quasiment pas de classes en React.**

### 4.4 Unions de littéraux ≈ enums

```ts
export type TaskStatus = 'todo' | 'in_progress' | 'done'

let s: TaskStatus = 'done'   // ✅
s = 'finished'               // ❌ erreur de compilation
```

Avantage : la valeur est directement celle du JSON de l'API, pas de conversion.

### 4.5 Le rétrécissement de type (narrowing)

```ts
function label(description: string | null): string {
  if (description === null) {
    return '(aucune description)'
  }
  return description.toUpperCase() // ici TS sait que c'est une string
}
```

### 4.6 Génériques et inférence

```ts
const [tasks, setTasks] = useState<Task[]>([]) // générique explicite (≈ new List<Task>())
const count = tasks.length                     // inféré : number (≈ var)
```

Règle pratique : typez les **paramètres** et les **frontières** (props, retours d'API), laissez TS inférer le reste.

### 4.7 Les types disparaissent au runtime

```ts
const data = (await response.json()) as Task[]
```

`as Task[]` ne vérifie **rien** : c'est une promesse faite au compilateur. Si l'API renvoie autre chose,
le bug apparaîtra plus loin. D'où l'importance que le back respecte le contrat (et du script `make php-check`).

### 4.8 Modules ES

Chaque fichier est un module ; rien n'est visible à l'extérieur sans `export`.

```ts
// types.ts
export type Task = { /* ... */ }

// TaskList.tsx
import type { Task } from '../types'   // "import type" : import effacé à la compilation
import { useState } from 'react'       // import depuis un paquet npm
```

Chemins relatifs (`./`, `../`) pour vos fichiers, nom nu (`'react'`) pour les paquets de `node_modules`.

## 5. JSX / TSX

JSX = du « HTML » écrit dans le code, compilé en appels de fonctions. C'est l'équivalent de Razor,
mais à l'envers : en Razor on met du C# dans du HTML, en JSX on met du HTML dans du TS.

| Razor | JSX |
|---|---|
| `@task.Title` | `{task.title}` |
| `class="x"` | `className="x"` (`class` est un mot réservé JS) |
| `@if (cond) { <p>…</p> }` | `{cond && <p>…</p>}` |
| `@if … else …` | `{cond ? <A /> : <B />}` |
| `@foreach (var t in tasks) { <li>@t.Title</li> }` | `{tasks.map((t) => <li key={t.id}>{t.title}</li>)}` |
| `onclick="@Handler"` | `onClick={handler}` (camelCase) |
| `style="color:red"` | `style={{ color: 'red' }}` (un objet) |

```tsx
<ul>
  {tasks.length === 0 && <li>Aucune tâche</li>}
  {tasks.map((task) => (
    <li key={task.id} className={task.status === 'done' ? 'done' : ''}>
      {task.title}
    </li>
  ))}
</ul>
```

Règles :

- Entre `{}` : **une expression** (pas de `if`, pas de `for` → on utilise `&&`, `? :`, `.map()`).
- Un composant renvoie **un seul élément racine** (ou un fragment `<>…</>`).
- Toute balise doit être fermée : `<br />`, `<input />`.
- Le contenu entre `{}` est **échappé automatiquement** (pas d'injection XSS).
- ⚠️ `{0 && <p/>}` affiche `0` : utilisez `{tasks.length > 0 && ...}`.

## 6. Composants et props

Un composant est une **fonction** qui reçoit un objet `props` et renvoie du JSX. Son nom commence par une majuscule.

```tsx
type TaskItemProps = {
  task: Task
  onDelete: (id: number) => void
}

export function TaskItem({ task, onDelete }: TaskItemProps) {
  return (
    <li>
      {task.title}
      <button onClick={() => onDelete(task.id)}>Supprimer</button>
    </li>
  )
}

// Utilisation
<TaskItem task={task} onDelete={handleDelete} />
```

| Blazor | React |
|---|---|
| `TaskItem.razor` | `TaskItem.tsx` (une fonction) |
| `[Parameter] public Task Task { get; set; }` | `props.task` (typé par `TaskItemProps`) |
| `[Parameter] public EventCallback<int> OnDelete` | `onDelete: (id: number) => void` (une simple fonction) |
| `<TaskItem Task="@t" OnDelete="Delete" />` | `<TaskItem task={t} onDelete={handleDelete} />` |
| `RenderFragment ChildContent` | `children: ReactNode` |

**Les props sont en lecture seule.** Un composant ne modifie jamais ses props : s'il veut changer quelque
chose, il appelle une fonction reçue en props (`onDelete`) et c'est le parent qui modifie son état.

## 7. L'état : `useState`

```tsx
import { useState } from 'react'

export function Counter() {
  const [count, setCount] = useState(0) // valeur initiale 0

  return <button onClick={() => setCount(count + 1)}>Cliqué {count} fois</button>
}
```

- `useState` renvoie un couple **[valeur actuelle, fonction de mise à jour]** (déstructuration de tableau).
- Appeler `setCount(...)` **enregistre** la nouvelle valeur et **programme un nouveau rendu**.
- On ne modifie **jamais** l'état directement (`count++` ne fait rien de visible).

| Blazor | React |
|---|---|
| `private int count;` | `const [count, setCount] = useState(0)` |
| `count++; StateHasChanged();` (souvent implicite) | `setCount(count + 1)` — le re-rendu est automatique |
| WPF : propriété + `INotifyPropertyChanged` | idem, la notification est intégrée au setter |

## 8. Le modèle de rendu

C'est **le** point qui change le plus de C# :

1. React appelle votre fonction composant → elle renvoie une description de l'UI (le « virtual DOM »).
2. React compare cette description avec la précédente (**réconciliation**) et n'applique au vrai DOM que les différences.
3. Quand un état change (`setX`), React **ré-exécute toute la fonction** du composant (et de ses enfants).

```tsx
export function Demo() {
  console.log('rendu !')           // s'affiche à CHAQUE rendu
  const [n, setN] = useState(0)
  const double = n * 2              // recalculé à chaque rendu : c'est normal et peu coûteux
  return <button onClick={() => setN(n + 1)}>{double}</button>
}
```

Conséquences :

- Les variables locales sont **recréées à chaque rendu**. Seul ce qui est dans `useState` (ou `useRef`) survit.
- Une valeur dérivée (`double`, une liste filtrée…) se **calcule** dans le rendu, on ne la stocke pas dans un état.
- Le rendu doit être **pur** : pas d'appel HTTP, pas de modification de variable extérieure dans le corps
  de la fonction. Les effets de bord vont dans les gestionnaires d'événements ou dans `useEffect`.

Comparaison MVVM : en WPF on modifie le ViewModel et le binding met à jour **une** propriété de la vue.
En React on **redécrit toute la vue** à partir de l'état, et React calcule lui-même le minimum à changer.
« UI = f(état) ».

## 9. Flux de données unidirectionnel et événements

Pas de two-way binding (`@bind` Blazor, `{Binding Mode=TwoWay}` WPF). Les données **descendent** (props),
les événements **remontent** (callbacks). Pour un champ de formulaire : `value` + `onChange`.

```tsx
export function TitleInput() {
  const [title, setTitle] = useState('')

  return (
    <>
      <input value={title} onChange={(e) => setTitle(e.target.value)} />
      <p>{title.length} caractères</p>
    </>
  )
}
```

`@bind="title"` en Blazor ≈ `value={title}` + `onChange={(e) => setTitle(e.target.value)}` en React.

Événements courants : `onClick`, `onChange`, `onSubmit` (formulaire — penser à `e.preventDefault()`
pour empêcher le rechargement de la page), `onKeyDown`.

## 10. Les effets : `useEffect`

Charger des données au premier affichage est un **effet de bord** : il se déclare avec `useEffect`.

```tsx
useEffect(() => {
  // code exécuté APRÈS l'affichage
}, [/* dépendances */])
```

| Blazor | React |
|---|---|
| `OnInitializedAsync()` | `useEffect(() => { ... }, [])` — tableau vide : après le 1er rendu seulement |
| `OnParametersSetAsync()` | `useEffect(() => { ... }, [taskId])` — relancé quand `taskId` change |
| `Dispose()` | la fonction renvoyée par l'effet (nettoyage) |

La fonction passée à `useEffect` **ne peut pas être `async`** directement ; on utilise `.then()` ou
une fonction async interne. Détails et pièges : [02-particularites §2-4](02-particularites.md#2-le-tableau-de-dépendances-de-useeffect).

## 11. Lecture commentée de `TaskList.tsx`

```tsx
import { useEffect, useState } from 'react'
import type { Task } from '../types'                 // type uniquement : effacé à la compilation

export function TaskList() {                         // un composant = une fonction exportée
  const [tasks, setTasks] = useState<Task[]>([])     // état n°1 : la liste, vide au départ
  const [error, setError] = useState<string | null>(null) // état n°2 : message d'erreur éventuel

  useEffect(() => {                                  // après le 1er affichage...
    fetch('/api/tasks')                              // ...appel HTTP (URL relative → proxy Vite)
      .then((response) => {
        if (!response.ok) {                          // fetch ne lève PAS d'erreur sur 404/500 !
          throw new Error(`HTTP ${response.status}`)
        }
        return response.json() as Promise<Task[]>  // "as" : on fait confiance au contrat
      })
      .then(setTasks)                                // ≈ .then((data) => setTasks(data)) → nouveau rendu
      .catch((e: Error) => setError(`Impossible de charger les tâches (${e.message})`))
  }, [])                                             // [] : une seule fois

  if (error) {                                       // rendu conditionnel par "return" anticipé
    return <p className="error">{error}</p>
  }

  return (
    <ul className="task-list">
      {tasks.map((task) => (
        <li key={task.id}>                           {/* key : identité stable de l'élément */}
          <strong>{task.title}</strong> — {task.status}
        </li>
      ))}
    </ul>
  )
}
```

Chronologie :

1. 1er rendu : `tasks = []`, `error = null` → une liste vide s'affiche.
2. L'effet s'exécute, `fetch` part.
3. La réponse arrive → `setTasks(données)` → 2e rendu avec les tâches.

Ce qui manque volontairement (ce sont vos premiers tickets) : un état de chargement, un message
« aucune tâche », un composant par tâche, un affichage lisible du statut… → [exercices F1-F4](03-exercices-front.md).

## 12. Le chemin d'une requête `/api/tasks`

Le front appelle toujours une URL **relative** : `fetch('/api/tasks')`. Pas d'URL d'API en dur.

```
Navigateur                    conteneur "front" (Vite, :5173)        conteneur API
GET http://localhost:5173/api/tasks
        │
        └──► Vite : "commence par /api" ──proxy──► http://api-php:8000/api/tasks      (make use-php)
                                                ou http://nginx-symfony/api/tasks      (make use-symfony)
```

Extrait de `apps/front/vite.config.ts` :

```ts
const apiTarget = process.env.API_TARGET ?? 'http://localhost:8081'

server: {
  proxy: {
    '/api': { target: apiTarget, changeOrigin: true },
  },
},
```

Avantages :

- Pour le navigateur, front et API sont sur la **même origine** (`localhost:5173`) → aucun problème de **CORS**.
- On bascule d'API avec `make use-php` / `make use-symfony` **sans toucher au code React**.
- En production, un reverse proxy (nginx…) jouerait le même rôle.

Pour observer : DevTools du navigateur (F12) → onglet **Réseau** → filtre `Fetch/XHR`.
Pour vérifier l'API seule : `curl http://localhost:8081/api/tasks` (PHP) ou `:8082` (Symfony).

---

Suite : [02 — Particularités & pièges](02-particularites.md)
