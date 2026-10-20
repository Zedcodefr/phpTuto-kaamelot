# 2. Relations

> Relisez les quatre entités de [`src/Kata/Entity/`](../../apps/api-symfony/src/Kata/Entity/)
> pendant ce chapitre : elles illustrent chaque notion.

- [2.1 Les quatre types de relations](#21-les-quatre-types-de-relations)
- [2.2 Côté propriétaire, côté inverse : LE piège](#22-côté-propriétaire-côté-inverse--le-piège)
- [2.3 Garder les deux côtés synchronisés en mémoire](#23-garder-les-deux-côtés-synchronisés-en-mémoire)
- [2.4 Les collections](#24-les-collections)
- [2.5 Cascade, orphanRemoval et onDelete](#25-cascade-orphanremoval-et-ondelete)
- [2.6 Chargement : lazy, eager, extra lazy](#26-chargement--lazy-eager-extra-lazy)
- [2.7 Le problème N+1](#27-le-problème-n1)
- [2.8 Relations et sérialisation JSON](#28-relations-et-sérialisation-json)
- [2.9 Application au backlog](#29-application-au-backlog)

---

## 2.1 Les quatre types de relations

```
┌──────────┐ 1      * ┌──────────┐ 1      * ┌──────────┐
│  Author  │──────────│   Book   │──────────│  Review  │
└──────────┘ books    └────┬─────┘ reviews  └──────────┘
                           │ *
                           │ tags (table de jointure kata_book_tag)
                           │ *
                      ┌────┴─────┐
                      │   Tag    │
                      └──────────┘
```

| Relation | Exemple du kata | EF Core | Clé étrangère |
|---|---|---|---|
| `ManyToOne` | `Book::$author` (plusieurs livres → un auteur) | `public Author Author { get; set; }` | `kata_book.author_id` |
| `OneToMany` | `Author::$books` (l'inverse du précédent) | `ICollection<Book> Books` | aucune (c'est l'autre côté) |
| `ManyToMany` | `Book::$tags` ↔ `Tag::$books` | `ICollection<Tag>` avec *skip navigation* | table `kata_book_tag` |
| `OneToOne` | (ex. `User` ↔ `UserProfile`) | `HasOne().WithOne()` | sur le côté propriétaire |

```php
// Book : ManyToOne — c'est ici qu'est la colonne author_id
#[ORM\ManyToOne(inversedBy: 'books')]
#[ORM\JoinColumn(nullable: false)]
private Author $author;

// Author : OneToMany — simple "vue" de la relation, mappedBy pointe vers la propriété de Book
#[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'author')]
private Collection $books;
```

> 💡 Une relation n'a **pas besoin** d'être bidirectionnelle. Si vous n'avez jamais besoin de
> `$project->getTasks()`, ne déclarez que `Task::$project` (ManyToOne) : moins de code, moins de pièges.
> On peut toujours retrouver les tâches d'un projet par une requête.

---

## 2.2 Côté propriétaire, côté inverse : LE piège

Dans une relation bidirectionnelle, il y a **deux** propriétés PHP mais **une seule** clé étrangère en base.
Doctrine doit savoir laquelle des deux fait foi :

| | Côté **propriétaire** (*owning side*) | Côté **inverse** (*inverse side*) |
|---|---|---|
| Attribut | `inversedBy: '...'` (ou rien) | `mappedBy: '...'` |
| ManyToOne / OneToMany | **toujours** le ManyToOne (`Book::$author`) | le OneToMany (`Author::$books`) |
| ManyToMany | celui que vous choisissez (`Book::$tags`) | l'autre (`Tag::$books`) |
| Lu par Doctrine au `flush()` | ✅ **oui** | ❌ **jamais** |

```php
$murakami = $authorRepository->findOneBy(['name' => 'Haruki Murakami']);
$atwood   = $authorRepository->findOneBy(['name' => 'Margaret Atwood']);
$book     = $bookRepository->findOneBy(['title' => '1Q84']);

// ❌ Modifie seulement le côté inverse : RIEN n'est écrit en base
$murakami->getBooks()->removeElement($book);
$atwood->getBooks()->add($book);
$em->flush();   // aucun UPDATE !

// ✅ Modifie le côté propriétaire : UPDATE kata_book SET author_id = ...
$book->setAuthor($atwood);
$em->flush();
```

En EF Core, le *relationship fixup* synchronise souvent les deux côtés pour vous. **Doctrine ne le fait pas.**
C'est la source n°1 de « j'ai modifié la relation et rien ne se passe ».

> Moyen mnémotechnique : **le propriétaire est celui qui a la clé étrangère dans sa table.**

---

## 2.3 Garder les deux côtés synchronisés en mémoire

Même si seul le côté propriétaire compte pour la base, les deux côtés doivent rester cohérents **en mémoire**
pendant la requête (sinon `$author->getBooks()` renvoie une liste fausse jusqu'au prochain chargement).
La convention est d'écrire des méthodes qui mettent à jour les deux côtés, comme dans le kata :

```php
// Book.php
public function setAuthor(Author $author): void
{
    if (isset($this->author)) {
        $this->author->syncBook($this, false);   // retirer de l'ancien auteur
    }
    $this->author = $author;                     // côté propriétaire
    $author->syncBook($this, true);              // côté inverse, en mémoire
}

// Review.php : le constructeur ajoute la critique au livre
public function __construct(private Book $book, /* ... */)
{
    $book->addReview($this);
}
```

`make sf-entity` génère ce genre de méthodes (`addX`/`removeX`) quand vous créez une relation bidirectionnelle.

---

## 2.4 Les collections

| C# | Doctrine | Rôle |
|---|---|---|
| `ICollection<T>` | `Doctrine\Common\Collections\Collection<int, T>` | Le type à déclarer sur la propriété |
| `new List<T>()` | `new ArrayCollection()` | À créer **dans le constructeur** (entité neuve) |
| proxy de collection EF | `PersistentCollection` | Ce que Doctrine met à la place quand il charge l'entité |

```php
/** @var Collection<int, Review> */
#[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'book')]
private Collection $reviews;

public function __construct(/* ... */)
{
    $this->reviews = new ArrayCollection();   // ⚠️ obligatoire, sinon propriété non initialisée
}
```

Méthodes courantes : `add()`, `removeElement()`, `contains()`, `count()`, `isEmpty()`, `first()`,
`toArray()`, `getValues()` (liste réindexée), `map(fn)`, `filter(fn)`, `exists(fn)`, `forAll(fn)`.

> ⚠️ Ne réaffectez jamais la propriété (`$this->reviews = new ArrayCollection([...])`) sur une entité chargée :
> Doctrine perdrait le suivi. Modifiez la collection existante avec `add()` / `removeElement()` / `clear()`.

---

## 2.5 Cascade, orphanRemoval et onDelete

Trois mécanismes qui se ressemblent mais agissent à des endroits différents :

| Option | Où | Effet | Exemple du kata |
|---|---|---|---|
| `cascade: ['persist']` | ORM (PHP) | `persist($book)` persiste aussi les **nouvelles** entités de la relation | `Book::$reviews` : une `new Review(...)` ajoutée à un livre suivi est insérée au flush sans `persist()` |
| `cascade: ['remove']` | ORM (PHP) | `remove($book)` appelle aussi `remove()` sur chaque élément (charge tout en mémoire !) | non utilisé |
| `orphanRemoval: true` | ORM (PHP) | un élément **retiré de la collection** est supprimé en base ; supprimer le parent supprime aussi ses enfants | `Book::$reviews` : `$book->removeReview($r)` + `flush()` = `DELETE` |
| `#[ORM\JoinColumn(onDelete: 'CASCADE')]` | **Base de données** | la contrainte SQL supprime les lignes filles | posé automatiquement sur la table de jointure `kata_book_tag` |

Sans aucune de ces options, `remove($parent)` sur une entité qui a encore des enfants échoue : violation de clé
étrangère (par exemple `remove($author)` sur un auteur qui a des livres, puisque `kata_book.author_id` le référence). C'est exactement le cas « supprimer un projet qui a encore des tâches → 409 » du ticket B1 :
on **vérifie** avant de supprimer, on ne cascade pas aveuglément.

> 💡 Doctrine crée automatiquement un **index** sur chaque colonne de clé étrangère (`author_id`, `book_id`…).
> Vérifiez-le avec `make sf-psql` puis `\d kata_book`.

---

## 2.6 Chargement : lazy, eager, extra lazy

Par défaut, les relations sont **LAZY** : chargées au premier accès.

```php
$book = $bookRepository->find(1);   // SELECT ... FROM kata_book WHERE id = 1
$book->getAuthor();                 // pas de requête : Author est un objet "fantôme" (lazy object), seul son id est connu
$book->getAuthor()->getName();      // SELECT ... FROM kata_author WHERE id = ?   ← chargé maintenant
$book->getReviews()->count();       // SELECT ... FROM kata_review WHERE book_id = ?
```

C'est l'équivalent de `UseLazyLoadingProxies()` en EF Core, sauf qu'en Doctrine c'est **actif par défaut**.
Avec PHP 8.4, le projet utilise les *lazy objects* natifs du langage (`enable_native_lazy_objects: true`) :
l'objet fantôme **est** un vrai `Author`, qui se remplit tout seul au premier accès à une propriété.

| `fetch:` | Comportement | Usage |
|---|---|---|
| `LAZY` (défaut) | Chargé au premier accès | Presque toujours |
| `EAGER` | Toujours chargé avec l'entité | À éviter : impossible de s'en passer quand on n'en a pas besoin |
| `EXTRA_LAZY` | Sur une collection, `count()`, `contains()`, `slice()` font une requête ciblée sans tout charger | Grosses collections, **une** entité à la fois : sur une liste, un `count()` par élément = N+1 (comptez alors dans la requête, chapitre 3) |

La bonne façon de charger une relation **quand on en a besoin** n'est pas `EAGER` mais le **fetch join**, dans la
requête qui en a besoin (chapitre 3).

---

## 2.7 Le problème N+1

```php
$books = $bookRepository->findAll();             // 1 requête : les 9 livres
foreach ($books as $book) {
    echo $book->getAuthor()->getName();          // + 1 requête par auteur pas encore chargé
    echo count($book->getReviews());             // + 1 requête par livre
}
// Total : 1 + 4 + 9 = 14 requêtes pour afficher une liste
```

Avec 9 livres, ça passe. Avec 500 tâches dans `GET /api/tasks`, c'est 500 requêtes. Le **profiler** le montre
immédiatement : onglet Doctrine, des dizaines de requêtes identiques à un paramètre près.

### La solution : le fetch join

On demande à Doctrine de charger la relation **dans la même requête**, avec `addSelect()` :

```php
$books = $this->createQueryBuilder('b')
    ->addSelect('a')            // ← "et remplis aussi les auteurs avec ces colonnes"
    ->join('b.author', 'a')
    ->getQuery()
    ->getResult();              // 1 seule requête, auteurs inclus
```

```sql
SELECT k0_.id, k0_.title, ..., k1_.id, k1_.name, k1_.country
FROM kata_book k0_ INNER JOIN kata_author k1_ ON k0_.author_id = k1_.id
```

Sans `addSelect('a')`, la jointure sert seulement à **filtrer** (`WHERE a.country = ...`) : les auteurs ne sont pas
chargés, et le N+1 reste. C'est une nuance essentielle, travaillée dans le kata (K06).

> Pour savoir si une collection a été chargée : `$book->getReviews()->isInitialized()` (sur une `PersistentCollection`).

---

## 2.8 Relations et sérialisation JSON

Sérialiser une entité avec des relations bidirectionnelles, c'est demander une boucle infinie :
`Book → author → books → author → …` Le Serializer Symfony lève alors une
`CircularReferenceException`. Et même sans boucle, il déclencherait du lazy loading partout (N+1 caché dans le JSON).

Deux solutions (déjà vues dans [les particularités de Symfony](../02-symfony/02-particularites.md)) :

1. **Groupes de sérialisation** : `#[Groups(['task:read'])]` sur les propriétés à exposer, et
   `$this->json($task, context: ['groups' => 'task:read'])`. La relation inverse n'est pas dans le groupe.
2. **DTO de sortie** : une classe `TaskOutput` construite à partir de l'entité, qui ne contient que ce que le
   contrat d'API demande (ex. `project: { id, name }`). Plus explicite, recommandé quand la forme JSON s'éloigne
   de l'entité.

Le contrat d'API demande justement des formes réduites (`"project": { "id": 1, "name": "..." }`,
`"author": User`) : pensez-y dès le mapping.

### Relations auto-référencées (pour info)

Une entité peut pointer vers elle-même (`Comment::$parent` pour des réponses, `Category::$parent` pour un arbre) :
c'est un ManyToOne/OneToMany ordinaire dont la cible est la même classe. Pas nécessaire dans le backlog.

---

## 2.9 Application au backlog

| Ticket | Relation à créer | Côté propriétaire | Points d'attention |
|---|---|---|---|
| **B1** Projets | `Task::$project` ManyToOne → `Project`, `nullable: true` | `Task` | Inverse `Project::$tasks` utile pour `taskCount` ? Une requête `COUNT` suffit aussi. `DELETE` d'un projet non vide → 409, pas de cascade |
| **B2** Utilisateurs | `Task::$author` et `Task::$assignee` : **deux** ManyToOne vers `User` | `Task` | Deux relations vers la même entité : si vous les rendez bidirectionnelles, il faut deux collections inverses distinctes (`createdTasks`, `assignedTasks`). Unidirectionnel conseillé. `ApiToken` ManyToOne → `User` |
| **B3** Commentaires | `Comment::$task` ManyToOne → `Task`, `Comment::$author` ManyToOne → `User` (nullable au départ) | `Comment` | Unidirectionnel conseillé (pas de `Task::$comments` : `Task` est un fichier partagé). Supprimer une tâche doit supprimer ses commentaires : `onDelete: 'CASCADE'` sur `Comment::$task` est le plus simple. `commentCount` sans N+1 |

---

➡️ Suite : [3. DQL et QueryBuilder](03-dql-et-query-builder.md)
