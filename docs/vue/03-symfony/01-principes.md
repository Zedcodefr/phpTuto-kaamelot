# Symfony — les principes (pour un dev Vue)

> Vous connaissez un framework : Vue. Vous savez donc déjà ce qu'apporte un framework — des **conventions**,
> des **composants réutilisables**, un **cycle de vie** dans lequel on branche son code. Symfony, c'est la même idée,
> appliquée à un problème différent : transformer **une requête HTTP en une réponse HTTP**.
> Ce document vous donne la carte mentale ; la [visite du projet](03-visite-du-projet.md) vous montre le code.

Sommaire

1. [Un framework back, c'est quoi ?](#1-un-framework-back-cest-quoi-)
2. [Symfony en bref : composants, Flex, bundles, LTS](#2-symfony-en-bref--composants-flex-bundles-lts)
3. [Le cycle de vie d'une requête](#3-le-cycle-de-vie-dune-requête)
4. [L'injection de dépendances, à partir de zéro](#4-linjection-de-dépendances-à-partir-de-zéro)
5. [Le grand tableau d'équivalences Vue / Node ↔ Symfony](#5-le-grand-tableau-déquivalences-vue--node--symfony)
6. [Configuration et environnements](#6-configuration-et-environnements)
7. [Ce qui va vous surprendre](#7-ce-qui-va-vous-surprendre)

---

## 1. Un framework back, c'est quoi ?

| | Vue (framework front) | Symfony (framework back) |
|---|---|---|
| Unité de base | le **composant** : état + template + réactions | le **contrôleur** : une fonction requête → réponse |
| Ce qui « vit » | un **arbre de composants** réactif, tant que l'onglet est ouvert | **rien** : une requête arrive, on la traite, on répond, tout est détruit |
| Ce qui déclenche le code | un clic, un changement d'état, une navigation | une requête HTTP (ou une commande console) |
| Cycle de vie | `setup` → `onMounted` → `onUpdated` → `onUnmounted` | `kernel.request` → contrôleur → `kernel.response` → `kernel.terminate` |
| Ce que le framework vous évite | manipuler le DOM à la main | parser HTTP, router, valider, sérialiser, gérer les erreurs, parler à la base |

En partie 2, vous avez **tout écrit à la main** : routeur, `Request`, `JsonResponse`, exceptions HTTP, PDO, validation.
Symfony fait exactement ce travail — mieux testé, plus complet — et vous laisse écrire uniquement le **métier**.
C'est le même saut qu'entre « manipuler le DOM avec `document.querySelector` » et « écrire un composant Vue ».

---

## 2. Symfony en bref : composants, Flex, bundles, LTS

- **Des composants indépendants** : `HttpFoundation` (Request/Response), `Routing`, `Validator`, `Serializer`,
  `Console`… Chacun est un paquet Composer utilisable seul (Laravel, Drupal ou API Platform en réutilisent beaucoup).
  ≈ l'écosystème `@vue/*` : `@vue/reactivity` s'utilise sans `vue`.
- **Le framework** (`symfony/framework-bundle`) assemble ces composants avec des conventions.
- **Les bundles** sont des plugins qui s'enregistrent dans l'application (DoctrineBundle, SecurityBundle, MonologBundle…).
  ≈ `app.use(router)`, `app.use(pinia)` : on « branche » une brique et elle fournit des services et de la config.
- **Flex** est un plugin Composer : quand vous installez un paquet, il applique une **recette** qui crée la config
  et branche le bundle (≈ `npx nuxi module add`, ou un `vue add` qui modifie votre projet). Voir [les particularités](02-particularites.md#1-flex-et-les-recettes).
- **Versions** : une version mineure tous les six mois ; certaines sont **LTS** (support long). Le projet utilise
  **Symfony 7.4 LTS**. Quand vous cherchez de la doc ou demandez à une IA, précisez la version : beaucoup d'exemples
  en ligne datent de Symfony 3 ou 4 et ne fonctionnent plus.

---

## 3. Le cycle de vie d'une requête

```
navigateur / curl
      │  POST /api/tasks
      ▼
nginx ──FastCGI──▶ PHP-FPM ──▶ public/index.php            le "front controller" (votre index.php de la partie 2)
                                   │
                                   ▼
                              Kernel::handle($request)
                                   │
   ┌───────────────────────────────┼──────────────────────────────────────────────┐
   │ kernel.request                │  routing : quelle route ? sécurité : qui est-ce ?
   │ kernel.controller             │  quel contrôleur ?
   │ kernel.controller_arguments   │  résolution des arguments : JSON → DTO + validation, {id} → entité
   │ ── votre contrôleur ──        │  le métier
   │ kernel.view                   │  (si le contrôleur ne renvoie pas de Response)
   │ kernel.response               │  dernières retouches (en-têtes, profiler)
   │ kernel.exception              │  si une exception a été levée n'importe où : → réponse d'erreur JSON
   └───────────────────────────────┼──────────────────────────────────────────────┘
                                   ▼
                              $response->send()  →  kernel.terminate  →  PHP détruit tout
```

Chaque étape est un **événement** : n'importe quel service peut s'y abonner avec un *listener* et agir
(refuser la requête, modifier la réponse, journaliser…).

| Vous connaissez… | Équivalent Symfony |
|---|---|
| Middlewares Express `app.use((req, res, next) => …)` | listeners de `kernel.request` / `kernel.response` |
| Middleware d'erreur Express `(err, req, res, next)` | listener de `kernel.exception` |
| Guards / interceptors / pipes NestJS | sécurité (`access_control`, voters), listeners, *argument resolvers* (validation) |
| `router.beforeEach((to, from) => …)` de Vue Router | listener de `kernel.request` ou règles de sécurité : on peut bloquer **avant** le contrôleur |
| `onErrorCaptured` / `app.config.errorHandler` | `kernel.exception` |

Différence avec Express : on n'écrit pas la chaîne à la main dans un ordre précis ; chaque listener déclare
l'événement et une **priorité**. `make sf c="debug:event-dispatcher kernel.request"` affiche la chaîne réelle.

---

## 4. L'injection de dépendances, à partir de zéro

### Le problème

Dans votre API PHP pur, `public/index.php` construisait tout à la main :

```php
$pdo = Connection::fromEnvironment();
$controller = new TaskController(new TaskRepository($pdo));
```

Ça marche avec trois classes. Avec trente services qui dépendent les uns des autres (repository → connexion,
contrôleur → repository + logger + validateur…), ce câblage devient énorme et fragile.

### L'idée

Une classe **déclare** ce dont elle a besoin dans son constructeur, et **ne le crée pas elle-même** :

```php
final class TaskController
{
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly EntityManagerInterface $em,
    ) {}
}
```

Un **conteneur** lit ces types, sait construire chaque service, et fournit les bonnes instances.
C'est l'**injection de dépendances** ; le fait que le conteneur devine tout seul à partir des types s'appelle
l'**autowiring**.

### Vous l'avez déjà utilisé en Vue

| Vue | Symfony |
|---|---|
| `provide('api', apiClient)` dans un parent | le conteneur « fournit » chaque service de `src/` |
| `const api = inject('api')` dans un enfant | un paramètre typé dans le constructeur : `ApiClient $api` |
| `useTaskStore()` : une seule instance du store, partagée | un service est **partagé** : une seule instance… **par requête** |
| Pas besoin de savoir qui a créé le store | pas besoin de savoir qui a créé le repository |

NestJS fait exactement la même chose (`constructor(private readonly tasks: TasksService)` + `@Injectable()`),
avec des décorateurs ; Symfony se passe même de décorateur : toute classe de `src/` est un service candidat
(règle `App\: resource: '../src/'` dans `config/services.yaml`).

Pourquoi c'est utile :
- **tester** : on peut donner un faux repository au contrôleur (vous l'avez fait en partie 2 avec `InMemoryTaskRepository`) ;
- **remplacer** une implémentation sans toucher aux classes qui l'utilisent (on dépend d'une **interface**) ;
- **lire** une classe et savoir immédiatement de quoi elle dépend.

---

## 5. Le grand tableau d'équivalences Vue / Node ↔ Symfony

| Besoin | Côté Vue / Node | Côté Symfony | Où le voir |
|---|---|---|---|
| Déclarer une route | `{ path: '/tasks/:id', component: TaskView }` (Vue Router), `app.get('/tasks/:id', …)` (Express) | `#[Route('/api/tasks/{id}', methods: ['GET'])]` sur une méthode | `TaskController` |
| Paramètre de route | `route.params.id`, `req.params.id` | argument de méthode `int $id`… ou directement `Task $task` (chargé depuis la base, 404 sinon) | `show(Task $task)` |
| Le code qui répond | composant de page / handler Express | **contrôleur** (méthode d'une classe) | `src/Controller/` |
| Partager des services | `provide`/`inject`, stores Pinia, `@Injectable()` NestJS | conteneur de services + **autowiring** | constructeurs |
| Annoter du code | décorateurs TS / NestJS (`@Get()`, `@Body()`) | **attributs PHP** `#[Route]`, `#[MapRequestPayload]`, `#[Assert\NotBlank]` | partout |
| Middleware | `app.use()`, middleware Nuxt server | **event listener / subscriber** (`#[AsEventListener]`) | [particularités](02-particularites.md#4-autowiring-en-détail) |
| Lire le corps JSON | `req.body` (après `express.json()`) | `#[MapRequestPayload] CreateTaskInput $input` : JSON → objet typé | `create()` |
| Valider l'entrée | zod / vee-validate **côté formulaire** | contraintes `#[Assert\…]` sur le DTO, validées **côté serveur** → 422 automatique | `CreateTaskInput` |
| Query string | `route.query.status`, `req.query.status` | `#[MapQueryString] TaskFilter $filter` ou `#[MapQueryParameter]` | ticket B3-1 |
| Sérialiser en JSON | `JSON.stringify(obj)`, méthode `toJSON()` | **Serializer** : `$this->json($task)` lit les getters | `list()` |
| Erreur HTTP | `res.status(404).json(…)`, `throw createError({ statusCode: 404 })` (Nuxt) | `throw $this->createNotFoundException()` → JSON *problem details* automatique | [erreurs JSON](02-particularites.md#8-erreurs-json) |
| Variables d'environnement | `.env`, `import.meta.env.VITE_API_URL`, `process.env` | `.env` + `%env(DATABASE_URL)%` dans `config/` | [section 6](#6-configuration-et-environnements) |
| Modes | `vite` (dev) / `vite build` (prod), `import.meta.env.DEV` | `APP_ENV=dev` / `prod` / `test` | `.env` |
| Logs | `console.log`, pino / winston | `LoggerInterface` injecté (Monolog) → `var/log/dev.log` | `make logs s=api-symfony` |
| Tests | Vitest + `@vue/test-utils`, supertest | PHPUnit + `WebTestCase` (requêtes HTTP en mémoire) | `tests/Controller/` |
| CLI du projet | `npm run …`, `vite`, `npx nuxi` | `bin/console` (`make sf c="…"`) + générateurs **MakerBundle** | `make sf c="list"` |
| Paquets | npm + `package.json` / `package-lock.json` | Composer + `composer.json` / `composer.lock` | `make sf-composer` |
| ORM | Prisma, TypeORM, Drizzle | **Doctrine ORM** | étape 4 : [cours Doctrine](../04-doctrine/README.md) |
| Authentification | `passport`, guards NestJS, middleware de session | **SecurityBundle** : firewall, authenticator, voters | ticket B2 |

---

## 6. Configuration et environnements

```
apps/api-symfony/
├── .env              valeurs par défaut (commité) : DATABASE_URL, APP_ENV, APP_SECRET de dev
├── .env.local        surcharges locales (jamais commité)
├── .env.test         surcharges pour les tests
└── config/packages/  un YAML par brique : doctrine.yaml, framework.yaml, security.yaml…
                      avec des sections when@dev / when@test / when@prod
```

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'   # lit la variable d'environnement
```

Deux différences fondamentales avec `import.meta.env.VITE_*` :

1. **Tout ce qui est `VITE_*` finit dans le bundle JavaScript** : n'importe quel visiteur peut le lire.
   C'est pour ça qu'on ne met jamais de secret côté front. Côté Symfony, les variables d'environnement restent
   **sur le serveur** : c'est l'endroit où vivent les mots de passe de base, les clés d'API, `APP_SECRET`.
2. Symfony lit l'environnement **à l'exécution**, pas au build. En production, on préfère de vraies variables
   d'environnement (ou les *secrets* Symfony) aux fichiers `.env`.

`APP_ENV` choisit le mode : `dev` (profiler, erreurs détaillées, recompilation automatique), `prod` (rapide,
erreurs génériques, cache figé), `test` (base `taskboard_test`, utilisé par PHPUnit). C'est l'équivalent de
`import.meta.env.DEV` / `PROD`, mais qui change aussi la configuration chargée (`when@prod:`).

---

## 7. Ce qui va vous surprendre

- **Pas de réactivité.** Rien ne « se met à jour tout seul ». Une requête arrive, vous lisez la base, vous écrivez la base, vous répondez. Point.
- **Pas d'état partagé entre requêtes.** Un service est partagé *pendant* une requête ; à la requête suivante, tout est reconstruit. Un tableau en propriété de service n'est **pas** un store Pinia : il est vide à chaque requête. L'état durable, c'est la **base de données**.
- **Des classes partout.** Contrôleurs, services, DTO, entités : PHP moderne est très orienté objet, typé, avec `final` et `readonly`. Pas de `export function` isolée comme dans un composable.
- **Beaucoup de configuration en YAML.** `config/packages/*.yaml` remplace une partie de ce que vous écririez en code (`app.use(...)`). Lisez toujours le diff après un `composer require` : la recette a écrit de la config pour vous.
- **Un cache compilé.** Symfony « compile » le conteneur et les routes en PHP dans `var/cache/`. En dev c'est automatique ; en cas de comportement fantôme : `make sf-cc` ([détails](02-particularites.md#3-le-cache-compilé)).
- **La validation front ne protège rien.** Vous le savez depuis les [fondamentaux](../01-fondamentaux/03-securite-cote-serveur.md) : n'importe qui peut appeler l'API avec `curl`. La seule validation qui compte, c'est celle du serveur.
- **Les erreurs sont du JSON normalisé.** Un 404 ou un 422 n'est pas un `throw` qui fait planter la page : c'est une réponse HTTP propre, au format du [contrat d'API](../../api-contract.md#format-des-erreurs), que votre front Vue saura afficher.
