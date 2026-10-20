# 2. Relations

> Relisez les quatre entités de [`src/Kata/Entity/`](../../../apps/api-symfony/src/Kata/Entity/)
> pendant ce chapitre : elles illustrent chaque notion. Et gardez en tête le SQL de l'étape 1 : clés étrangères,
> tables de jointure, `JOIN`.

- [2.1 Les quatre types de relations](#21-les-quatre-types-de-relations)
- [2.2 Côté propriétaire, côté inverse : LE piège](#22-côté-propriétaire-côté-inverse--le-piège)
- [2.3 Garder les deux côtés synchronisés en mémoire](#23-garder-les-deux-côtés-synchronisés-en-mémoire)
- [2.4 Les collections (≠ tableaux JS)](#24-les-collections--tableaux-js)
- [2.5 Cascade, orphanRemoval et onDelete](#25-cascade-orphanremoval-et-ondelete)
- [2.6 Le chargement paresseux](#26-le-chargement-paresseux)
- [2.7 Le problème N+1](#27-le-problème-n1)
- [2.8 Relations et JSON : les références circulaires](#28-relations-et-json--les-références-circulaires)
- [2.9 Application au projet](#29-application-au-projet)

---

## 2.1 Les quatre types de relations

Côté front, une « relation », c'est un objet imbriqué dans le JSON : `task.project.name`. Côté base, c'est une
**clé étrangère** (une colonne qui contient l'id d'une autre ligne) ou une **table de jointure**. Doctrine fait le
lien entre les deux.

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

| Relation | Exemple du kata | En SQL (étape 1) | Prisma |
|---|---|---|---|
| `ManyToOne` | `Book::$author` (plusieurs livres → un auteur) | colonne `kata_book.author_id` + clé étrangère | `author Author @relation(fields: [authorId], ...)` |
| `OneToMany` | `Author::$books` (l'inverse du précédent) | **aucune colonne** : c'est l'autre côté | `books Book[]` |
| `ManyToMany` | `Book::$tags` ↔ `Tag::$books` | table `kata_book_tag (book_id, tag_id)` | relation implicite `tags Tag[]` |
| `OneToOne` | (ex. `User` ↔ `UserProfile`) | clé étrangère **unique** | `profile Profile?` |

```php
// Book : ManyToOne — c'est ici qu'est la colonne author_id
#[ORM\ManyToOne(inversedBy: 'books')]
#[ORM\JoinColumn(nullable: false)]
private Author $author;

// Author : OneToMany — simple "vue" de la relation, mappedBy pointe vers la propriété de Book
#[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'author')]
private Collection $books;
```

> 💡 Une relation n'a **pas besoin** d'être dans les deux sens. Si vous n'avez jamais besoin de
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

C'est la source n°1 de « j'ai modifié la relation et rien ne se passe ». Pour un dev Vue, c'est contre-intuitif :
dans un store, pousser un élément dans un tableau **suffit** à changer l'état. Ici, la vérité est dans une colonne
SQL, et seule la propriété qui correspond à cette colonne compte.

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

C'est la même idée qu'une *action* Pinia qui met à jour deux morceaux d'état d'un coup pour qu'ils restent
cohérents. `make sf-entity` génère ce genre de méthodes (`addX`/`removeX`) quand vous créez une relation bidirectionnelle.

---

## 2.4 Les collections (≠ tableaux JS)

Le côté « plusieurs » d'une relation n'est **pas** un tableau PHP mais une `Collection` Doctrine :

| JS / TS | Doctrine | Rôle |
|---|---|---|
| `Book[]` (type) | `Doctrine\Common\Collections\Collection<int, Book>` | Le type à déclarer sur la propriété |
| `[]` | `new ArrayCollection()` | À créer **dans le constructeur** (entité neuve) |
| — | `PersistentCollection` | Ce que Doctrine met à la place quand il charge l'entité (chargement paresseux) |

```php
/** @var Collection<int, Review> */
#[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'book')]
private Collection $reviews;

public function __construct(/* ... */)
{
    $this->reviews = new ArrayCollection();   // ⚠️ obligatoire, sinon propriété non initialisée
}
```

| Méthode de tableau JS | Collection Doctrine |
|---|---|
| `arr.push(x)` | `$col->add($x)` |
| `arr.splice(arr.indexOf(x), 1)` | `$col->removeElement($x)` |
| `arr.includes(x)` | `$col->contains($x)` |
| `arr.length` | `$col->count()` (ou `count($col)`) |
| `arr.map(fn)` / `arr.filter(fn)` | `$col->map(fn)` / `$col->filter(fn)` (renvoient une Collection) |
| `arr.some(fn)` / `arr.every(fn)` | `$col->exists(fn)` / `$col->forAll(fn)` |
| `[...arr]` | `$col->toArray()` ; `$col->getValues()` pour une liste réindexée |

> ⚠️ Ne réaffectez jamais la propriété (`$this->reviews = new ArrayCollection([...])`) sur une entité chargée :
> Doctrine perdrait le suivi. Modifiez la collection existante avec `add()` / `removeElement()` / `clear()`.
> (Même réflexe qu'en Vue avec un `reactive()` : on mute le contenu, on ne remplace pas la référence suivie.)

---

## 2.5 Cascade, orphanRemoval et onDelete

Trois mécanismes qui se ressemblent mais agissent à des endroits différents :

| Option | Où | Effet | Exemple du kata |
|---|---|---|---|
| `cascade: ['persist']` | ORM (PHP) | `persist($book)` persiste aussi les **nouvelles** entités de la relation | `Book::$reviews` : une `new Review(...)` ajoutée à un livre suivi est insérée au flush sans `persist()` |
| `cascade: ['remove']` | ORM (PHP) | `remove($book)` appelle aussi `remove()` sur chaque élément (charge tout en mémoire !) | non utilisé |
| `orphanRemoval: true` | ORM (PHP) | un élément **retiré de la collection** est supprimé en base ; supprimer le parent supprime aussi ses enfants | `Book::$reviews` : `$book->removeReview($r)` + `flush()` = `DELETE` |
| `#[ORM\JoinColumn(onDelete: 'CASCADE')]` | **Base de données** | la contrainte SQL (étape 1 : `ON DELETE CASCADE`) supprime les lignes filles | posé automatiquement sur la table de jointure `kata_book_tag` |

Sans aucune de ces options, `remove($parent)` sur une entité qui a encore des enfants échoue : violation de clé
étrangère (par exemple `remove($author)` sur un auteur qui a des livres). C'est exactement le cas « supprimer un
projet qui a encore des tâches → 409 » du ticket B1 : on **vérifie** avant de supprimer, on ne cascade pas aveuglément.

> 💡 Doctrine crée automatiquement un **index** sur chaque colonne de clé étrangère (`author_id`, `book_id`…).
> Vérifiez-le avec `make sf-psql` puis `\d kata_book`.

---

## 2.6 Le chargement paresseux

« Pourquoi ma relation s'est-elle chargée toute seule ? » Parce que, par défaut, les relations sont **LAZY** :
chargées au **premier accès**, par une requête SQL que vous n'avez pas écrite.

```php
$book = $bookRepository->find(1);   // SELECT ... FROM kata_book WHERE id = 1
$book->getAuthor();                 // pas de requête : Author est un objet "fantôme", seul son id est connu
$book->getAuthor()->getName();      // SELECT ... FROM kata_author WHERE id = ?   ← chargé maintenant
$book->getReviews()->count();       // SELECT ... FROM kata_review WHERE book_id = ?
```

L'objet fantôme (un *lazy object* natif de PHP 8.4 dans ce projet) **est** un vrai `Author`, qui se remplit au
premier accès à une propriété. Pratique… et piégeux, parce que chaque accès peut cacher une requête.

> ⚠️ Prisma fait l'inverse : rien n'est chargé si vous ne le demandez pas (`include`). Doctrine charge **à la
> demande**, en silence. Les deux approches évitent de charger trop ; celle de Doctrine peut charger **trop souvent**.

| `fetch:` | Comportement | Usage |
|---|---|---|
| `LAZY` (défaut) | Chargé au premier accès | Presque toujours |
| `EAGER` | Toujours chargé avec l'entité | À éviter : impossible de s'en passer quand on n'en a pas besoin |
| `EXTRA_LAZY` | Sur une collection, `count()`, `contains()`, `slice()` font une requête ciblée sans tout charger | Grosses collections, **une** entité à la fois : sur une liste, un `count()` par élément = N+1 (comptez alors dans la requête, chapitre 3) |

La bonne façon de charger une relation **quand on en a besoin** n'est pas `EAGER` mais le **fetch join**, dans la
requête qui en a besoin (ci-dessous et chapitre 3).

---

## 2.7 Le problème N+1

Vous connaissez déjà ce problème côté front :

```js
// ❌ Front : 1 requête pour la liste, puis 1 requête par élément
const tasks = await api.get('/tasks')
for (const task of tasks) {
  task.project = await api.get(`/projects/${task.projectId}`)
}
```

C'est lent à cause du réseau. Côté back, le même schéma existe, mais entre PHP et la base, et il est **invisible**
dans le code :

```php
$books = $bookRepository->findAll();             // 1 requête : les 9 livres
foreach ($books as $book) {
    echo $book->getAuthor()->getName();          // + 1 requête par auteur pas encore chargé
    echo count($book->getReviews());             // + 1 requête par livre
}
// Total : 1 + 4 + 9 = 14 requêtes pour afficher une liste
```

Avec 9 livres, ça passe. Avec 500 tâches dans `GET /api/tasks`, c'est 500 requêtes. Le **profiler** le montre
immédiatement : http://localhost:8082/_profiler → onglet **Doctrine**, des dizaines de requêtes identiques à un
paramètre près (exactement comme une cascade de requêtes identiques dans l'onglet *Réseau* des DevTools).

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

≈ `include: { author: true }` en Prisma. Sans `addSelect('a')`, la jointure sert seulement à **filtrer**
(`WHERE a.country = ...`) : les auteurs ne sont pas chargés, et le N+1 reste. C'est une nuance essentielle,
travaillée dans le kata (K06).

> Pour savoir si une collection a été chargée : `$book->getReviews()->isInitialized()` (sur une `PersistentCollection`).

---

## 2.8 Relations et JSON : les références circulaires

Essayez ceci dans la console du navigateur :

```js
const author = { name: 'Victor Hugo', books: [] }
const book = { title: 'Les Misérables', author }
author.books.push(book)
JSON.stringify(author)   // TypeError: Converting circular structure to JSON
```

Sérialiser une entité avec des relations bidirectionnelles, c'est exactement ça : `Book → author → books → author → …`
Le Serializer Symfony lève alors une `CircularReferenceException`. Et même sans boucle, il déclencherait du
chargement paresseux partout (un N+1 caché dans la génération du JSON).

Deux solutions (déjà vues avec [Symfony](../03-symfony/README.md)) :

1. **Groupes de sérialisation** : `#[Groups(['task:read'])]` sur les propriétés à exposer, et
   `$this->json($task, context: ['groups' => 'task:read'])`. La relation inverse n'est pas dans le groupe.
2. **DTO de sortie** : une classe `TaskOutput` construite à partir de l'entité, qui ne contient que ce que le
   contrat d'API demande (ex. `project: { id, name }`). C'est l'équivalent d'un type TS dédié à la réponse
   d'API, distinct du modèle interne. Recommandé quand la forme JSON s'éloigne de l'entité.

Le contrat d'API demande justement des formes réduites (`"project": { "id": 1, "name": "..." }`,
`"author": User`) : pensez-y dès le mapping.

---

## 2.9 Application au projet

| Ticket | Relation à créer | Côté propriétaire | Points d'attention |
|---|---|---|---|
| **B1** Projets | `Task::$project` ManyToOne → `Project`, `nullable: true` | `Task` | Inverse `Project::$tasks` utile pour `taskCount` ? Une requête `COUNT` suffit aussi. `DELETE` d'un projet non vide → 409, pas de cascade |
| **B2** Utilisateurs | `Task::$author` et `Task::$assignee` : **deux** ManyToOne vers `User` | `Task` | Deux relations vers la même entité : si vous les rendez bidirectionnelles, il faut deux collections inverses distinctes (`createdTasks`, `assignedTasks`). Unidirectionnel conseillé. `ApiToken` ManyToOne → `User` |
| **B3** Commentaires | `Comment::$task` ManyToOne → `Task`, `Comment::$author` ManyToOne → `User` (nullable au départ) | `Comment` | Unidirectionnel conseillé (pas de `Task::$comments` : `Task` est un fichier partagé). Supprimer une tâche doit supprimer ses commentaires : `onDelete: 'CASCADE'` sur `Comment::$task` est le plus simple. `commentCount` sans N+1 |

---

➡️ Suite : [3. DQL et QueryBuilder](03-dql-et-query-builder.md)
