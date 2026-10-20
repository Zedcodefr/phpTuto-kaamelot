# Symfony — les principes (pour dev ASP.NET Core)

> TL;DR : Symfony, c'est ce que vous avez écrit à la main en partie 1 (front controller, routeur, Request/Response,
> gestion des erreurs, câblage des objets, accès BDD, validation, JSON)… industrialisé, configurable et testé
> depuis 2005. Votre réflexe ASP.NET Core est presque toujours le bon : il faut juste apprendre les noms.

Sommaire

1. [Symfony, c'est quoi ?](#1-symfony-cest-quoi-)
2. [Le cycle de vie d'une requête](#2-le-cycle-de-vie-dune-requête)
3. [Le grand tableau d'équivalences ASP.NET Core ↔ Symfony](#3-le-grand-tableau-déquivalences)
4. [Zoom sur les points clés](#4-zoom-sur-les-points-clés)
5. [Ce qui va vous surprendre](#5-ce-qui-va-vous-surprendre)

---

## 1. Symfony, c'est quoi ?

### Deux choses sous un même nom

| | Description | Analogie .NET |
|---|---|---|
| **Les composants** | ~50 bibliothèques PHP indépendantes : `HttpFoundation`, `Routing`, `DependencyInjection`, `Console`, `Validator`, `Serializer`, `EventDispatcher`, `Messenger`… Utilisables seules, dans n'importe quel projet PHP. | Les packages `Microsoft.Extensions.*` (DI, Logging, Configuration) utilisables hors ASP.NET |
| **Le framework** (`symfony/framework-bundle`) | La colle qui assemble les composants en une application web : Kernel, configuration, conteneur, conventions. | ASP.NET Core lui-même |

Les composants Symfony sont **partout** dans l'écosystème PHP :

- **Laravel** est construit sur `HttpFoundation`, `Console`, `Routing`, `Mailer`… de Symfony ;
- **Drupal**, **PrestaShop**, **phpBB**, **Composer** lui-même utilisent des composants Symfony ;
- **API Platform** est un framework d'API REST/GraphQL bâti **sur** Symfony (≈ un « OData + Swagger » génératif). Nous ne l'utilisons pas exprès : il génère tout, vous n'apprendriez rien.

### Flex et les bundles

- Un **bundle** ≈ un package NuGet qui s'enregistre dans le framework (services, config, routes). Liste dans `config/bundles.php`.
- **Symfony Flex** est un plugin Composer : quand vous faites `composer require xxx`, il applique une **recette** (« recipe ») qui crée les fichiers de config, ajoute les variables dans `.env`, enregistre le bundle. Pensez « `dotnet add package` qui modifierait aussi votre `Program.cs` et votre `appsettings.json` ». Détails dans [02-particularites](02-particularites.md#1-flex-et-les-recettes).
- Les *packs* (`symfony/orm-pack`, `symfony/serializer-pack`…) sont des méta-paquets qui installent un ensemble cohérent.

### Versions

| Version | Sortie | Support | Note |
|---|---|---|---|
| 6.4 LTS | nov. 2023 | sécurité jusqu'à nov. 2027 | |
| **7.4 LTS** | nov. 2025 | bugs jusqu'à nov. 2028, sécurité jusqu'à nov. 2029 | **← notre projet** |
| 8.0 / 8.1… | nov. 2025 / mai 2026 | 8 mois chacune | même code que 7.4, sans les dépréciations |

Rythme fixe : une mineure tous les 6 mois, une majeure tous les 2 ans, la dernière mineure de chaque majeure est LTS
(≈ .NET LTS/STS, en plus prévisible). **La règle d'or des montées de version : corriger toutes les dépréciations
signalées en x.4 → le passage en (x+1).0 est trivial.**

---

## 2. Le cycle de vie d'une requête

### Rappel partie 1

```
php -S → public/index.php → Request::fromGlobals() → Router->dispatch() → TaskController → JsonResponse->send()
                                         └── try/catch HttpException → JSON d'erreur
```

### Avec Symfony

```
nginx ──FastCGI──▶ PHP-FPM
                      │
                public/index.php            (délègue au composant Runtime)
                      │
                Runtime → new Kernel('dev', debug: true)
                      │      (charge le conteneur compilé dans var/cache/dev/)
                      ▼
                HttpKernel::handle(Request)
                      │
   ┌──────────────────┼────────────────────────────────────────────────────────────┐
   │ 1. kernel.request          RouterListener : trouve la route → _controller     │ ← peut renvoyer
   │                            (+ locale, firewall de sécurité...)                │   une réponse direct
   │ 2. kernel.controller       on connaît le contrôleur (≈ ActionFilter.OnExecuting)│
   │ 3. kernel.controller_arguments                                                │
   │       argument resolvers : Request, Task (depuis {id}), #[MapRequestPayload]…  │
   │ 4. appel du contrôleur  →  Response                                           │
   │ 5. kernel.view             seulement si le contrôleur ne renvoie PAS une Response│
   │ 6. kernel.response         modifier la réponse (en-têtes, CORS, profiler…)     │
   │                                                                               │
   │  en cas d'exception, à n'importe quelle étape : kernel.exception              │
   │       → ErrorListener → réponse d'erreur (JSON "problem details" chez nous)    │
   └──────────────────┬────────────────────────────────────────────────────────────┘
                      ▼
                $response->send()
                      ▼
                kernel.terminate           après envoi au client (≈ travail "fire & forget")
```

Vous pouvez voir les listeners de chaque étape, triés par priorité :

```bash
make sf c="debug:event-dispatcher kernel.request"
make sf c="debug:event-dispatcher kernel.controller_arguments"
```

Extrait réel de notre projet :

```
kernel.request
  #1  DebugHandlersListener::configure()           2048
  #2  ValidateRequestListener::onKernelRequest()   256
  #6  RouterListener::onKernelRequest()            32     ← le routing est un simple listener !
kernel.controller_arguments
  #3  RequestPayloadValueResolver::onKernelControllerArguments()   ← la validation de #[MapRequestPayload]
```

### Comparaison avec le pipeline ASP.NET Core

| ASP.NET Core | Symfony |
|---|---|
| Pipeline de **middlewares** emboîtés (`app.UseXxx()`), l'ordre d'enregistrement = l'ordre d'exécution | Une séquence fixe d'**événements** ; on s'y abonne avec une **priorité** (plus grand = plus tôt) |
| Un middleware appelle `next()` pour continuer | Un listener ne « continue » rien : il agit sur l'événement. Il peut **court-circuiter** en posant une réponse (`$event->setResponse()`) sur `kernel.request` |
| `UseRouting()` / `UseEndpoints()` | `RouterListener` sur `kernel.request` |
| `UseExceptionHandler()`, `ProblemDetails` | `ErrorListener` sur `kernel.exception` + `ProblemNormalizer` |
| `UseAuthentication()` / `UseAuthorization()` | `FirewallListener` sur `kernel.request` (SecurityBundle) |
| Action filters (`OnActionExecuting`) | listeners `kernel.controller` / `kernel.controller_arguments` |
| Result filters | `kernel.view` / `kernel.response` |
| `Response.OnCompleted()` | `kernel.terminate` |

**Le point fondamental** : Kestrel est un process qui tourne en continu. PHP-FPM, lui, exécute ce cycle complet
**pour chaque requête** puis jette tout (modèle *share nothing*, vu en partie 1). Symfony est rapide malgré ça
parce que tout ce qui peut être calculé à l'avance (conteneur, routes, mapping Doctrine) est **compilé en PHP
dans `var/cache/`** et mis en mémoire par OPcache.

---

## 3. Le grand tableau d'équivalences

| Sujet | ASP.NET Core | Symfony 7.4 | Dans le projet |
|---|---|---|---|
| **Point d'entrée** | `Program.cs`, `WebApplicationBuilder` | `public/index.php` (quelques lignes) + `src/Kernel.php` (vide !) + tout le dossier `config/` | |
| **Configuration de l'app** | `builder.Services.AddXxx()` en C# | YAML (ou PHP) dans `config/packages/*.yaml`, un fichier par bundle | `config/packages/doctrine.yaml` |
| **Injection de dépendances** | `IServiceCollection`, enregistrement explicite | Conteneur de services, **autowiring + autoconfigure** : toute classe de `src/` est un service, sans l'enregistrer | `config/services.yaml` |
| **Durée de vie** | Singleton / Scoped / Transient | Services **partagés** par défaut (une instance par conteneur), `shared: false` ≈ Transient. Et comme un conteneur = une requête en FPM, « singleton » ≈ « scoped » | |
| **Contrôleur** | `ControllerBase` + `[ApiController]` | `AbstractController` (facultatif : n'importe quelle classe avec `#[Route]` marche) | `TaskController` |
| **Routing** | `[Route("api/tasks")]`, `[HttpGet("{id}")]` | `#[Route('/api/tasks')]`, `#[Route('/{id}', methods: ['GET'])]` | |
| **Contrainte de route** | `{id:int}` | `requirements: ['id' => '\d+']` ou `'/{id<\d+>}'` | |
| **Lister les routes** | — (Swagger) | `make sf-routes` | |
| **Middleware** | `app.Use(...)`, `IMiddleware` | Event listener / subscriber sur `kernel.request` / `kernel.response` (`#[AsEventListener]`) | |
| **Filters** | `IActionFilter`, `IExceptionFilter` | listeners `kernel.controller`, `kernel.exception` ; attributs comme `#[IsGranted]`, `#[Cache]` | |
| **Model binding — corps** | `[FromBody] CreateTaskDto dto` | `#[MapRequestPayload] CreateTaskInput $input` | `TaskController::create` |
| **Model binding — query** | `[FromQuery] TaskFilter filter` | `#[MapQueryString] TaskFilter $filter` (objet) ou `#[MapQueryParameter] int $page = 1` (scalaire) | ticket B3 |
| **Model binding — route** | `[FromRoute] int id` | `int $id` (même nom que `{id}`) | |
| **Charger l'entité depuis la route** | à la main : `await db.Tasks.FindAsync(id) ?? NotFound()` | **`Task $task`** : l'`EntityValueResolver` fait le `find()` et lève une 404 | `show(Task $task)` |
| **Validation** | DataAnnotations `[Required]`, `[StringLength]`, `ModelState` | Contraintes `#[Assert\NotBlank]`, `#[Assert\Length]` ; avec `MapRequestPayload`, échec = **422 automatique** | `CreateTaskInput` |
| **JSON** | `System.Text.Json`, `[JsonPropertyName]`, `[JsonIgnore]` | **Serializer** : normalizers + encoders, `#[SerializedName]`, `#[Ignore]`, **`#[Groups]`**, contexte | `$this->json($task)` |
| **Erreurs** | `ProblemDetails`, `UseExceptionHandler` | `format: 'json'` sur la route → erreurs en problem+json ; `HttpException`, `NotFoundHttpException`… | |
| **ORM** | **EF Core** | **Doctrine ORM** | |
| DbContext | `AppDbContext` | `EntityManagerInterface` (un seul, générique) | |
| DbSet | `DbSet<Task>` | `TaskRepository` (`ServiceEntityRepository`) | |
| Ajouter | `db.Tasks.Add(t)` | `$em->persist($t)` | |
| Supprimer | `db.Tasks.Remove(t)` | `$em->remove($t)` | |
| Sauvegarder | `SaveChanges()` | **`flush()`** | |
| Suivi des modifications | Change tracker | **Unit of Work** (+ *identity map*) | |
| Requêtes | LINQ | **DQL** / **QueryBuilder** (sur les entités, pas les tables) | `findAllOrdered()` |
| Chargement paresseux | Lazy loading proxies (opt-in) | **Proxies par défaut** sur les relations (`fetch: LAZY`) | |
| Mapping | DataAnnotations / Fluent API | Attributs `#[ORM\Entity]`, `#[ORM\Column]`… | `Task` |
| Migrations | `dotnet ef migrations add` / `database update` | `make sf-migration` / `make sf-migrate` | `migrations/` |
| Seed | `HasData()` | **Fixtures** (`DoctrineFixturesBundle`) | `AppFixtures` |
| **Configuration (valeurs)** | `appsettings.json`, `IOptions<T>` | `.env` (+ `.env.local` non commité) → `%env(XXX)%` dans le YAML ; `parameters:` ; `#[Autowire('%env(XXX)%')]` | `DATABASE_URL` |
| **Environnements** | `ASPNETCORE_ENVIRONMENT` = Development/Production | `APP_ENV` = `dev` / `prod` / `test` ; `when@prod:` dans le YAML ; `.env.test` | |
| **Secrets** | User Secrets, Key Vault | `.env.local`, `secrets:set` (coffre chiffré) | |
| **Logs** | `ILogger<T>` | `Psr\Log\LoggerInterface` (PSR-3), implémenté par **Monolog** | `var/log/dev.log` |
| **Auth** | Identity, `[Authorize]`, policies | **SecurityBundle** : firewalls, authenticators, `#[IsGranted('ROLE_X')]`, **voters** | ticket B2 |
| **Tâches de fond** | `IHostedService`, Hangfire | **Messenger** (bus + transports + `messenger:consume`) | non installé |
| **Tests** | xUnit + `WebApplicationFactory` | **PHPUnit** + `WebTestCase` / `KernelBrowser` | `TaskControllerTest` |
| **CLI** | `dotnet` | `bin/console` (`make sf c="..."`) + **MakerBundle** (`make:entity`, `make:controller`…) | |
| **Doc d'API** | Swashbuckle / Swagger | NelmioApiDocBundle ou API Platform | non installés |
| **Gestionnaire de paquets** | NuGet, `.csproj` | Composer, `composer.json` / `composer.lock` | `make sf-composer` |

---

## 4. Zoom sur les points clés

### 4.1 Le conteneur de services et l'autowiring

`config/services.yaml` (tel quel dans le projet) :

```yaml
services:
    _defaults:
        autowire: true      # injecte les dépendances d'après les type-hints du constructeur
        autoconfigure: true # tague automatiquement : commandes, listeners, voters, validateurs...
    App\:
        resource: '../src/'  # TOUTES les classes de src/ deviennent des services
```

Conséquence : vous écrivez une classe, vous la demandez dans un constructeur, **c'est tout**.

```php
final class TaskStatsService
{
    public function __construct(private readonly TaskRepository $tasks) {}
}

final class StatsController extends AbstractController
{
    public function __construct(private readonly TaskStatsService $stats) {} // aucune ligne de config
}
```

En C# vous auriez écrit `builder.Services.AddScoped<TaskStatsService>()`. Ici, rien. Pour voir ce qui est injectable :

```bash
make sf c="debug:autowiring Logger"
make sf c="debug:autowiring EntityManager"
```

Et on peut aussi injecter **dans l'action** du contrôleur (pas seulement dans le constructeur) :

```php
public function list(TaskRepository $tasks, LoggerInterface $logger): JsonResponse
```

### 4.2 Contrôleur fin, résolution d'arguments

```php
#[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
public function update(Task $task, #[MapRequestPayload] UpdateTaskInput $input): JsonResponse
```

Avant même d'entrer dans la méthode, Symfony a :
1. trouvé la route et extrait `id` ;
2. chargé la `Task` en base (ou renvoyé 404) ;
3. désérialisé le JSON dans `UpdateTaskInput`, validé les contraintes (ou renvoyé 422).

C'est l'équivalent de `[ApiController]` + model binding + `ModelState` automatique.

### 4.3 Doctrine en une phrase

**Doctrine est un Data Mapper avec Unit of Work, exactement comme EF Core** : les entités sont des objets PHP
simples, l'`EntityManager` les suit, et `flush()` calcule et exécute le SQL. Pas d'Active Record
(`$task->save()`) comme dans Laravel/Eloquent. Les pièges sont détaillés dans [02-particularites](02-particularites.md#5-doctrine-en-profondeur).

### 4.4 Configuration et environnements

Ordre de chargement des variables (le dernier gagne) :
`.env` → `.env.local` → `.env.$APP_ENV` → `.env.$APP_ENV.local` → **vraies variables d'environnement**.

- `.env` est commité (valeurs par défaut de dev), `.env.local` ne l'est jamais (vos surcharges, secrets).
- Dans le YAML : `'%env(DATABASE_URL)%'`, avec des processeurs : `%env(int:MAX_ITEMS)%`, `%env(bool:FEATURE_X)%`.
- Blocs conditionnels par environnement :

```yaml
# config/packages/doctrine.yaml (extrait réel)
when@test:
    doctrine:
        dbal:
            dbname_suffix: '_test%env(default::TEST_TOKEN)%'   # → base taskboard_test
```

- Injecter une valeur dans un service :

```php
public function __construct(
    #[Autowire('%env(int:TOKEN_TTL_HOURS)%')] private readonly int $tokenTtl,
) {}
```

### 4.5 Événements = vos middlewares

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

final class ApiVersionHeaderListener
{
    #[AsEventListener] // l'événement est déduit du type de l'argument
    public function __invoke(ResponseEvent $event): void
    {
        $event->getResponse()->headers->set('X-Api-Version', '2');
    }
}
```

Aucune déclaration : l'autoconfiguration a vu l'attribut. ≈ un middleware qui ajoute un en-tête après `next()`.

---

## 5. Ce qui va vous surprendre

1. **Il n'y a pas de `Program.cs`.** `src/Kernel.php` est vide ; tout est dans `config/` et dans les attributs. On cherche « où c'est enregistré ? » → nulle part, c'est l'autowiring. Utilisez `debug:container`, `debug:autowiring`, `debug:router`.
2. **Le YAML compte autant que le code.** Une faute d'indentation dans `config/packages/*.yaml` casse l'application. Les erreurs sont heureusement explicites.
3. **Le cache.** La config est compilée dans `var/cache/`. En dev elle se recompile seule quand un fichier change ; si un comportement est « bizarre » après une modif de config : `make sf-cc`.
4. **Pas d'`async`/`await`.** Une requête = un process synchrone. Le parallélisme, c'est le nombre de workers FPM. Les traitements longs → Messenger.
5. **`flush()` est le seul moment où la base est touchée.** Oublier `flush()` = rien n'est enregistré, sans erreur. Faire `flush()` dans une boucle = lent.
6. **Les erreurs s'affichent différemment en dev et en prod.** En dev, une 404 contient la trace complète ; en prod, juste `"detail": "Not Found"`.
7. **On ne type pas les collections.** `array` partout, la précision est dans les PHPDoc (`@return list<Task>`) lus par l'IDE et PHPStan — pas de génériques au runtime.
8. **Les noms en camelCase dans le JSON, en snake_case en base** — automatiquement (`createdAt` ↔ `created_at`, stratégie `underscore` dans `doctrine.yaml`).
9. **Un composant pour tout, mais rien d'installé par défaut.** Le squelette est minimal : la sécurité, Messenger, le CORS… s'ajoutent avec `make sf-composer c="require ..."` quand on en a besoin.
10. **Le profiler.** Chaque réponse en dev a un en-tête `X-Debug-Token-Link` : ouvrez-le dans le navigateur pour voir les requêtes SQL, les événements, les logs, le temps de chaque étape de **cette** requête. Votre meilleur ami.
