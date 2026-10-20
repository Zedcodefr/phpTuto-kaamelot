# 1. ORM, Unit of Work et mapping

> Prérequis : [Symfony](../03-symfony/README.md) et le chapitre SQL de l'étape 1
> ([SQL et bases relationnelles](../01-fondamentaux/02-sql-et-bases-relationnelles.md)).

- [1.1 Pourquoi un ORM](#11-pourquoi-un-orm)
- [1.2 Doctrine en une image](#12-doctrine-en-une-image)
- [1.3 L'EntityManager : surtout pas un store Pinia](#13-lentitymanager--surtout-pas-un-store-pinia)
- [1.4 L'Identity Map](#14-lidentity-map)
- [1.5 L'Unit of Work et les états d'une entité](#15-lunit-of-work-et-les-états-dune-entité)
- [1.6 Transactions](#16-transactions)
- [1.7 Le mapping par attributs](#17-le-mapping-par-attributs)
- [1.8 Réagir aux changements : callbacks et listeners](#18-réagir-aux-changements--callbacks-et-listeners)
- [1.9 Migrations](#19-migrations)
- [1.10 Fixtures](#110-fixtures)
- [1.11 Repositories](#111-repositories)

---

## 1.1 Pourquoi un ORM

Souvenez-vous de l'étape 2 (API en PHP pur). Pour chaque opération, vous avez écrit :

```php
$statement = $pdo->prepare('SELECT * FROM task WHERE id = :id');   // 1. le SQL, à la main
$statement->execute(['id' => $id]);
$row = $statement->fetch();                                          // 2. une ligne = un tableau associatif
return $row === false ? null : Task::fromRow($row);                  // 3. tableau → objet, à la main
```

Et l'inverse pour écrire (`INSERT ... RETURNING *`, `UPDATE ... SET ...`). Avec trois entités et leurs relations,
ce code de « traduction » entre **lignes SQL** et **objets** devient vite la moitié de l'application.

Un **ORM** (*Object-Relational Mapper*) fait cette traduction pour vous :

| Étape 2 (PHP pur + PDO) | Avec Doctrine |
|---|---|
| `SELECT * FROM task WHERE id = :id` + `Task::fromRow()` | `$taskRepository->find($id)` |
| `INSERT INTO task (...) VALUES (...) RETURNING *` | `$em->persist($task); $em->flush();` |
| `UPDATE task SET title = :title ... WHERE id = :id` | `$task->rename('...'); $em->flush();` |
| `schema.sql` écrit à la main | **migrations** générées à partir des entités |
| `Task::jsonSerialize()` | (rôle du Serializer de Symfony, pas de Doctrine) |

Mais un ORM ne vous dispense **pas** de connaître le SQL : il en génère pour vous, et c'est à vous de vérifier
qu'il génère le **bon**. C'est tout l'objet de cette étape.

---

## 1.2 Doctrine en une image

Doctrine est en fait **deux** bibliothèques empilées :

```
 Votre code ──▶  ORM   (entités, EntityManager, Unit of Work, DQL)
                  │
                  ▼
                DBAL  (connexion, requêtes SQL, types, schéma)
                  │
                  ▼
                 PDO  ──▶ PostgreSQL           (ce que vous avez utilisé à l'étape 2)
```

### Comparaison avec les ORM JavaScript

| | Doctrine | Prisma | TypeORM |
|---|---|---|---|
| Où décrit-on le modèle ? | **Attributs PHP** sur des classes (`#[ORM\Column]`) | Fichier `schema.prisma` (*schema-first*), client généré | **Décorateurs** TS sur des classes (`@Column()`) |
| Pattern | **Data Mapper** | Client de requêtes | Active Record **ou** Data Mapper |
| Quand part le SQL ? | Lectures : tout de suite. Écritures : **au `flush()`** | À chaque appel (`await prisma.task.create(...)`) | À chaque `save()` |
| Suivi des objets en mémoire | **Oui** (Unit of Work, Identity Map) | Non : objets JS simples | Partiel |
| Migrations | Générées en comparant entités et **base réelle** | `prisma migrate dev` (compare au schéma) | `migration:generate` |

Les attributs Doctrine ressemblent beaucoup aux décorateurs TypeORM :

```ts
// TypeORM
@Entity('task')
export class Task {
  @PrimaryGeneratedColumn() id!: number
  @Column({ length: 255 }) title!: string
}
```

```php
// Doctrine
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task')]
class Task
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;
}
```

### Data Mapper, pas Active Record

| Pattern | Exemple | L'entité sait-elle se sauvegarder ? |
|---|---|---|
| **Active Record** | Eloquent (Laravel), Sequelize (`task.save()`), TypeORM en mode `BaseEntity` | Oui : `$task->save()` |
| **Data Mapper** | **Doctrine** | Non : c'est l'`EntityManager` qui s'en charge |

Une entité Doctrine est un **objet PHP ordinaire** : pas de classe de base, aucune connaissance de la base de
données. Vous pouvez faire `new Book(...)` dans un test sans base. Le mapping (quelle table, quelle colonne)
est décrit à part, dans des **attributs** PHP.

---

## 1.3 L'EntityManager : surtout pas un store Pinia

L'`EntityManager` (`EntityManagerInterface`, injecté par autowiring) est le point d'entrée de Doctrine.
Il garde en mémoire les entités qu'il a chargées ou qu'on lui a confiées. Si vous pensez « c'est un store
Pinia de mes entités », vous êtes à moitié dans le vrai… et la moitié fausse est dangereuse :

| Store Pinia | EntityManager Doctrine |
|---|---|
| Vit tant que l'onglet est ouvert | **Naît et meurt avec chaque requête HTTP** (modèle *share nothing* vu à l'étape 2) |
| **Réactif** : modifier l'état met la vue à jour | **Pas réactif** : modifier une entité ne fait **rien**… jusqu'au `flush()` |
| Source de vérité pour l'interface | La source de vérité, c'est **la base**. L'EntityManager n'est qu'un brouillon de travail |
| Partagé par tous les composants | Partagé par tous les services **de la même requête**, jamais entre deux requêtes ni entre deux utilisateurs |

Les opérations de base :

| Doctrine | Ce que ça fait | Équivalent Prisma / TypeORM |
|---|---|---|
| `$em->persist($task)` | « Suis cet objet, il faudra l'insérer. » **Aucun SQL** | — (pas d'équivalent : ils écrivent tout de suite) |
| `$em->flush()` | Calcule toutes les différences et exécute **tout** le SQL, dans une transaction : **c'est le commit** | `prisma.task.create()` / `repository.save()` |
| `$em->remove($task)` | Programme un `DELETE` pour le prochain `flush()` | `prisma.task.delete()` / `repository.remove()` |
| `$em->find(Task::class, $id)` ou `$repo->find($id)` | Lit une entité (en passant d'abord par l'Identity Map) | `prisma.task.findUnique({ where: { id } })` |
| `$em->refresh($task)` | Relit la ligne en base et écrase l'objet en mémoire | refaire un `findUnique` |
| `$em->clear()` | Oublie **toutes** les entités suivies | — |

> ⚠️ Doctrine **suit toujours** les entités qu'il charge. Pour des lectures sans suivi (une liste à renvoyer en
> JSON, par exemple), on demande des **tableaux** ou des **DTO** à la place des entités
> (voir [chapitre 3](03-dql-et-query-builder.md#37-modes-dhydratation)).

---

## 1.4 L'Identity Map

**Une ligne en base = un seul objet PHP** pour un même EntityManager.

```php
$a = $em->find(Author::class, 1);   // SELECT ... WHERE id = 1
$b = $em->find(Author::class, 1);   // aucune requête : déjà dans l'Identity Map

var_dump($a === $b);                 // true : c'est LE MÊME objet

$c = $authorRepository->findOneBy(['name' => 'Victor Hugo']);
var_dump($a === $c);                 // true aussi (une requête est exécutée, mais l'objet existant est réutilisé)
```

C'est un peu comme un store Pinia normalisé par id (`entities[id]`) : on ne garde jamais deux copies du même
auteur. Conséquences :

- `find()` par clé primaire est **gratuit** la deuxième fois ; `findOneBy()` et les requêtes DQL interrogent
  toujours la base, puis **réutilisent** les objets déjà connus ;
- si vous modifiez une entité en mémoire puis la relisez par une requête, vous **récupérez votre version modifiée**,
  pas celle de la base. Pour vérifier ce qui a vraiment été écrit (dans un test), on fait `$em->clear()` avant de
  relire. C'est ce que fait `forgetEverything()` dans les tests du kata.

---

## 1.5 L'Unit of Work et les états d'une entité

L'**Unit of Work** (UoW) est la partie de l'`EntityManager` qui retient ce qu'il faudra écrire au prochain
`flush()`. Une entité est toujours dans l'un de ces quatre états :

```
                 new Book(...)
                      │
                      ▼
               ┌─────────────┐
               │     NEW     │   inconnue de Doctrine (un simple objet PHP)
               └──────┬──────┘
                      │ persist()
                      ▼
 find(), DQL   ┌─────────────┐   flush() : INSERT / UPDATE
 ────────────▶ │   MANAGED   │ ◀────────────────────────────┐
               └──┬───────┬──┘                              │
         remove() │       │ detach() / clear()              │ persist()
                  ▼       ▼                                 │
         ┌─────────────┐ ┌─────────────┐                    │
         │   REMOVED   │ │  DETACHED   │                    │
         └──────┬──────┘ └─────────────┘                    │
                │ flush() : DELETE                          │
                ▼                                           │
            (supprimée) ────────────────────────────────────┘
```

### Le suivi des changements est implicite

Au `flush()`, Doctrine compare chaque entité **managed** avec une copie de ses valeurs prise au chargement
(*snapshot*). Toute différence devient un `UPDATE`.

```php
$task = $taskRepository->find(3);  // MANAGED
$task->rename('Nouveau titre');
$em->flush();                      // UPDATE task SET title = ... WHERE id = 3
// ❌ $em->persist($task) est inutile : l'entité est déjà suivie
```

C'est exactement ce que fait `TaskController::update()` dans le projet. Côté Vue, c'est le même réflexe qu'un
formulaire où l'on modifie l'objet puis on clique sur « Enregistrer » : ici, « Enregistrer », c'est `flush()`.

### Quand faut-il appeler `persist()` ?

Uniquement pour un objet **NEW** que vous voulez insérer, et seulement s'il n'est pas déjà atteint par une
relation configurée avec `cascade: ['persist']` (voir [chapitre 2](02-relations.md#25-cascade-orphanremoval-et-ondelete)).
Si vous oubliez, Doctrine vous le dit au `flush()` :

```
A new entity was found through the relationship 'App\Kata\Entity\Book#tags' that was not configured
to cascade persist operations for entity: App\Kata\Entity\Tag@... To solve this issue: Either explicitly call
EntityManager#persist() on this unknown entity or configure cascade persist this association in the mapping.
```

### Un seul `flush()` par requête HTTP

`flush()` est coûteux : il parcourt toutes les entités suivies. Règle de base : on modifie tout ce qu'il faut, puis
**un** `flush()` à la fin de l'action du contrôleur ou du service. Jamais de `flush()` dans une boucle (c'est
l'équivalent d'un `await api.save()` dans un `for` côté front : ça marche, mais c'est le mauvais réflexe).

---

## 1.6 Transactions

Rappel de l'étape 1 : une **transaction** garantit que plusieurs écritures réussissent toutes, ou aucune.

`flush()` ouvre **lui-même** une transaction, exécute tous les `INSERT`/`UPDATE`/`DELETE`, puis `COMMIT`
(ou `ROLLBACK` si une requête échoue). Dans la grande majorité des cas, vous n'avez rien à faire.

Pour regrouper plusieurs `flush()` ou mélanger ORM et SQL brut dans **une** transaction :

```php
$em->wrapInTransaction(function (EntityManagerInterface $em) use ($book): void {
    $em->getConnection()->executeStatement('UPDATE kata_book SET pages = pages + 1 WHERE id = ?', [$book->getId()]);
    $book->setOutOfPrint(true);
    $em->flush();
}); // COMMIT à la fin, ROLLBACK et exception relancée en cas d'erreur
```

≈ `prisma.$transaction(async (tx) => { ... })`.

---

## 1.7 Le mapping par attributs

Relisez [`src/Kata/Entity/Book.php`](../../../apps/api-symfony/src/Kata/Entity/Book.php) en parallèle de cette section.

### Entité et table

```php
#[ORM\Entity(repositoryClass: BookRepository::class)]   // c'est une entité, son repository est BookRepository
#[ORM\Table(name: 'kata_book')]                          // facultatif : sinon le nom vient de la classe (book)
#[ORM\Index(columns: ['published_at'])]                  // index supplémentaire
#[ORM\UniqueConstraint(columns: ['title', 'author_id'])] // contrainte d'unicité sur plusieurs colonnes
class Book
```

Avec la stratégie de nommage du projet (`underscore`), la propriété `publishedAt` devient la colonne `published_at` :
camelCase côté PHP (et JSON), snake_case côté SQL, comme avec `@map` dans Prisma.

### Clé primaire

```php
#[ORM\Id]
#[ORM\GeneratedValue]     // PostgreSQL : colonne IDENTITY (auto-incrément)
#[ORM\Column]
private ?int $id = null;  // null tant que l'entité n'a pas été flushée
```

### Colonnes

```php
#[ORM\Column(length: 255)]                         // VARCHAR(255) NOT NULL (le type vient du type PHP : string)
private string $title;

#[ORM\Column(type: Types::TEXT, nullable: true)]   // TEXT NULL
private ?string $description;

#[ORM\Column(length: 50, unique: true)]            // + index unique
private string $name;

#[ORM\Column(length: 20, enumType: TaskStatus::class)]  // VARCHAR en base, enum PHP dans votre code
private TaskStatus $status;

#[ORM\Column(type: Types::DATE_IMMUTABLE)]         // DATE (sans heure)
private \DateTimeImmutable $publishedAt;

#[ORM\Column]                                      // TIMESTAMP(0) (déduit de \DateTimeImmutable)
private \DateTimeImmutable $createdAt;
```

| Type PHP | Type Doctrine déduit | PostgreSQL | TypeScript |
|---|---|---|---|
| `int` | `integer` | `INT` | `number` |
| `string` | `string` (préciser `length`) | `VARCHAR(n)` | `string` |
| `bool` | `boolean` | `BOOLEAN` | `boolean` |
| `float` | `float` | `DOUBLE PRECISION` | `number` |
| `\DateTimeImmutable` | `datetime_immutable` | `TIMESTAMP(0)` | `Date` (ou une string ISO dans le JSON) |
| `array` | `json` | `JSON` | `object` |
| enum *backed* | `string` + `enumType` | `VARCHAR` | union de littéraux `'todo' \| 'done'` |

Autres types utiles, à préciser avec `type:` : `Types::TEXT`, `Types::SMALLINT`, `Types::DECIMAL`
(revient en **string** pour ne pas perdre de précision, comme quand on manipule des montants en JS),
`Types::DATE_IMMUTABLE`, `Types::GUID`.

> ⚠️ **Toujours les variantes `_IMMUTABLE`.** Avec un `\DateTime` mutable, `$task->getDueAt()->modify('+1 day')`
> modifie l'objet **sans que Doctrine le voie** : il compare les références d'objets, pas les valeurs. C'est le même
> piège qu'en Vue quand on mute un objet que le système de réactivité ne suit pas. Avec `\DateTimeImmutable`,
> `modify()` renvoie un **nouvel** objet qu'il faut réaffecter, et le changement est détecté.

### Attributs dans le constructeur

Le kata utilise la *constructor promotion* avec les attributs Doctrine directement sur les paramètres :

```php
public function __construct(
    #[ORM\Column(length: 100)]
    private string $name,

    #[ORM\Column(length: 50)]
    private string $country,
) {
    $this->books = new ArrayCollection();
}
```

C'est équivalent à déclarer les propriétés au-dessus du constructeur, en plus court. Doctrine n'appelle
**pas** le constructeur quand il hydrate une entité depuis la base : il crée l'objet par réflexion. Le constructeur
ne sert qu'à **votre** code, vous pouvez donc y imposer les champs obligatoires.

---

## 1.8 Réagir aux changements : callbacks et listeners

Besoin typique (ticket **B3**) : renseigner `updatedAt` à chaque modification et `completedAt` quand une tâche
passe à `done`. C'est un peu l'équivalent d'un `watch()` Vue… mais déclenché **au moment de l'écriture en base**.

### a) Lifecycle callbacks : dans l'entité

```php
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]          // obligatoire, sinon les méthodes ne sont jamais appelées
class Task
{
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
```

Simple, mais l'entité ne peut pas recevoir de services (pas d'injection), et c'est appelé pour **toute** modification.

### b) Entity listener : une classe dédiée à une entité

```php
#[AsEntityListener(event: Events::preUpdate, entity: Task::class)]
final class TaskTimestampsListener
{
    public function __construct(private readonly ClockInterface $clock) {}   // injection possible

    public function preUpdate(Task $task, PreUpdateEventArgs $args): void
    {
        if ($args->hasChangedField('status')) {
            // $args->getOldValue('status'), $args->getNewValue('status')
        }
    }
}
```

C'est le bon choix pour B3 : la logique est isolée, testable, et `hasChangedField()` dit **ce qui** a changé
(comme les valeurs `newValue` / `oldValue` d'un `watch`).

| Événement | Quand |
|---|---|
| `prePersist` / `postPersist` | Avant / après l'INSERT d'une nouvelle entité |
| `preUpdate` / `postUpdate` | Avant / après l'UPDATE (seulement s'il y a des changements) |
| `preRemove` / `postRemove` | Avant / après le DELETE |
| `onFlush` | Au début du flush, accès à tout l'Unit of Work (avancé) |

> ⚠️ Dans `preUpdate`, modifier une **propriété simple** de l'entité concernée fonctionne. Modifier une **autre**
> entité ou une relation n'est pas pris en compte dans ce flush. Restez simple.
>
> ⚠️ Les requêtes `UPDATE`/`DELETE` en DQL ([chapitre 3](03-dql-et-query-builder.md#310-update-et-delete-en-dql))
> **ne déclenchent aucun** de ces événements.

---

## 1.9 Migrations

Une migration est un fichier PHP qui fait évoluer le schéma de la base (`up()`) et sait revenir en arrière (`down()`).
C'est votre `schema.sql` de l'étape 2, mais versionné et incrémental.

| Prisma | Doctrine / Symfony | Makefile |
|---|---|---|
| `prisma migrate dev --name x` (génère + applique) | `make:migration` puis `doctrine:migrations:migrate` | `make sf-migration` puis `make sf-migrate` |
| `prisma migrate status` | `doctrine:migrations:status` | `make sf c="doctrine:migrations:status"` |
| `prisma migrate reset` | drop + migrate + fixtures | `make sf-db-reset` |
| compare au `schema.prisma` | compare les entités à **votre base locale réelle** | |

### Comment `make:migration` fonctionne

```
mapping des entités  ─┐
                      ├──▶ différence ──▶ migrations/VersionAAAAMMJJHHMMSS.php (up() / down())
schéma de la base    ─┘
```

La migration est la différence entre vos entités et **votre base locale**. Conséquences :

1. si votre base n'est pas à jour (migrations des collègues non exécutées), la migration générée va
   **recréer leurs changements**. Toujours faire `make sf-migrate` **avant** `make sf-migration` ;
2. si vous avez bricolé la base à la main (dans Adminer, par exemple), ces bricolages apparaîtront dans la migration ;
3. **tout** ce qui est mappé dans `src/` sans être encore migré y entre aussi : une entité d'un autre ticket commencée
   sur votre branche, ou le travail des collègues si vous partagez une base. Relisez et retirez ce qui n'est pas à vous,
   ou ciblez des tables : `make sf c="doctrine:migrations:diff --filter-expression=/^comment/"` (filtre sur les noms de tables).

### Le cycle de travail

```bash
# 1. modifier l'entité (à la main ou avec make sf-entity)
make sf-migrate        # 2. base à jour avec les migrations existantes
make sf-migration      # 3. générer
# 4. RELIRE le fichier généré : le SQL fait-il exactement ce que vous voulez ? (étape 1 : vous savez lire ce SQL)
make sf-migrate        # 5. appliquer
make sf c="doctrine:schema:validate"   # 6. "mapping correct" ET "base synchronisée"
```

Pensez à **remplir `getDescription()`** dans la migration : `doctrine:migrations:status` l'affiche.

### Travailler à trois sans se marcher dessus

Les trois devs back vont modifier `Task` en parallèle (B1 : `project`, B2 : `author`/`assignee`, B3 : `updatedAt`…).
Règles :

| Règle | Pourquoi |
|---|---|
| Une migration **par ticket**, dans la PR du ticket | On sait d'où vient chaque changement de schéma |
| Avant de générer : `git rebase` sur la branche principale **puis** `make sf-migrate` | Sinon votre migration contient les changements des autres |
| Ne **jamais** modifier une migration déjà mergée | Les collègues l'ont déjà exécutée : ils ne la rejoueront pas |
| Conflit après rebase ? Supprimer **votre** migration, `make sf-db-reset`, regénérer | Plus fiable que fusionner deux diffs à la main |
| Le nom (horodatage) détermine l'ordre d'exécution | Deux migrations proches ne posent pas de problème si elles touchent des colonnes différentes |

> 💡 `make sf-db-reset` reconstruit la base de zéro (drop, migrations, fixtures). En cas de doute, c'est le reset
> qui fait foi : si votre branche ne passe pas un `sf-db-reset`, elle ne passera pas chez les autres.

---

## 1.10 Fixtures

Les fixtures sont les **données de démonstration** (≈ `prisma db seed`), chargées par `make sf-db-reset`.
Chaque classe de `src/DataFixtures/` est une fixture.

### Une classe par domaine

Pour travailler à trois sans conflits : `AppFixtures` (tâches, existante), `ProjectFixtures` (B1),
`UserFixtures` (B2), `CommentFixtures` (B3).

### Ordre et références

Une tâche a besoin d'un projet : la fixture des projets doit passer **avant**. On le déclare, et on partage les
objets avec des **références** :

```php
final class ProjectFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $project = new Project('Formation PHP');
        $manager->persist($project);
        $this->addReference('project-formation', $project);   // le rendre disponible aux autres fixtures
        $manager->flush();
    }
}

final class AppFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [ProjectFixtures::class];                      // chargé AVANT moi
    }

    public function load(ObjectManager $manager): void
    {
        $project = $this->getReference('project-formation', Project::class);
        // new Task(..., project: $project)
    }
}
```

### Groupes

`KataFixtures` implémente `FixtureGroupInterface` et déclare le groupe `kata`. Ainsi `make sf-kata` ne charge
**que** les données du kata (`doctrine:fixtures:load --group=kata`) dans la base de test.

---

## 1.11 Repositories

```php
/** @extends ServiceEntityRepository<Book> */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }
}
```

C'est un **service** : on l'injecte par son type, comme n'importe quel service Symfony. Pensez-y comme au
module `api/tasks.ts` de votre front : **toutes** les requêtes sur une entité au même endroit, avec des noms métier.

| Méthode héritée | Exemple | SQL | Prisma |
|---|---|---|---|
| `find($id)` | `$repo->find(3)` | `WHERE id = 3` (ou rien, si déjà en mémoire) | `findUnique({ where: { id: 3 } })` |
| `findAll()` | `$repo->findAll()` | pas de tri garanti ! | `findMany()` |
| `findBy(critères, tri, limite, offset)` | `$repo->findBy(['outOfPrint' => false], ['title' => 'ASC'], 10)` | `WHERE ... ORDER BY ... LIMIT 10` | `findMany({ where, orderBy, take })` |
| `findOneBy(critères)` | `$repo->findOneBy(['name' => 'policier'])` | `null` si rien | `findFirst({ where })` |
| `count(critères)` | `$repo->count(['outOfPrint' => true])` | `SELECT COUNT(*)` | `count({ where })` |

Les critères de `findBy` sont des **égalités** sur des propriétés (un tableau de valeurs donne un `IN`). Dès qu'il
faut une jointure, un `LIKE`, un `>`, un agrégat… on écrit une **méthode dédiée** dans le repository, avec le
QueryBuilder ou le DQL : c'est le sujet du [chapitre 3](03-dql-et-query-builder.md).

> 💡 Bonne pratique : le contrôleur appelle des méthodes **nommées métier** (`findOpenTasksOfProject($project)`)
> et ne construit jamais de requête lui-même. Les requêtes vivent dans les repositories.

---

➡️ Suite : [2. Relations](02-relations.md)
