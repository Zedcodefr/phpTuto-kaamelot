# Visite guidée de `apps/api-symfony`

> Le formateur a déjà porté en Symfony toute la partie 1 (CRUD des tâches). Ce document vous fait lire ce code
> en le comparant à ce que vous avez écrit en PHP pur. Gardez les deux projets ouverts côte à côte.
>
> **En séance** : sections 2, 3 et 4 — la table de correspondance, `TaskController` et le parcours d'un POST.
> Les sections 1 et 5 sont des références ; l'exercice de la section 6 est un **bonus** (si vous avancez vite, ou plus tard).

Sommaire

1. [Arborescence commentée](#1-arborescence-commentée)
2. [PHP pur → Symfony : la table de correspondance](#2-php-pur--symfony--la-table-de-correspondance)
3. [Lecture commentée de TaskController](#3-lecture-commentée-de-taskcontroller)
4. [Parcours d'un POST /api/tasks de bout en bout](#4-parcours-dun-post-apitasks-de-bout-en-bout)
5. [Les commandes du Makefile](#5-les-commandes-du-makefile)
6. [Exercice bonus : le champ priority](#6-exercice-bonus--le-champ-priority)

---

## 1. Arborescence commentée

Seuls les fichiers que nous avons écrits ou modifiés sont détaillés ; le reste vient du squelette et des recettes Flex.

```
apps/api-symfony/
├── .env                          DATABASE_URL → postgresql://app:app@db:5432/taskboard
├── .env.test                     surcharges pour les tests (APP_SECRET...)
├── composer.json                 dépendances : Symfony 7.4.*, doctrine/orm, serializer, validator, monolog
│                                 dev : maker, fixtures, phpunit, dama/doctrine-test-bundle, web-profiler
├── config/
│   ├── bundles.php               + DAMADoctrineTestBundle (ajouté à la main, pas de recette)
│   ├── packages/doctrine.yaml    connexion + 2 mappings par attributs : src/Entity (App) et src/Kata/Entity (Kata) ;
│   │                             when@test → base *_test
│   ├── routes.yaml               importe les #[Route] des contrôleurs
│   ├── services.yaml             autowire + autoconfigure sur tout src/
│   └── services_test.yaml        en test seulement : App\Kata\Library rendu public (les tests du kata le lisent dans le conteneur)
├── migrations/
│   ├── Version20261015094951.php CREATE TABLE task (...)  ← l'équivalent de database/schema.sql
│   └── Version20261015112825.php tables kata_* du kata Doctrine (kata_author, kata_book, kata_review, kata_tag, kata_book_tag)
├── public/index.php              front controller (généré, on n'y touche jamais)
├── src/
│   ├── Controller/
│   │   ├── HealthController.php  GET /api/health — contrôleur "invokable" (une seule action)
│   │   └── TaskController.php    CRUD complet de la partie 1
│   ├── DataFixtures/
│   │   ├── AppFixtures.php       les 4 tâches de démo (mêmes que schema.sql)
│   │   └── KataFixtures.php      bibliothèque du kata (groupe "kata") — ne pas modifier, les tests en dépendent
│   ├── Dto/
│   │   ├── CreateTaskInput.php   corps de POST + contraintes de validation
│   │   └── UpdateTaskInput.php   corps de PATCH, tout facultatif
│   ├── Entity/Task.php           entité Doctrine, méthodes métier (rename, describe, changeStatus)
│   ├── Enum/TaskStatus.php       enum "backed" todo / in_progress / done
│   ├── Repository/TaskRepository.php  findAllOrdered() en QueryBuilder
│   ├── Kata/                     KATA DOCTRINE & DQL — domaine "bibliothèque" isolé du projet (partie 3)
│   │   ├── Entity/               Author, Book, Review, Tag : fournies, à lire (relations, cascade, orphanRemoval)
│   │   ├── Repository/           BookRepository, AuthorRepository : K01 → K16 à compléter (requêtes)
│   │   ├── Library.php           K17 → K21 à compléter (persist, flush, Unit of Work)
│   │   └── Dto/BookSummary.php   DTO construit par "SELECT NEW" (K11)
│   └── Kernel.php                vide (MicroKernelTrait)
├── tests/
│   ├── Controller/TaskControllerTest.php   6 tests fonctionnels HTTP (suite "app")
│   └── Kata/                     21 tests du kata (suite "kata") : KataTestCase + K1/K2/K3
└── phpunit.dist.xml              + extension DAMA (chaque test dans une transaction annulée)
                                  + 2 suites : "app" (make sf-test) et "kata" (make sf-kata)
```

> **Pourquoi un dossier `src/Kata` dans le projet ?** Le [kata Doctrine & DQL](../03-doctrine/04-kata-doctrine.md)
> a besoin d'un modèle riche (relations 1-N et N-N, notes, dates) que TaskBoard n'aura qu'en fin de backlog.
> Il vit dans son propre namespace (`App\Kata`), ses propres tables (`kata_*`) et sa propre suite de tests :
> il ne gêne ni l'API ni le backlog. Chacun le fait sur une branche `kata/<prénom>` qui n'est **pas** mergée.

---

## 2. PHP pur → Symfony : la table de correspondance

| Partie 1 (`apps/api-php`, votre code) | Partie 2 (`apps/api-symfony`) | Ce que Symfony fait pour vous |
|---|---|---|
| `public/index.php` : création des objets, enregistrement des routes, try/catch global | `public/index.php` généré + `Kernel` + `config/` | Tout le *bootstrap* |
| `Http/Router.php` + `$router->get('/api/tasks/{id}', ...)` | `#[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]` | Routing compilé, contraintes, génération d'URL (`generateUrl()`) |
| `Http/Request.php` (superglobales, `php://input`) | `Symfony\Component\HttpFoundation\Request` | Objet requête complet (headers, query, JSON via `toArray()`…) |
| `Http/JsonResponse.php` + `json_encode` | `$this->json($data, $status, $headers)` → `JsonResponse` | Sérialisation d'objets, en-têtes |
| `Http/HttpException.php`, `ValidationException.php` + `catch` dans `index.php` | Exceptions de `HttpKernel` (`NotFoundHttpException`…) + `ErrorListener` + `format: 'json'` | Rendu problem+json automatique, 404/422 sans écrire de code |
| `Database/Connection.php` (`new PDO(...)`, options) | `DATABASE_URL` dans `.env` + `config/packages/doctrine.yaml` | Connexion DBAL, paresseuse (ouverte seulement si utilisée) |
| `database/schema.sql` | `migrations/Version*.php` | Schéma **généré** depuis les entités, versionné, rejouable |
| `INSERT` de démo dans `schema.sql` | `DataFixtures/AppFixtures.php` | `doctrine:fixtures:load` |
| `Entity/Task.php` + `fromRow()` | `Entity/Task.php` + attributs `#[ORM\...]` | Hydratation automatique ligne SQL → objet |
| `Task::jsonSerialize()` | rien : le Serializer lit les getters | camelCase, dates RFC 3339, enums → valeur |
| `Repository/TaskRepository.php` (PDO, SQL, `prepare`/`execute`) | `TaskRepository extends ServiceEntityRepository` | `find`, `findAll`, `findBy` hérités ; QueryBuilder ; SQL généré |
| `$repository->save($task)` (INSERT ou UPDATE selon l'id) | `$em->persist($task)` + `$em->flush()` | Le Unit of Work décide INSERT/UPDATE/rien |
| `Validation/TaskValidator.php` (if/else à la main) | Contraintes `#[Assert\...]` sur `CreateTaskInput` | Validation déclarative + 422 automatique |
| `$controller->findOr404($id)` | paramètre `Task $task` | `EntityValueResolver` : find + 404 |
| Câblage manuel : `new TaskController(new TaskRepository($pdo))` | Constructeur avec type-hints | **Autowiring** |
| `tools/contract-check.php` | `tests/Controller/TaskControllerTest.php` + `make sf-check` (le même script, pointé sur Symfony) | Tests in-process, base isolée |

Exercice de lecture : pour chaque ligne du tableau, ouvrez les deux fichiers et notez une chose que Symfony gère et que votre version PHP pur ne gérait pas (exemple : `requirements: ['id' => '\d+']` fait que `/api/tasks/abc` donne 404 au lieu d'une erreur de type).

---

## 3. Lecture commentée de TaskController

```php
#[Route('/api/tasks', name: 'api_tasks_', format: 'json')]
```
Attribut de **classe** : préfixe d'URL et de nom de route pour toutes les actions (≈ `[Route("api/tasks")]` sur un contrôleur .NET).
`format: 'json'` : les erreurs seront rendues en JSON. Les noms de route complets seront `api_tasks_list`, `api_tasks_show`…

```php
final class TaskController extends AbstractController
```
`AbstractController` apporte des raccourcis : `json()`, `generateUrl()`, `createNotFoundException()`, `getUser()`, `denyAccessUnlessGranted()`… `final` : personne n'en hérite.

```php
public function __construct(
    private readonly TaskRepository $tasks,
    private readonly EntityManagerInterface $em,
) {
}
```
Injection par constructeur + **promotion de propriétés** (PHP 8). Aucun enregistrement : l'autowiring trouve `TaskRepository` (c'est un service de `src/`) et `EntityManagerInterface` (fourni par DoctrineBundle).

```php
#[Route('', name: 'list', methods: ['GET'])]
public function list(): JsonResponse
{
    return $this->json($this->tasks->findAllOrdered());
}
```
`''` = l'URL du préfixe elle-même : `GET /api/tasks`. `json()` sérialise un tableau d'entités `Task` via leurs getters.

```php
#[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
public function show(Task $task): JsonResponse
```
Le paramètre est typé `Task`, pas `int` : l'**EntityValueResolver** voit une entité Doctrine, prend `{id}` dans la route, fait `find($id)`, et lève une `NotFoundHttpException` si rien n'est trouvé. Vous ne voyez jamais le cas 404 dans le code.

```php
#[Route('', name: 'create', methods: ['POST'])]
public function create(#[MapRequestPayload] CreateTaskInput $input): JsonResponse
{
    $task = new Task($input->title, $input->description, $input->status);
    $this->em->persist($task);
    $this->em->flush();

    return $this->json($task, Response::HTTP_CREATED, [
        'Location' => $this->generateUrl('api_tasks_show', ['id' => $task->getId()]),
    ]);
}
```
- `#[MapRequestPayload]` : désérialise le JSON dans le DTO **et le valide** ; un JSON invalide → 400, des contraintes violées → 422.
- `"status": "nimporte"` ne peut pas devenir un `TaskStatus` → 422 également, avec une violation sur `status`.
- `persist` puis `flush` : c'est au `flush` que l'`INSERT` part et que `$task->getId()` est renseigné.
- `generateUrl('api_tasks_show', ...)` : on génère l'URL depuis le **nom** de la route (≈ `CreatedAtAction(nameof(Show), ...)`). Changer l'URL de la route ne casse rien.

```php
#[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
public function update(Task $task, #[MapRequestPayload] UpdateTaskInput $input): JsonResponse
{
    if (null !== $input->title) { $task->rename($input->title); }
    ...
    $this->em->flush();
```
Pas de `persist()` : `$task` vient de la base, elle est déjà suivie. On appelle des **méthodes métier** de l'entité (`rename`, `changeStatus`) plutôt que des setters. `null !== $x` (« Yoda condition ») est une convention du code Symfony, équivalente à `$x !== null`. Voir [la limite du PATCH](02-particularites.md#10-la-limite-du-patch-avec-un-dto-nullable).

```php
#[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
public function delete(Task $task): Response
{
    $this->em->remove($task);
    $this->em->flush();
    return new Response(status: Response::HTTP_NO_CONTENT);
}
```
`204` sans corps : une `Response` simple, pas une `JsonResponse` (qui écrirait `null`… ou `{}`).

---

## 4. Parcours d'un POST /api/tasks de bout en bout

```bash
curl -i -X POST http://localhost:8082/api/tasks -H 'Content-Type: application/json' -d '{"title": "Lire la visite"}'
```

| # | Où | Quoi |
|---|---|---|
| 1 | nginx (`docker/nginx/symfony.conf`) | `/api/tasks` n'est pas un fichier de `public/` → `try_files` envoie à `index.php` via FastCGI à `api-symfony:9000` |
| 2 | PHP-FPM → `public/index.php` | le Runtime lit `.env`, instancie `Kernel('dev', true)` |
| 3 | Kernel | charge le conteneur compilé depuis `var/cache/dev` (ou le compile si la config a changé) |
| 4 | `kernel.request` → `RouterListener` | `POST /api/tasks` → `api_tasks_create` → `TaskController::create` |
| 5 | Conteneur | construit `TaskController` avec `TaskRepository` et `EntityManager` (connexion BDD pas encore ouverte) |
| 6 | `kernel.controller_arguments` → `RequestPayloadValueResolver` | JSON → `CreateTaskInput` (Serializer) → validation (Validator) ; échec = 422 et on saute à l'étape 9 |
| 7 | `TaskController::create` | `new Task(...)`, `persist`, `flush` → `INSERT INTO task ...` (l'id généré est récupéré) |
| 8 | `$this->json(...)` | Serializer : `Task` → `{"id":..,"title":..,...}` ; statut 201 ; en-tête `Location` |
| 9 | `kernel.response` | le profiler ajoute `X-Debug-Token` / `X-Debug-Token-Link` |
| 10 | `send()` puis `kernel.terminate` | réponse envoyée ; le profiler enregistre ses données ; PHP détruit tout |

Ouvrez le `X-Debug-Token-Link` renvoyé : onglet **Performance** (la timeline de ces étapes), **Doctrine** (la requête `INSERT`), **Events**, **Validator**, **Serializer**.

---

## 5. Les commandes du Makefile

| Commande | Usage |
|---|---|
| `make sf-sh` | shell dans le conteneur PHP-FPM |
| `make sf c="..."` | n'importe quelle commande `bin/console` |
| `make sf-composer c="require ..."` | Composer |
| `make sf-routes` | liste des routes avec leur contrôleur |
| `make sf-entity` | `make:entity` (assistant interactif) |
| `make sf-migration` | génère une migration |
| `make sf-migrate` | exécute les migrations |
| `make sf-db-reset` | base supprimée, recréée, migrée, fixtures chargées |
| `make sf-test` | prépare la base de test et lance la suite `app` de PHPUnit (`c="--filter=Project"` pour filtrer) |
| `make sf-kata` | kata Doctrine : charge les fixtures du kata dans la base de test et lance la suite `kata` (`c="--filter=K05"`) |
| `make dql q="..."` | exécute une requête DQL et affiche le résultat (`make dql q="SELECT b.title FROM App\Kata\Entity\Book b"`) |
| `make sql q="..."` | exécute une requête SQL brute sur la base Symfony |
| `make sf-check` | contrat partie 1 vérifié contre l'API Symfony |
| `make sf-cc` | vide le cache |
| `make sf-psql` | console SQL sur la base `taskboard` |
| `make logs s=api-symfony` | logs PHP-FPM ; les logs applicatifs sont dans `apps/api-symfony/var/log/dev.log` |
| `make use-symfony` | le front React appelle désormais l'API Symfony |

---

## 6. Exercice bonus : le champ `priority`

> **Bonus, chacun sur sa branche `exo/priority-<prenom>`** — si vous avancez vite, ou plus tard
> (le kata Doctrine et le backlog couvrent les mêmes notions). Objectif : faire une fois tout le circuit
> entité → migration → DTO → test → front. **Ne pas merger** : `priority` ne fait pas partie du contrat d'API.

Spécification : une tâche a une priorité `low` | `normal` | `high`, défaut `normal`, exposée en JSON sous `priority`, modifiable en POST et PATCH.

1. **Enum** — créez `src/Enum/TaskPriority.php` sur le modèle de `TaskStatus`.
2. **Entité** — dans `Task` : propriété `#[ORM\Column(length: 10, enumType: TaskPriority::class)] private TaskPriority $priority;`, paramètre de constructeur avec défaut `TaskPriority::Normal`, `getPriority()`, méthode métier `prioritize(TaskPriority $p)`.
3. **Migration** — `make sf-migration`, **lisez le fichier généré**. Problème : les lignes existantes n'ont pas de valeur pour une colonne `NOT NULL`. Modifiez la migration pour ajouter `DEFAULT 'normal'` (ou remplissez les lignes avant la contrainte). Puis `make sf-migrate`.
4. **Vérifiez** — `curl http://localhost:8082/api/tasks` : `priority` apparaît sans avoir touché au contrôleur (c'est le Serializer qui lit `getPriority()`).
5. **DTO** — ajoutez `public TaskPriority $priority = TaskPriority::Normal` à `CreateTaskInput` et `public ?TaskPriority $priority = null` à `UpdateTaskInput`, puis utilisez-les dans `create()` et `update()`.
6. **Test** — dans `TaskControllerTest`, ajoutez `testCreateTaskWithPriority` (201 + `priority` = `high`) et `testInvalidPriorityReturns422`. `make sf-test`.
7. **Non-régression** — `make sf-check` doit rester vert.
8. **Front** (avec le dev front) — ajoutez `priority` au type `Task` dans `apps/front/src/types.ts` et affichez-la dans `TaskList`. `make use-symfony` puis http://localhost:5173.

Questions à se poser à la fin :
- Pourquoi la migration générée n'était-elle pas directement applicable ? Qu'aurait fait EF Core ?
- Que se passe-t-il pour l'API PHP pur, qui n'a pas ce champ ? Le front doit-il gérer `priority` absent ? (indice : le contrat est la source de vérité, on ne le modifie pas sans le dire à tout le monde)
