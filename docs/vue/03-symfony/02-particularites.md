# Symfony — les particularités

> À lire une première fois en entier, puis à garder comme **référence** pendant le backlog.
> Les sections 4 (autowiring), 6 (Serializer) et 10 (PATCH) sont celles où l'on bloque le plus souvent.
> Doctrine n'est ici qu'en aperçu : il a son étape à lui, juste après ([cours Doctrine](../04-doctrine/README.md)).

Sommaire

1. [Flex et les recettes](#1-flex-et-les-recettes)
2. [Structure des dossiers](#2-structure-des-dossiers)
3. [Le cache compilé](#3-le-cache-compilé)
4. [Autowiring en détail](#4-autowiring-en-détail)
5. [Doctrine : aperçu](#5-doctrine--aperçu)
6. [Serializer](#6-serializer)
7. [Validator](#7-validator)
8. [Erreurs JSON](#8-erreurs-json)
9. [Sécurité (survol)](#9-sécurité-survol)
10. [La limite du PATCH avec un DTO nullable](#10-la-limite-du-patch-avec-un-dto-nullable)
11. [Outils de diagnostic](#11-outils-de-diagnostic)
12. [Bonnes pratiques](#12-bonnes-pratiques)

---

## 1. Flex et les recettes

Quand vous lancez `make sf-composer c="require symfony/security-bundle"` :

1. Composer télécharge le paquet et met à jour `composer.json` / `composer.lock` (≈ `npm install` qui met à jour `package.json` / `package-lock.json`) ;
2. **Flex** cherche une *recette* pour ce paquet sur <https://github.com/symfony/recipes> ;
3. la recette **modifie votre projet** : ajoute le bundle dans `config/bundles.php`, crée `config/packages/security.yaml`, ajoute des variables dans `.env` entre des balises `###> paquet ###`, parfois crée des fichiers dans `src/` ;
4. Flex enregistre ce qu'il a fait dans **`symfony.lock`** (à commiter).

C'est l'équivalent d'un `vue add` / `nuxi module add` : le paquet ne fait pas que s'installer, il **se branche**.

À retenir :

- **Lisez le `git diff` après chaque `composer require`.** C'est la meilleure façon de comprendre ce qu'un bundle configure.
- Ne vous étonnez pas d'un gros diff dans `config/reference.php` (environ 300 lignes pour `security-bundle`) : ce fichier est **généré** par Symfony 7.4 et décrit les options de configuration des bundles installés. On le commite tel quel, sans le relire en détail.
- `composer remove` déroule la recette à l'envers.
- Certaines recettes « contrib » (communauté) demandent une confirmation ; d'autres n'existent pas : il faut alors brancher le bundle soi-même (c'est ce qui a été fait pour `DAMADoctrineTestBundle` dans `config/bundles.php` et `phpunit.dist.xml`).
- Notre `composer.json` contient `"extra": {"symfony": {"docker": "false"}}` : on refuse les fichiers Docker proposés par les recettes, l'infra Docker est à la racine du monorepo.

---

## 2. Structure des dossiers

```
apps/api-symfony/
├── bin/console              CLI (≈ npm run … + générateurs)
├── config/
│   ├── bundles.php          bundles activés, par environnement (≈ les app.use(...))
│   ├── packages/*.yaml      config de chaque bundle (+ when@dev / when@test / when@prod)
│   ├── routes.yaml          importe les #[Route] de src/Controller
│   └── services.yaml        règles d'autowiring + paramètres
├── migrations/              migrations Doctrine (versionnées, commitées)
├── public/index.php         front controller (seul dossier exposé par nginx, ≈ public/ de Vite)
├── src/
│   ├── Controller/  Entity/  Repository/  Dto/  Enum/  DataFixtures/
│   └── Kernel.php
├── tests/                   PHPUnit
├── var/cache/  var/log/     générés, jamais commités (≈ dist/, .vite/)
├── vendor/                  dépendances (≈ node_modules/)
├── .env  .env.dev  .env.test
└── composer.json  composer.lock  symfony.lock  phpunit.dist.xml
```

Les sous-dossiers de `src/` sont une **convention**, pas une obligation : l'autoload PSR-4 (`App\` → `src/`)
et l'autowiring couvrent tout. Créez `src/Service/`, `src/Security/`, `src/EventListener/` librement — comme vous
créeriez `src/composables/` ou `src/services/` dans un projet Vue.

---

## 3. Le cache compilé

Au premier appel, Symfony **compile** : tout le conteneur de services devient une classe PHP géante
(`var/cache/dev/ContainerXxx/`), les routes deviennent un tableau PHP optimisé, les métadonnées Doctrine sont mises en cache.
Les requêtes suivantes chargent ce PHP pré-calculé (gardé en mémoire par OPcache). C'est un peu le `vite build`
de la configuration : on paie l'analyse une fois, pas à chaque requête.

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
  ≈ `provide(ClockKey, new SystemClock())` en Vue, ou `{ provide: Clock, useClass: SystemClock }` en NestJS.
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
- Les services sont **privés** : on ne fait pas `$container->get(...)` (c'est le *service locator*, un anti-pattern) ; on injecte. Exception : dans les tests, `static::getContainer()->get(...)` donne accès aux services privés.
- **Durée de vie** : un service est créé une fois **par requête** et partagé pendant cette requête. Il n'y a pas d'équivalent d'un store Pinia qui survit : voir [les principes](01-principes.md#7-ce-qui-va-vous-surprendre).

### L'autoconfiguration : des attributs à la place de l'enregistrement

| Je veux… | J'écris | Équivalent JS |
|---|---|---|
| un listener d'événement | `#[AsEventListener]` sur une méthode | middleware Express / interceptor NestJS |
| une commande console | `#[AsCommand(name: 'app:purge-tokens')]` sur une classe | un script `npm run …` |
| un voter | `extends Voter` (tag automatique) | un guard NestJS qui inspecte la ressource |
| un validateur de contrainte | `extends ConstraintValidator` | un `refine()` zod… exécuté côté serveur |
| un listener Doctrine | `#[AsEntityListener]` ou `#[AsDoctrineListener]` | un middleware Prisma (`$extends`) / un subscriber TypeORM |
| un normalizer | `implements NormalizerInterface` | une méthode `toJSON()` |

---

## 5. Doctrine : aperçu

Doctrine a [son étape dédiée](../04-doctrine/README.md). Ce qu'il faut savoir dès maintenant pour lire le projet :

```php
$task = $repo->find(1);       // SELECT
$same = $repo->find(1);       // pas de SELECT : même instance (identity map)
$task->rename('Nouveau');     // rien en base
$em->flush();                 // UPDATE task SET title = ... WHERE id = 1
```

| Prisma / TypeORM | Doctrine |
|---|---|
| `prisma.task.create({ data })` : écrit tout de suite | `$em->persist($task)` : « suis cet objet » ; l'écriture part au `flush()` |
| `prisma.task.update(...)` à chaque modification | on modifie l'objet, **un seul** `$em->flush()` envoie tous les changements |
| `schema.prisma` | attributs `#[ORM\Column]` dans la classe entité |
| `prisma migrate dev` | `make sf-migration` puis `make sf-migrate` |
| `include: { project: true }` | *fetch join* (`leftJoin` + `addSelect`) dans un repository |

Les relations (côté propriétaire / côté inverse), le problème N+1, le DQL et la pagination sont traités à l'étape 4.

---

## 6. Serializer

`$this->json($data)` utilise le composant Serializer : **normalisation** (objet → tableau, via les getters) puis
**encodage** (tableau → JSON). C'est un `JSON.stringify` qui sait lire des objets PHP.

### Le piège des références circulaires

Dès que `Task` a un `project` et que `Project` a des `tasks`, `$this->json($task)` boucle : task → project → tasks → project…
→ `CircularReferenceException`. C'est exactement le `TypeError: Converting circular structure to JSON` de
`JSON.stringify`. Deux solutions :

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

**b) Un DTO de sortie** (`TaskOutput::fromEntity($task)`) : plus de code, mais un contrat explicite et découplé —
comme un type TypeScript `TaskDto` qu'on construit à la main avant de répondre. Les deux approches sont acceptables
dans le backlog ; choisissez-en une par équipe.

### Autres attributs utiles

| Attribut | Effet | Équivalent JS |
|---|---|---|
| `#[SerializedName('project_id')]` | renomme | `toJSON()` qui renomme la clé |
| `#[Ignore]` | n'exporte jamais (ex. mot de passe) | supprimer la clé avant `res.json()` |
| `#[MaxDepth(1)]` + contexte `enable_max_depth` | limite la profondeur | — |
| `#[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]` | format d'une date | `date.toISOString().slice(0, 10)` |

Une méthode publique `getXxx()` / `isXxx()` / `hasXxx()` **est exportée** sous le nom `xxx`. Une méthode calculée
`getTaskCount()` donne donc `taskCount` gratuitement (≈ un `computed` exposé). Le format de date par défaut est
RFC 3339 (`2026-10-15T09:30:00+02:00`), conforme au contrat — et directement lisible par `new Date(...)` côté front.

---

## 7. Validator

Contraintes courantes : `NotBlank`, `NotNull`, `Length`, `Email`, `Range`, `Positive`, `Choice`, `Regex`, `Url`,
`Valid` (valider un objet imbriqué), `Count`, `PasswordStrength`. Si vous avez utilisé zod, vous êtes en terrain connu :

| zod / vee-validate | Symfony |
|---|---|
| `z.string().min(3).max(255)` | `#[Assert\Length(min: 3, max: 255)]` |
| `z.string().email()` | `#[Assert\Email]` |
| `z.enum(['todo', 'in_progress', 'done'])` | propriété typée `TaskStatus` (enum PHP) ou `#[Assert\Choice]` |
| `.refine((data) => …)` | `#[Assert\Callback]` sur une méthode du DTO |
| erreurs par champ (`errors.title`) | `violations[]` avec `propertyPath: "title"` dans la réponse 422 |

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

Rappel essentiel : vous **gardez** la validation dans vos formulaires Vue (c'est du confort pour l'utilisateur),
mais c'est celle-ci qui protège les données.

---

## 8. Erreurs JSON

- `format: 'json'` sur la classe `TaskController` (`#[Route('/api/tasks', format: 'json')]`) dit à Symfony que la réponse attendue est du JSON : toute exception est alors rendue en *problem details* par le `ProblemNormalizer`. **Mettez-le sur chaque nouveau contrôleur d'API.**
- Lancer une erreur HTTP : `throw $this->createNotFoundException('Project 3 not found')`, `throw new ConflictHttpException('...')` (409), `throw new AccessDeniedHttpException()` (403)… Toute exception qui n'est pas une `HttpExceptionInterface` donne une 500. ≈ `throw createError({ statusCode: 404 })` dans Nuxt server.
- **dev vs prod** : en dev, le JSON contient `detail` = message de l'exception + `class` + `trace` complète. En prod, `detail` = le texte HTTP standard (« Not Found »), sans trace. Ne mettez donc jamais d'info sensible dans les messages… et ne testez pas la valeur exacte de `detail`.
- Pour personnaliser globalement : un listener `kernel.exception` (≈ middleware d'erreur Express).

---

## 9. Sécurité (survol)

Les notions de base (authentification vs autorisation, hash de mot de passe, tokens, 401 vs 403) sont dans
[Sécurité côté serveur](../01-fondamentaux/03-securite-cote-serveur.md). Côté Symfony, à installer au ticket B2 :
`make sf-composer c="require symfony/security-bundle"`.

| Notion | Rôle | Équivalent JS |
|---|---|---|
| **User provider** | charge un `User` depuis un identifiant (email) | la fonction `findUser` d'une stratégie passport |
| **Password hasher** | `UserPasswordHasherInterface::hashPassword()` / `isPasswordValid()` (bcrypt/argon2 « auto ») | `bcrypt.hash()` / `bcrypt.compare()` |
| **Firewall** | zone de l'appli (`^/api`) avec sa méthode d'authentification ; `stateless: true` pour une API | un middleware d'auth monté sur `/api` |
| **Authenticator** | extrait et vérifie les credentials. Natif : `access_token` (lit `Authorization: Bearer`) + un `AccessTokenHandlerInterface` à écrire | `passport-http-bearer`, `AuthGuard('bearer')` NestJS |
| **access_control** | règles par URL/méthode dans `security.yaml` | `router.beforeEach` qui vérifie `meta.requiresAuth`… mais côté serveur |
| **`#[IsGranted('ROLE_ADMIN')]`** | sur une action/classe | `@UseGuards(RolesGuard)` + `@Roles('admin')` |
| **Voter** | décision fine sur un objet : « peut-il supprimer CETTE tâche ? » ; `#[IsGranted('TASK_DELETE', 'task')]` | une fonction `canDelete(user, task)` appelée dans le handler |
| `$this->getUser()` | utilisateur courant | `req.user` |

Réponses : non authentifié → **401**, authentifié mais refusé → **403** (conforme au contrat).
Un `v-if="isAdmin"` dans un template cache un bouton ; il ne protège rien. La décision se prend ici.

---

## 10. La limite du PATCH avec un DTO nullable

Le projet utilise `UpdateTaskInput` où tout est `?string = null`. Problème : **« absent du JSON » et « `null` explicite »
sont indiscernables.** En JavaScript vous feriez la différence entre `'description' in body` et `body.description === null` ;
ici, une fois le JSON transformé en DTO, cette information est perdue. On ne peut donc pas remettre `description`
à `null` (le projet accepte `""` comme contournement) — et ce sera bloquant au ticket B1 pour `"projectId": null`
(détacher une tâche).

Solutions, de la plus simple à la plus élégante :

**a) Regarder les clés du JSON brut** (comme en PHP pur, ≈ `'projectId' in req.body`) :

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

≈ `Object.assign(currentValues, body)` : seules les clés envoyées écrasent. Le ticket B1-3 vous demande (en bonus) de
choisir et d'implémenter l'une de ces solutions.

---

## 11. Outils de diagnostic

### Commandes `debug:*`

| Commande (`make sf c="..."`) | Répond à |
|---|---|
| `debug:router` (ou `make sf-routes`) | quelles routes existent, avec quel contrôleur ? (≈ lister `router.getRoutes()`) |
| `debug:router api_tasks_show` | détail d'une route (regex, méthodes, defaults) |
| `router:match /api/tasks/3 --method=PATCH` | quelle route va répondre à cette URL ? |
| `debug:autowiring Logger` | quel type utiliser pour obtenir tel service ? |
| `debug:container App\Repository\TaskRepository` | comment ce service est-il construit ? |
| `debug:event-dispatcher kernel.request` | qui écoute cet événement, dans quel ordre ? (la chaîne de « middlewares ») |
| `debug:config doctrine` | configuration effective d'un bundle (après fusion des fichiers) |
| `config:dump-reference security` | toutes les options possibles d'un bundle, commentées |
| `doctrine:mapping:info` / `doctrine:schema:validate` | le mapping est-il valide et synchro avec la base ? |
| `debug:dotenv` | quelles variables d'env, venant de quel fichier ? |

### Le profiler

Le Vue DevTools du back. Chaque réponse en dev contient :

```
X-Debug-Token: 0a7921
X-Debug-Token-Link: http://localhost:8082/_profiler/0a7921
```

Ouvrez le lien (ou <http://localhost:8082/_profiler> pour les dernières requêtes, y compris celles faites par le front ou
par `curl`) : requêtes SQL avec paramètres, événements, logs, sécurité, exception, validation, sérialisation, timeline.
Prenez le réflexe d'ouvrir l'onglet **Doctrine** : c'est là qu'on voit si une liste déclenche une requête par élément.

### MakerBundle

`make sf c="list make"` affiche tous les générateurs (≈ les générateurs de `nest g` ou de `nuxi add`). Les utiles ici :

| Commande | Génère |
|---|---|
| `make:entity` (ou `make sf-entity`) | entité + repository, ou ajoute des champs/relations à une entité existante (interactif) |
| `make:migration` (ou `make sf-migration`) | migration depuis le diff mapping ↔ base |
| `make:controller` | contrôleur (préférez l'écrire à la main pour une API) |
| `make:user` | entité `User` + config sécurité |
| `make:voter`, `make:validator`, `make:subscriber`, `make:listener`, `make:command` | squelettes de services |
| `make:test` | test PHPUnit (`WebTestCase`, `KernelTestCase`, `TestCase`) |
| `make:fixtures` | classe de fixtures |

Le code généré est un **point de départ** : relisez-le, adaptez-le (on préfère par exemple des méthodes métier aux
setters génériques, comme dans `Task`).

---

## 12. Bonnes pratiques

1. **Contrôleurs fins** : décoder (DTO), appeler, répondre. La logique métier va dans l'entité (méthodes métier) ou dans un service `src/Service/` quand elle implique plusieurs objets. C'est le même principe que « composants fins, logique dans les composables / stores ».
2. **DTO en entrée, toujours.** Jamais de désérialisation directe dans une entité : un client pourrait envoyer `"id": 42` ou `"createdAt"` (*mass assignment*). En sortie : groupes ou DTO, mais un choix cohérent.
3. **Un `flush()` par cas d'usage**, dans le contrôleur ou le service applicatif, pas dans le repository.
4. **Requêtes dans les repositories**, pas de DQL dans les contrôleurs.
5. **`declare(strict_types=1)`, `final`, `readonly`** partout où c'est possible (comme dans le projet).
6. **Injecter des interfaces** (`EntityManagerInterface`, `LoggerInterface`), pas des implémentations.
7. **Tester par le HTTP** (`WebTestCase`) chaque endpoint : cas nominal + un cas d'erreur par code du contrat.
8. **Lire la doc de la bonne version** : <https://symfony.com/doc/7.4/> (beaucoup de tutos en ligne et de réponses d'IA datent de Symfony 3/4, avec annotations, `services.yml`, `AppBundle`… à ignorer).
