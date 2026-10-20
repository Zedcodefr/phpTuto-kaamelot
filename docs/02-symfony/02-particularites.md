# Symfony & Doctrine — les particularités

> Ce document se lit une première fois en entier, puis sert de **référence** pendant le backlog.
> Les sections 5 (Doctrine) et 6 (Serializer) sont celles où les devs .NET perdent le plus de temps.

Sommaire

1. [Flex et les recettes](#1-flex-et-les-recettes)
2. [Structure des dossiers](#2-structure-des-dossiers)
3. [Le cache compilé](#3-le-cache-compilé)
4. [Autowiring en détail](#4-autowiring-en-détail)
5. [Doctrine en profondeur](#5-doctrine-en-profondeur)
6. [Serializer](#6-serializer)
7. [Validator](#7-validator)
8. [Erreurs JSON](#8-erreurs-json)
9. [Sécurité (survol)](#9-sécurité-survol)
10. [La limite du PATCH avec un DTO nullable](#10-la-limite-du-patch-avec-un-dto-nullable)
11. [Outils de diagnostic : debug:*, profiler, maker](#11-outils-de-diagnostic)
12. [Bonnes pratiques](#12-bonnes-pratiques)

---

## 1. Flex et les recettes

Quand vous lancez `make sf-composer c="require symfony/security-bundle"` :

1. Composer télécharge le paquet et met à jour `composer.json` / `composer.lock` (≈ `dotnet add package`) ;
2. **Flex** cherche une *recette* pour ce paquet sur <https://github.com/symfony/recipes> ;
3. la recette **modifie votre projet** : ajoute le bundle dans `config/bundles.php`, crée `config/packages/security.yaml`, ajoute des variables dans `.env` entre des balises `###> paquet ###`, parfois crée des fichiers dans `src/` ;
4. Flex enregistre ce qu'il a fait dans **`symfony.lock`** (à commiter).

À retenir :

- **Lisez le `git diff` après chaque `composer require`.** C'est la meilleure façon de comprendre ce qu'un bundle configure.
- Ne vous étonnez pas d'un gros diff dans `config/reference.php` (environ 300 lignes pour `security-bundle`) : ce fichier est **généré** par Symfony 7.4 et décrit les options de configuration des bundles installés. On le commite tel quel, sans le relire en détail.
- `composer remove` déroule la recette à l'envers.
- Les recettes « contrib » (communauté) peuvent demander une confirmation ; certaines n'existent pas : il faut alors faire le travail soi-même (c'est ce qu'on a fait pour `DAMADoctrineTestBundle` dans `config/bundles.php` et `phpunit.dist.xml`).
- Notre `composer.json` contient `"extra": {"symfony": {"docker": "false"}}` : on refuse les fichiers Docker proposés par les recettes, l'infra Docker est à la racine du monorepo.

---

## 2. Structure des dossiers

```
apps/api-symfony/
├── bin/console              CLI (≈ dotnet + dotnet ef + générateurs)
├── config/
│   ├── bundles.php          bundles activés, par environnement
│   ├── packages/*.yaml      config de chaque bundle (+ when@dev / when@test / when@prod)
│   ├── routes.yaml          importe les #[Route] de src/Controller
│   └── services.yaml        règles d'autowiring + paramètres
├── migrations/              migrations Doctrine (versionnées, commitées)
├── public/index.php         front controller (seul dossier exposé par nginx)
├── src/
│   ├── Controller/  Entity/  Repository/  Dto/  Enum/  DataFixtures/
│   └── Kernel.php
├── tests/                   PHPUnit
├── var/cache/  var/log/     générés, jamais commités
├── vendor/                  dépendances (≈ packages NuGet restaurés)
├── .env  .env.dev  .env.test
└── composer.json  composer.lock  symfony.lock  phpunit.dist.xml
```

Les sous-dossiers de `src/` sont une **convention**, pas une obligation : l'autoload PSR-4 (`App\` → `src/`) et l'autowiring couvrent tout. Créez `src/Service/`, `src/Security/`, `src/EventListener/` librement.

---

## 3. Le cache compilé

Au premier appel, Symfony **compile** : tout le conteneur de services devient une classe PHP géante
(`var/cache/dev/ContainerXxx/`), les routes deviennent un tableau PHP optimisé, les métadonnées Doctrine sont mises en cache.
Les requêtes suivantes chargent ce PHP pré-calculé (gardé en mémoire par OPcache).

| Environnement | Recompilation |
|---|---|
| `dev` | automatique quand un fichier surveillé change (config, classe de service, attribut de route) |
| `prod` | **jamais** : il faut `cache:clear` à chaque déploiement |
| `test` | comme dev, dans `var/cache/test` |

Faites `make sf-cc` quand :
- vous avez modifié un `.env` et la valeur n'est pas prise ;
- vous avez une erreur incompréhensible juste après un `composer require/remove` ;
- un comportement « fantôme » persiste alors que le code a changé.

---

## 4. Autowiring en détail

### Les règles

- Le conteneur injecte d'après le **type** de chaque paramètre du constructeur (ou de l'action de contrôleur).
- Pour une **interface**, il faut qu'il n'existe qu'une implémentation (sinon : alias ou `#[AsAlias]`).
  ```php
  interface Clock { public function now(): \DateTimeImmutable; }

  #[AsAlias(Clock::class)] // "quand on demande Clock, donner SystemClock"
  final class SystemClock implements Clock { ... }
  ```
  ≈ `services.AddSingleton<IClock, SystemClock>()`.
- Pour un **scalaire** (string, int…), impossible de deviner : utilisez `#[Autowire]` ou `bind`.
  ```php
  public function __construct(
      #[Autowire('%kernel.project_dir%')] private string $projectDir,
      #[Autowire(env: 'int:TOKEN_TTL_HOURS')] private int $ttl,
      #[Autowire(service: 'monolog.logger.security')] private LoggerInterface $securityLogger,
  ) {}
  ```
  ou globalement dans `services.yaml` :
  ```yaml
  services:
      _defaults:
          bind:
              int $tokenTtlHours: '%env(int:TOKEN_TTL_HOURS)%'
  ```
- Les services sont **privés** : on ne fait pas `$container->get(...)` (≈ service locator, anti-pattern) ; on injecte. Exception : dans les tests, `static::getContainer()->get(...)` donne accès aux services privés.

### L'autoconfiguration : des attributs à la place de l'enregistrement

| Je veux… | J'écris | Équivalent .NET |
|---|---|---|
| un listener d'événement | `#[AsEventListener]` sur une méthode | middleware / filter |
| une commande console | `#[AsCommand(name: 'app:purge-tokens')]` sur une classe | `System.CommandLine` |
| un voter | `extends Voter` (tag automatique) | `IAuthorizationHandler` |
| un validateur de contrainte | `extends ConstraintValidator` | `ValidationAttribute` |
| un listener Doctrine | `#[AsEntityListener]` ou `#[AsDoctrineListener]` | `SaveChanges` interceptor |
| un normalizer | `implements NormalizerInterface` | `JsonConverter<T>` |

---

## 5. Doctrine en profondeur

### 5.1 Unit of Work, identity map, flush

```php
$task = $repo->find(1);       // SELECT
$same = $repo->find(1);       // pas de SELECT : identity map, $same === $task (même instance)
$task->rename('Nouveau');     // rien en base
$em->flush();                 // UPDATE task SET title = ... WHERE id = 1
```

- `persist()` ne sert **qu'aux nouveaux objets**. Une entité chargée depuis la base est déjà suivie.
- **Un seul `flush()` par requête**, à la fin du cas d'usage. `flush()` est transactionnel : tout ou rien.
- Après une exception Doctrine (contrainte unique violée…), l'EntityManager est **fermé** : on ne peut plus l'utiliser dans la requête. D'où l'intérêt de valider *avant* (ex. `UniqueEntity`).

### 5.2 Relations : owning side vs inverse side

Le piège n°1. Exemple du ticket B1 : un `Project` a des `Task`.

```php
// Task.php — côté PROPRIÉTAIRE (owning side) : c'est lui qui a la clé étrangère project_id
#[ORM\ManyToOne(inversedBy: 'tasks')]
#[ORM\JoinColumn(nullable: true)]
private ?Project $project = null;

// Project.php — côté INVERSE : pure commodité de navigation, aucune colonne
/** @var Collection<int, Task> */
#[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'project')]
private Collection $tasks;

public function __construct()
{
    $this->tasks = new ArrayCollection(); // jamais un array PHP !
}
```

**Doctrine ne regarde QUE le côté propriétaire au moment du `flush()`.**

```php
$project->getTasks()->add($task); // ❌ rien en base : on a modifié le côté inverse seulement
$task->assignTo($project);        // ✅ project_id est mis à jour
```

C'est pourquoi `make:entity` génère des méthodes qui synchronisent les deux côtés :

```php
public function addTask(Task $task): static
{
    if (!$this->tasks->contains($task)) {
        $this->tasks->add($task);
        $task->setProject($this);   // ← met à jour le côté propriétaire
    }
    return $this;
}
```

| EF Core | Doctrine |
|---|---|
| `ICollection<Task>` + `List<Task>` | `Collection<int, Task>` + `ArrayCollection` |
| Les deux côtés sont réconciliés par le change tracker | Seul le côté propriétaire (`ManyToOne`, ou celui qui a `inversedBy`) compte |
| `OnDelete(DeleteBehavior.Cascade)` | `#[ORM\JoinColumn(onDelete: 'CASCADE')]` (côté SQL) ou `cascade: ['remove']` (côté ORM) |
| — | `cascade: ['persist']` : persister automatiquement les enfants nouveaux |
| Suppression d'orphelins | `orphanRemoval: true` sur le `OneToMany` |

### 5.3 Lazy loading et N+1

Les relations sont chargées **à la demande** via des proxies. Sérialiser 50 tâches qui affichent `project.name` = 1 requête pour les tâches + jusqu'à 50 requêtes pour les projets. Le profiler (onglet *Doctrine*) le montre immédiatement.

Solution : un **fetch join** dans le repository.

```php
return $this->createQueryBuilder('t')
    ->leftJoin('t.project', 'p')->addSelect('p')   // ≈ .Include(t => t.Project)
    ->orderBy('t.id', 'ASC')
    ->getQuery()
    ->getResult();
```

Pour un compteur (`taskCount`), ne chargez pas la collection. Pour **un** projet, `$project->getTasks()->count()` avec `fetch: 'EXTRA_LAZY'` sur la relation suffit (Doctrine fait alors un `SELECT COUNT(*)`). Pour une **liste**, `EXTRA_LAZY` ferait un `COUNT` par projet, donc un N+1 : comptez dans la requête de liste (`SELECT p, COUNT(t.id) ... GROUP BY p.id`, une seule requête).

### 5.4 QueryBuilder et DQL

```php
$qb = $this->createQueryBuilder('t')
    ->andWhere('t.status = :status')->setParameter('status', TaskStatus::Todo)
    ->andWhere('LOWER(t.title) LIKE :q')->setParameter('q', '%'.mb_strtolower($search).'%')
    ->setFirstResult(($page - 1) * $limit)
    ->setMaxResults($limit);
```

- On interroge des **entités et propriétés** (`t.createdAt`), pas des colonnes (`created_at`).
- Toujours des paramètres (`:status`) : jamais de concaténation (injection SQL, comme en partie 1).
- Avec un `JOIN` + `setMaxResults`, utilisez `Doctrine\ORM\Tools\Pagination\Paginator` : il compte correctement et `count($paginator)` donne le total (→ `X-Total-Count`).

### 5.5 Enums et types

`#[ORM\Column(enumType: TaskStatus::class)]` stocke la valeur (`'todo'`) et réhydrate l'enum. Dates : utilisez toujours `\DateTimeImmutable` (types `datetime_immutable`), comme dans `Task::$createdAt`.

### 5.6 Migrations

- `make sf-migration` compare **le mapping des entités** et **la base actuelle**, et génère le diff SQL dans `migrations/VersionYYYYMMDDHHMMSS.php`.
- **Relisez toujours la migration générée** (≈ relire la migration EF).
- `make sf-migrate` exécute celles qui ne sont pas encore jouées (table `doctrine_migration_versions` ≈ `__EFMigrationsHistory`).
- Si votre base locale est « sale » (vous avez bricolé), `make sf-db-reset` repart de zéro.
- Règles d'équipe : voir [le backlog](04-backlog-back.md#conventions-migrations).

### 5.7 Entités vs DTO

| | Entité | DTO |
|---|---|---|
| Rôle | Modèle persistant, règles métier | Contrat d'entrée/sortie HTTP |
| Validation | Invariants métier (constructeur, méthodes) | Contraintes `#[Assert]` sur les données reçues |
| Sérialisation directe | OK pour une entité simple (comme `Task` aujourd'hui) | Recommandé dès qu'il y a des relations |

Le projet désérialise **toujours** vers des DTO (`CreateTaskInput`), jamais directement vers l'entité : pas de risque de « mass assignment » (un client qui enverrait `"id": 42` ou `"createdAt"`).

---

## 6. Serializer

`$this->json($data)` utilise le composant Serializer : **normalisation** (objet → tableau, via les getters) puis **encodage** (tableau → JSON).

### Le piège des références circulaires

Dès que `Task` a un `project` et que `Project` a des `tasks`, `$this->json($task)` boucle : task → project → tasks → project… → `CircularReferenceException`. Deux solutions :

**a) Les groupes de sérialisation**

```php
use Symfony\Component\Serializer\Attribute\Groups;

class Task
{
    #[Groups(['task:read'])]
    public function getTitle(): string { ... }

    #[Groups(['task:read'])]
    public function getProject(): ?Project { ... }
}

class Project
{
    #[Groups(['task:read', 'project:read'])]
    public function getName(): string { ... }

    // pas de groupe task:read sur getTasks() → la boucle est coupée
}

return $this->json($task, context: ['groups' => ['task:read']]);
```

⚠️ Dès qu'on passe un groupe, **seuls** les attributs portant ce groupe sont exportés (y compris `id` : n'oubliez pas de l'annoter).

**b) Un DTO de sortie** (`TaskOutput::fromEntity($task)`) : plus de code, mais un contrat explicite et découplé. Les deux approches sont acceptables dans le backlog ; choisissez-en une par équipe.

### Autres attributs utiles

| Attribut | Effet | .NET |
|---|---|---|
| `#[SerializedName('project_id')]` | renomme | `[JsonPropertyName]` |
| `#[Ignore]` | n'exporte jamais (ex. mot de passe) | `[JsonIgnore]` |
| `#[MaxDepth(1)]` + contexte `enable_max_depth` | limite la profondeur | `ReferenceHandler`/`MaxDepth` |
| `#[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]` | format d'une date | `JsonConverter` |

Une méthode publique `getXxx()` / `isXxx()` / `hasXxx()` **est exportée** sous le nom `xxx`. Une méthode calculée `getTaskCount()` donne donc `taskCount` gratuitement. Le format de date par défaut est RFC 3339 (`2026-10-15T09:30:00+02:00`), conforme au contrat.

---

## 7. Validator

Contraintes courantes : `NotBlank`, `NotNull`, `Length`, `Email`, `Range`, `Positive`, `Choice`, `Regex`, `Url`, `Valid` (valider un objet imbriqué), `Count`, `PasswordStrength`.

| Besoin | Solution |
|---|---|
| Unicité en base (`name` de projet, `email`) | `#[UniqueEntity('name')]` sur l'**entité**, ou une contrainte personnalisée sur le DTO (car le DTO n'est pas une entité) |
| Règle qui dépend de plusieurs champs | `#[Assert\Callback]` sur une méthode du DTO |
| Règle qui a besoin d'un service (ex. « le projet existe ») | contrainte personnalisée (`make:validator`) : la classe `XxxValidator` est un service, injectez-y le repository |
| Règles différentes création/édition | DTO différents (ce qu'on fait) ou groupes de validation |

`#[MapRequestPayload]` valide automatiquement et renvoie **422** avec `violations[]`. Pour valider à la main :

```php
$violations = $validator->validate($dto);
if (count($violations) > 0) {
    throw new ValidationFailedException($dto, $violations); // rendu en 422 problem+json
}
```

---

## 8. Erreurs JSON

- `format: 'json'` sur la classe `TaskController` (`#[Route('/api/tasks', format: 'json')]`) dit à Symfony que la réponse attendue est du JSON : toute exception est alors rendue en *problem details* par le `ProblemNormalizer`. **Mettez-le sur chaque nouveau contrôleur d'API.**
- Lancer une erreur HTTP : `throw $this->createNotFoundException('Project 3 not found')`, `throw new ConflictHttpException('...')` (409), `throw new AccessDeniedHttpException()` (403)… Toute exception qui n'est pas une `HttpExceptionInterface` donne une 500.
- **dev vs prod** : en dev, le JSON contient `detail` = message de l'exception + `class` + `trace` complète. En prod, `detail` = le texte HTTP standard (« Not Found »), sans trace. Ne mettez donc jamais d'info sensible dans les messages… et ne testez pas la valeur exacte de `detail`.
- Pour personnaliser globalement : un listener `kernel.exception` (≈ `IExceptionFilter`).

---

## 9. Sécurité (survol)

À installer au ticket B2 : `make sf-composer c="require symfony/security-bundle"`.

| Notion | Rôle | .NET |
|---|---|---|
| **User provider** | charge un `User` depuis un identifiant (email) | `UserManager` / `IUserStore` |
| **Password hasher** | `UserPasswordHasherInterface::hashPassword()` / `isPasswordValid()` (bcrypt/argon2 « auto ») | `PasswordHasher<T>` |
| **Firewall** | zone de l'appli (`^/api`) avec sa méthode d'authentification ; `stateless: true` pour une API | schéma d'authentification |
| **Authenticator** | extrait et vérifie les credentials. Natif : `access_token` (lit `Authorization: Bearer`) + un `AccessTokenHandlerInterface` à écrire | `AuthenticationHandler` (JwtBearer…) |
| **access_control** | règles par URL/méthode dans `security.yaml` | `RequireAuthorization()` |
| **`#[IsGranted('ROLE_ADMIN')]`** | sur une action/classe | `[Authorize(Roles = "Admin")]` |
| **Voter** | décision fine sur un objet : « peut-il supprimer CETTE tâche ? » ; `#[IsGranted('TASK_DELETE', 'task')]` | policy + `IAuthorizationHandler` avec ressource |
| `$this->getUser()` / `Security::getUser()` | utilisateur courant | `User` / `HttpContext.User` |

Réponses : non authentifié → **401**, authentifié mais refusé → **403** (conforme au contrat).

---

## 10. La limite du PATCH avec un DTO nullable

Le projet utilise `UpdateTaskInput` où tout est `?string = null`. Problème : **« absent du JSON » et « `null` explicite » sont indiscernables.** On ne peut donc pas remettre `description` à `null` (le projet accepte `""` comme contournement) — et ce sera bloquant au ticket B1 pour `"projectId": null` (détacher une tâche).

Solutions, de la plus simple à la plus élégante :

**a) Regarder les clés du JSON brut** (comme en PHP pur) :

```php
public function update(Task $task, #[MapRequestPayload] UpdateTaskInput $input, Request $request): JsonResponse
{
    $sent = $request->toArray();                 // JSON décodé
    if (array_key_exists('projectId', $sent)) {  // présent, même si null
        $task->assignTo($input->projectId === null ? null : $this->projects->find($input->projectId));
    }
    // ...
}
```

**b) Un DTO qui mémorise les champs fournis** (un `private array $provided` rempli par un setter, ou un objet `Optional`).

**c) Désérialiser directement sur un objet existant** avec `AbstractNormalizer::OBJECT_TO_POPULATE` :

```php
$serializer->deserialize($request->getContent(), TaskPatch::class, 'json', [
    AbstractNormalizer::OBJECT_TO_POPULATE => TaskPatch::fromTask($task), // valeurs actuelles
]);
// seules les clés présentes dans le JSON écrasent les valeurs ; puis valider, puis appliquer
```

Le ticket B1-3 vous demande de choisir et d'implémenter l'une de ces solutions.

---

## 11. Outils de diagnostic

### Commandes `debug:*`

| Commande (`make sf c="..."`) | Répond à |
|---|---|
| `debug:router` (ou `make sf-routes`) | quelles routes existent, avec quel contrôleur ? |
| `debug:router api_tasks_show` | détail d'une route (regex, méthodes, defaults) |
| `router:match /api/tasks/3 --method=PATCH` | quelle route va répondre à cette URL ? |
| `debug:autowiring Logger` | quel type-hint utiliser pour obtenir tel service ? |
| `debug:container App\Repository\TaskRepository` | comment ce service est-il construit ? |
| `debug:event-dispatcher kernel.request` | qui écoute cet événement, dans quel ordre ? |
| `debug:config doctrine` | configuration effective d'un bundle (après fusion des fichiers) |
| `config:dump-reference security` | toutes les options possibles d'un bundle, commentées |
| `doctrine:mapping:info` / `doctrine:schema:validate` | le mapping est-il valide et synchro avec la base ? |
| `debug:dotenv` | quelles variables d'env, venant de quel fichier ? |

### Le profiler

Chaque réponse en dev contient :

```
X-Debug-Token: 0a7921
X-Debug-Token-Link: http://localhost:8082/_profiler/0a7921
```

Ouvrez le lien (ou <http://localhost:8082/_profiler> pour les dernières requêtes, y compris celles faites par le front ou par `curl`) : requêtes SQL avec temps et paramètres, événements, logs, sécurité, exception, validation, sérialisation, timeline.

### MakerBundle

`make sf c="list make"` affiche tous les générateurs. Les utiles ici :

| Commande | Génère |
|---|---|
| `make:entity` (ou `make sf-entity`) | entité + repository, ou ajoute des champs/relations à une entité existante (interactif) |
| `make:migration` (ou `make sf-migration`) | migration depuis le diff mapping ↔ base |
| `make:controller` | contrôleur (préférez l'écrire à la main pour une API) |
| `make:user` | entité `User` + config sécurité |
| `make:voter`, `make:validator`, `make:subscriber`, `make:listener`, `make:command` | squelettes de services |
| `make:test` | test PHPUnit (`WebTestCase`, `KernelTestCase`, `TestCase`) |
| `make:fixtures` | classe de fixtures |

Le code généré est un **point de départ** : relisez-le, adaptez-le (on préfère par exemple des méthodes métier aux setters génériques, comme dans `Task`).

---

## 12. Bonnes pratiques

1. **Contrôleurs fins** : décoder (DTO), appeler, répondre. La logique métier va dans l'entité (méthodes métier) ou dans un service `src/Service/` quand elle implique plusieurs objets.
2. **DTO en entrée, toujours.** En sortie : groupes ou DTO, mais un choix cohérent.
3. **Un `flush()` par cas d'usage**, dans le contrôleur ou le service applicatif, pas dans le repository.
4. **Requêtes dans les repositories**, pas de DQL dans les contrôleurs.
5. **`declare(strict_types=1)`, `final`, `readonly`** partout où c'est possible (comme dans le projet).
6. **Injecter des interfaces** (`EntityManagerInterface`, `LoggerInterface`), pas des implémentations.
7. **Tester par le HTTP** (`WebTestCase`) chaque endpoint : cas nominal + un cas d'erreur par code du contrat.
8. **Lire la doc de la bonne version** : <https://symfony.com/doc/7.4/> (beaucoup de tutos en ligne datent de Symfony 3/4, avec annotations, `services.yml`, `AppBundle`… à ignorer).
