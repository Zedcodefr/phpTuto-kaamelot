# 1. ORM, Unit of Work et mapping

> Prérequis : [visite du projet Symfony](../02-symfony/03-visite-du-projet.md).

- [1.1 Doctrine en une image](#11-doctrine-en-une-image)
- [1.2 EntityManager ≈ DbContext](#12-entitymanager--dbcontext)
- [1.3 L'Identity Map](#13-lidentity-map)
- [1.4 L'Unit of Work et les états d'une entité](#14-lunit-of-work-et-les-états-dune-entité)
- [1.5 Transactions](#15-transactions)
- [1.6 Le mapping par attributs](#16-le-mapping-par-attributs)
- [1.7 Réagir aux changements : callbacks, listeners, subscribers](#17-réagir-aux-changements--callbacks-listeners-subscribers)
- [1.8 Migrations](#18-migrations)
- [1.9 Fixtures](#19-fixtures)
- [1.10 Repositories](#110-repositories)

---

## 1.1 Doctrine en une image

Doctrine est en fait **deux** bibliothèques empilées :

```
 Votre code ──▶  ORM   (entités, EntityManager, Unit of Work, DQL)        ≈ EF Core
                  │
                  ▼
                DBAL  (connexion, requêtes SQL, types, schéma)           ≈ ADO.NET + Dapper léger
                  │
                  ▼
                 PDO  ──▶ PostgreSQL                                     (ce que vous avez utilisé en partie 1)
```

### Data Mapper, pas Active Record

| Pattern | Exemple | L'entité sait-elle se sauvegarder ? |
|---|---|---|
| **Active Record** | Eloquent (Laravel), Rails | Oui : `$book->save()` |
| **Data Mapper** | **Doctrine**, EF Core, Hibernate | Non : c'est l'`EntityManager` qui s'en charge |

Une entité Doctrine est un **objet PHP ordinaire** : pas de classe de base, pas de dépendance à la base de
données. Vous pouvez faire `new Book(...)` dans un test unitaire sans base. Le mapping (quelle table, quelle colonne)
est décrit à part, dans des **attributs** PHP.

---

## 1.2 EntityManager ≈ DbContext

| EF Core | Doctrine | Commentaire |
|---|---|---|
| `DbContext` | `EntityManagerInterface` | Injecté par autowiring, **un par requête HTTP** (modèle *share nothing*) |
| `context.Books.Add(book)` | `$em->persist($book)` | « Suis cet objet », **aucun SQL** |
| `context.SaveChanges()` | `$em->flush()` | Calcule les différences et exécute **tout** le SQL dans une transaction |
| `context.Books.Remove(book)` | `$em->remove($book)` | Programme un `DELETE` pour le prochain `flush()` |
| `context.Books.Find(id)` | `$em->find(Book::class, $id)` ou `$repo->find($id)` | Passe d'abord par l'Identity Map |
| `context.Entry(book).Reload()` | `$em->refresh($book)` | Relit la ligne en base, écrase l'objet en mémoire |
| `context.Entry(book).State = Detached` | `$em->detach($book)` | L'objet n'est plus suivi |
| `context.ChangeTracker.Clear()` | `$em->clear()` | Oublie **toutes** les entités suivies |
| `context.Entry(book).State` | `$em->getUnitOfWork()->getEntityState($book)` | Rarement utile hors débogage |
| `context.Set<Book>()` / `DbSet<Book>` | `$em->getRepository(Book::class)` ou `BookRepository` injecté | Voir [1.10](#110-repositories) |

> ⚠️ Différence importante : EF Core a `AsNoTracking()` par requête. Doctrine **suit toujours** les entités
> qu'il hydrate. Pour des lectures sans suivi, on demande des **tableaux** ou des **DTO** à la place des entités
> (voir [chapitre 3](03-dql-et-query-builder.md#36-modes-dhydratation)).

---

## 1.3 L'Identity Map

**Une ligne en base = un seul objet PHP** pendant toute la durée de vie de l'`EntityManager`.

```php
$a = $em->find(Author::class, 1);   // SELECT ... WHERE id = 1
$b = $em->find(Author::class, 1);   // aucune requête : déjà dans l'Identity Map

var_dump($a === $b);                 // true : c'est LE MÊME objet

$c = $authorRepository->findOneBy(['name' => 'Victor Hugo']);
var_dump($a === $c);                 // true aussi (une requête est exécutée, mais l'objet existant est réutilisé)
```

Conséquences :

- `find()` par clé primaire est **gratuit** la deuxième fois ; `findOneBy()` et les requêtes DQL interrogent
  toujours la base, puis **réutilisent** les objets déjà connus ;
- si vous modifiez une entité en mémoire puis la relisez par une requête, vous **récupérez votre version modifiée**,
  pas celle de la base. Pour vérifier ce qui a réellement été écrit (dans un test), on fait `$em->clear()` avant de relire.
  C'est ce que fait `forgetEverything()` dans les tests du kata.

---

## 1.4 L'Unit of Work et les états d'une entité

L'**Unit of Work** (UoW) est la partie de l'`EntityManager` qui retient ce qu'il faudra écrire au prochain
`flush()`. Une entité est toujours dans l'un de ces quatre états :

```
                 new Book(...)
                      │
                      ▼
               ┌─────────────┐
               │     NEW     │   inconnue de Doctrine
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

### Le change tracking est implicite

Au `flush()`, Doctrine compare chaque entité **managed** avec la copie de ses valeurs prise au chargement
(*snapshot*). Toute différence devient un `UPDATE`.

```php
$task = $taskRepository->find(3);  // MANAGED
$task->rename('Nouveau titre');
$em->flush();                      // UPDATE task SET title = ... WHERE id = 3
// ❌ $em->persist($task) est inutile : l'entité est déjà suivie
```

C'est exactement ce que fait `TaskController::update()` dans le projet.

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
**un** `flush()` à la fin de l'action du contrôleur ou du service. Jamais de `flush()` dans une boucle.

> 💡 PHP ne garde rien d'une requête à l'autre : l'Unit of Work démarre **vide** à chaque requête HTTP.
> Pas de « DbContext qui vit trop longtemps » comme dans une application WPF.

---

## 1.5 Transactions

`flush()` ouvre **lui-même** une transaction, exécute tous les `INSERT`/`UPDATE`/`DELETE`, puis `COMMIT`
(ou `ROLLBACK` si une requête échoue). Dans 95 % des cas, vous n'avez rien à faire.

Pour regrouper plusieurs `flush()` ou mélanger ORM et SQL brut dans **une** transaction :

```php
$em->wrapInTransaction(function (EntityManagerInterface $em) use ($book): void {
    $em->getConnection()->executeStatement('UPDATE kata_book SET pages = pages + 1 WHERE id = ?', [$book->getId()]);
    $book->setOutOfPrint(true);
    $em->flush();
}); // COMMIT à la fin, ROLLBACK et exception relancée en cas d'erreur
```

≈ `using var tx = context.Database.BeginTransaction(); ... tx.Commit();` en EF Core.

---

## 1.6 Le mapping par attributs

Relisez [`src/Kata/Entity/Book.php`](../../apps/api-symfony/src/Kata/Entity/Book.php) en parallèle de cette section.

### Entité et table

```php
#[ORM\Entity(repositoryClass: BookRepository::class)]   // c'est une entité, son repository est BookRepository
#[ORM\Table(name: 'kata_book')]                          // facultatif : sinon le nom vient de la classe (book)
#[ORM\Index(columns: ['published_at'])]                  // index supplémentaire
#[ORM\UniqueConstraint(columns: ['title', 'author_id'])] // contrainte d'unicité sur plusieurs colonnes
class Book
```

Avec la stratégie de nommage du projet (`underscore`), la propriété `publishedAt` devient la colonne `published_at`.

### Clé primaire

```php
#[ORM\Id]
#[ORM\GeneratedValue]     // PostgreSQL : colonne IDENTITY (configuré dans doctrine.yaml)
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

#[ORM\Column(length: 20, enumType: TaskStatus::class)]  // VARCHAR, mais vous manipulez l'enum PHP
private TaskStatus $status;

#[ORM\Column(type: Types::DATE_IMMUTABLE)]         // DATE (sans heure)
private \DateTimeImmutable $publishedAt;

#[ORM\Column]                                      // TIMESTAMP(0) WITHOUT TIME ZONE (déduit de \DateTimeImmutable)
private \DateTimeImmutable $createdAt;
```

| Type PHP | Type Doctrine déduit | PostgreSQL |
|---|---|---|
| `int` | `integer` | `INT` |
| `string` | `string` (préciser `length`) | `VARCHAR(n)` |
| `bool` | `boolean` | `BOOLEAN` |
| `float` | `float` | `DOUBLE PRECISION` |
| `\DateTimeImmutable` | `datetime_immutable` | `TIMESTAMP(0)` |
| `array` | `json` | `JSON` |
| enum *backed* | `string` / `integer` + `enumType` | `VARCHAR` / `INT` |

Autres types utiles, à préciser avec `type:` : `Types::TEXT`, `Types::SMALLINT`, `Types::DECIMAL`
(revient en **string** pour ne pas perdre de précision, comme un `decimal` C#), `Types::DATE_IMMUTABLE`, `Types::GUID`.

> ⚠️ **Toujours les variantes `_IMMUTABLE`.** Avec un `\DateTime` mutable, `$task->getDueAt()->modify('+1 day')`
> modifie l'objet **sans que Doctrine le voie** : il compare les références d'objets, pas les valeurs. Avec
> `\DateTimeImmutable`, `modify()` renvoie un nouvel objet qu'il faut réaffecter, et le changement est détecté.

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
ne sert qu'à **votre** code, donc vous pouvez y imposer les champs obligatoires.

### Embeddables (pour info)

`#[ORM\Embeddable]` / `#[ORM\Embedded]` permettent de ranger plusieurs colonnes dans un *value object*
(ex. une `Address` avec `street`, `city`). C'est l'équivalent des *owned types* d'EF Core. Nous n'en avons pas
besoin dans le projet.

---

## 1.7 Réagir aux changements : callbacks, listeners, subscribers

Besoin typique (ticket **B3**) : renseigner `updatedAt` à chaque modification et `completedAt` quand une tâche
passe à `done`. Trois mécanismes, du plus simple au plus découplé :

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

C'est le bon choix pour B3 : la logique est isolée, testable, et `hasChangedField()` dit **ce qui** a changé.

### c) Doctrine listener : pour toutes les entités

`#[AsDoctrineListener(event: Events::onFlush)]` réagit à un événement global (ex. un journal d'audit de
toutes les modifications). Puissant mais plus délicat ; on n'en a pas besoin ici.

| Événement | Quand |
|---|---|
| `prePersist` / `postPersist` | Avant / après l'INSERT d'une nouvelle entité |
| `preUpdate` / `postUpdate` | Avant / après l'UPDATE (seulement s'il y a des changements) |
| `preRemove` / `postRemove` | Avant / après le DELETE |
| `onFlush` | Au début du flush, accès à tout l'Unit of Work |

> ⚠️ Dans `preUpdate`, modifier une **propriété simple** de l'entité concernée fonctionne. Modifier une **autre**
> entité ou une relation n'est pas pris en compte dans ce flush. Restez simple.
>
> ⚠️ Les requêtes `UPDATE`/`DELETE` en DQL ([chapitre 3](03-dql-et-query-builder.md#39-update-et-delete-en-dql))
> **ne déclenchent aucun** de ces événements.

---

## 1.8 Migrations

| EF Core | Doctrine / Symfony | Makefile |
|---|---|---|
| `dotnet ef migrations add X` | `make:migration` | `make sf-migration` |
| `dotnet ef database update` | `doctrine:migrations:migrate` | `make sf-migrate` |
| `dotnet ef migrations list` | `doctrine:migrations:status` / `:list` | `make sf c="doctrine:migrations:status"` |
| `dotnet ef database update <Précédente>` | `doctrine:migrations:migrate prev` | `make sf c="doctrine:migrations:migrate prev"` |
| Snapshot `ModelSnapshot.cs` | ❌ aucun : Doctrine compare le mapping à la **base réelle** | |

### Comment `make:migration` fonctionne

```
mapping des entités  ─┐
                      ├──▶ différence ──▶ migrations/VersionAAAAMMJJHHMMSS.php (up() / down())
schéma de la base    ─┘
```

Il n'y a **pas de snapshot** : la migration est la différence entre vos entités et **votre base locale**.
Conséquences :

1. si votre base n'est pas à jour (migrations des collègues non exécutées), la migration générée va
   **recréer leurs changements**. Toujours faire `make sf-migrate` **avant** `make sf-migration` ;
2. si vous avez bricolé la base à la main, ces bricolages apparaîtront dans la migration ;
3. **tout** ce qui est mappé dans `src/` sans être encore migré y entre aussi : une entité d'un autre ticket commencée
   sur votre branche, ou le travail des collègues si vous partagez une base. Relisez et retirez ce qui n'est pas à vous,
   ou ciblez des tables : `make sf c="doctrine:migrations:diff --filter-expression=/^comment/"` (filtre sur les noms de tables).

### Le cycle de travail

```bash
# 1. modifier l'entité (à la main ou avec make sf-entity)
make sf-migrate        # 2. base à jour avec les migrations existantes
make sf-migration      # 3. générer
# 4. RELIRE le fichier généré : le SQL fait-il exactement ce que vous voulez ?
make sf-migrate        # 5. appliquer
make sf c="doctrine:schema:validate"   # 6. "mapping correct" ET "base synchronisée"
```

Pensez à **remplir `getDescription()`** dans la migration : `doctrine:migrations:status` l'affiche.

### Travailler à trois sans se marcher dessus

Les trois devs back vont modifier `Task` en même temps (B1 : `project`, B2 : `author`/`assignee`, B3 : `updatedAt`…).
Règles :

| Règle | Pourquoi |
|---|---|
| Une migration **par ticket**, dans la PR du ticket | On sait d'où vient chaque changement de schéma |
| Avant de générer : `git rebase` sur la branche principale **puis** `make sf-migrate` | Sinon votre migration contient les changements des autres |
| Ne **jamais** modifier une migration déjà mergée | Les collègues l'ont déjà exécutée : ils ne la rejoueront pas |
| Conflit après rebase ? Supprimer **votre** migration, `make sf-db-reset`, regénérer | Plus fiable que fusionner deux diffs à la main |
| Les noms (timestamp) déterminent l'ordre | Deux migrations le même jour ne posent pas de problème si elles touchent des colonnes différentes |

> 💡 `make sf-db-reset` reconstruit la base de zéro (drop, migrations, fixtures). En cas de doute, c'est le reset
> qui fait foi : si votre branche ne passe pas un `sf-db-reset`, elle ne passera pas chez les autres.

---

## 1.9 Fixtures

Les fixtures sont les **données de démonstration** (≈ `HasData()` ou un *seeder* EF Core), chargées par
`make sf-db-reset`. Chaque classe de `src/DataFixtures/` est une fixture.

### Séparer les fixtures par domaine

Pour travailler à trois sans conflits, **une classe par domaine** : `TaskFixtures` (existante, `AppFixtures`),
`ProjectFixtures` (B1), `UserFixtures` (B2), `CommentFixtures` (B3).

### Ordre et références

Une tâche a besoin d'un projet et d'un utilisateur : leurs fixtures doivent passer **avant**. On le déclare, et
on partage les objets avec des **références** :

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

## 1.10 Repositories

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

C'est un **service** : on l'injecte par son type, comme n'importe quel service Symfony.

| Méthode héritée | Exemple | SQL |
|---|---|---|
| `find($id)` | `$repo->find(3)` | `WHERE id = 3` (ou rien, si déjà en mémoire) |
| `findAll()` | `$repo->findAll()` | pas de tri garanti ! |
| `findBy(critères, tri, limite, offset)` | `$repo->findBy(['outOfPrint' => false], ['title' => 'ASC'], 10)` | `WHERE ... ORDER BY ... LIMIT 10` |
| `findOneBy(critères)` | `$repo->findOneBy(['name' => 'policier'])` | `null` si rien |
| `count(critères)` | `$repo->count(['outOfPrint' => true])` | `SELECT COUNT(*)` |

Les critères de `findBy` sont des **égalités** sur des propriétés (un tableau de valeurs donne un `IN`). Dès qu'il
faut une jointure, un `LIKE`, un `>`, un agrégat… on écrit une **méthode dédiée** dans le repository, avec le
QueryBuilder ou le DQL : c'est le sujet du [chapitre 3](03-dql-et-query-builder.md).

> 💡 Bonne pratique : le contrôleur appelle des méthodes **nommées métier** (`findOpenTasksOfProject($project)`)
> et ne construit jamais de requête lui-même. Les requêtes vivent dans les repositories.

---

➡️ Suite : [2. Relations](02-relations.md)
