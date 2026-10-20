# 3. DQL et QueryBuilder

> Ce cours se met en pratique avec le [kata](04-kata-doctrine.md).
> Gardez un terminal ouvert pour essayer chaque exemple avec `make dql q="..."`.

- [3.1 Le DQL : du SQL qui parle objet](#31-le-dql--du-sql-qui-parle-objet)
- [3.2 SQL ↔ DQL ↔ LINQ](#32-sql--dql--linq)
- [3.3 Paramètres](#33-paramètres)
- [3.4 Le QueryBuilder](#34-le-querybuilder)
- [3.5 Construire une requête dynamiquement](#35-construire-une-requête-dynamiquement)
- [3.6 Modes d'hydratation](#36-modes-dhydratation)
- [3.7 Fetch join et pagination](#37-fetch-join-et-pagination)
- [3.8 Projection dans un DTO : SELECT NEW](#38-projection-dans-un-dto--select-new)
- [3.9 UPDATE et DELETE en DQL](#39-update-et-delete-en-dql)
- [3.10 Criteria, SQL natif](#310-criteria-sql-natif)
- [3.11 Voir et comprendre le SQL généré](#311-voir-et-comprendre-le-sql-généré)
- [3.12 Anti-sèche](#312-anti-sèche)

---

## 3.1 Le DQL : du SQL qui parle objet

Le **DQL** (*Doctrine Query Language*) ressemble à du SQL, mais on y manipule des **classes**, des **propriétés**
et des **relations**, jamais des tables ni des colonnes :

```sql
-- DQL
SELECT b FROM App\Kata\Entity\Book b JOIN b.author a WHERE a.country = 'Japon'
```

```sql
-- SQL généré par Doctrine (make sf c='doctrine:query:dql --show-sql "..."')
SELECT k0_.id AS id_0, k0_.title AS title_2, ... FROM kata_book k0_
INNER JOIN kata_author k1_ ON k0_.author_id = k1_.id WHERE k1_.country = 'Japon'
```

| Dans la requête | DQL | SQL |
|---|---|---|
| Source | `App\Kata\Entity\Book b` (classe complète + alias **obligatoire**) | `kata_book` |
| Champ | `b.publishedAt` (propriété) | `published_at` (colonne) |
| Jointure | `JOIN b.author a` (relation : pas de `ON`, Doctrine connaît la clé) | `INNER JOIN kata_author ON ...` |
| `SELECT b` | renvoie des **objets** `Book` | renvoie des lignes |

C'est l'équivalent de LINQ to Entities : vous décrivez la requête en termes de modèle, l'ORM écrit le SQL.
La différence : le DQL est une **chaîne de caractères**, sans vérification à la compilation. Une faute de frappe
dans un nom de propriété n'apparaît qu'à l'exécution (`[Semantical Error] ... Class App\Kata\Entity\Book has no field
or association named titel`). D'où l'intérêt des tests.

---

## 3.2 SQL ↔ DQL ↔ LINQ

Les exemples DQL utilisent le domaine du kata ; testez-les avec `make dql q="..."`.

| Besoin | DQL | LINQ (EF Core) |
|---|---|---|
| Sélection d'entités | `SELECT a FROM App\Kata\Entity\Author a` | `ctx.Authors` |
| Champs choisis | `SELECT a.name, a.country FROM ... a` | `.Select(a => new { a.Name, a.Country })` |
| Filtre | `WHERE a.country = :country` | `.Where(a => a.Country == country)` |
| Tri | `ORDER BY a.name ASC, a.id DESC` | `.OrderBy(a => a.Name).ThenByDescending(a => a.Id)` |
| Jointure sur une relation | `JOIN b.author a` / `LEFT JOIN a.books b` | navigation `b.Author` / `Include` |
| Jointure avec condition en plus | `LEFT JOIN a.books b WITH b.pages > 500` | `a.Books.Where(b => b.Pages > 500)` |
| Jointure sans relation | `JOIN App\Kata\Entity\Book b WITH b.author = a` | `join ... on ... equals ...` |
| Agrégats | `COUNT(b.id)`, `SUM(r.rating)`, `AVG(r.rating)`, `MIN`, `MAX` | `.Count()`, `.Sum()`, `.Average()` |
| Regroupement | `GROUP BY t.id HAVING COUNT(b.id) > 2` | `.GroupBy(...).Where(g => g.Count() > 2)` |
| Sans doublon | `SELECT DISTINCT a FROM ...` | `.Distinct()` |
| Liste | `WHERE t.name IN (:names)` | `names.Contains(t.Name)` |
| Motif | `WHERE LOWER(b.title) LIKE :pattern` | `EF.Functions.Like(...)` / `.Contains()` |
| Nul | `WHERE b.description IS NULL` | `== null` |
| Intervalle | `WHERE b.pages BETWEEN 300 AND 500` | `>= 300 && <= 500` |
| Sous-requête | `WHERE EXISTS (SELECT ...)`, `a.id IN (SELECT IDENTITY(b.author) FROM ...)` | `.Any(...)` |
| Taille d'une collection | `SIZE(t.books)` | `t.Books.Count` |
| Appartenance | `:book MEMBER OF t.books` | `t.Books.Contains(book)` |
| Clé étrangère sans jointure | `IDENTITY(b.author)` → l'id de l'auteur | `b.AuthorId` |
| Conditionnel | `CASE WHEN b.pages > 800 THEN 'pavé' ELSE 'normal' END` | `b.Pages > 800 ? "pavé" : "normal"` |
| Fonctions | `LOWER`, `UPPER`, `CONCAT(a, b)`, `LENGTH`, `TRIM`, `SUBSTRING`, `ABS`, `CURRENT_DATE()`, `DATE_DIFF(d1, d2)` | `ToLower()`, `string.Concat`… |

Exemples vérifiés sur les données du kata :

```bash
# Nombre de livres par étiquette (SIZE évite d'écrire la jointure)
make dql q="SELECT t.name, SIZE(t.books) AS nb FROM App\Kata\Entity\Tag t ORDER BY t.name"
# → aventure 3, classique 7, contemporain 2, policier 2, science-fiction 3

# Auteurs ayant au moins un livre épuisé (sous-requête IN + IDENTITY)
make dql q="SELECT a.name FROM App\Kata\Entity\Author a WHERE a.id IN (SELECT IDENTITY(b.author) FROM App\Kata\Entity\Book b WHERE b.outOfPrint = true)"
# → Jules Verne

# Nombre de "pavés" (> 500 pages) par auteur, auteurs sans pavé compris : la condition va dans le WITH, pas dans le WHERE
make dql q="SELECT a.name, COUNT(b.id) AS nb FROM App\Kata\Entity\Author a LEFT JOIN a.books b WITH b.pages > 500 GROUP BY a.id ORDER BY a.name"
# → Agatha Christie 0, Haruki Murakami 2, Jules Verne 0, Margaret Atwood 0, Victor Hugo 2
```

> ⚠️ Dans le dernier exemple, mettre `b.pages > 500` dans le `WHERE` éliminerait les auteurs sans pavé : un
> `WHERE` sur la table de droite transforme le `LEFT JOIN` en `INNER JOIN`. Même piège qu'en SQL.

> ⚠️ `AVG()` revient en **string** avec PostgreSQL (`"4.0714285714285714"`), `COUNT()` et `SUM()` en `int`.
> Convertissez explicitement (`(float)`, `round()`) avant de renvoyer une valeur.

---

## 3.3 Paramètres

```php
$em->createQuery('SELECT t FROM App\Kata\Entity\Tag t WHERE t.name = :name')   // paramètre nommé (recommandé)
   ->setParameter('name', $name);

$em->createQuery('SELECT t FROM App\Kata\Entity\Tag t WHERE t.name = ?1')      // paramètre positionnel
   ->setParameter(1, $name);
```

Doctrine convertit les types tout seul : un `\DateTimeImmutable`, un `bool`, une entité (son id est utilisé), un
enum *backed*, un tableau (pour `IN (:names)`).

> ❌ **Jamais** de concaténation : `"WHERE t.name = '$name'"`. C'est une injection (en DQL comme en SQL), et
> ça casse au premier nom contenant une apostrophe. Même règle qu'avec PDO en partie 1.
>
> Pour un `LIKE`, les `%` vont **dans la valeur du paramètre**, pas dans la requête :
> `->setParameter('pattern', '%' . mb_strtolower($text) . '%')`.

---

## 3.4 Le QueryBuilder

Le QueryBuilder construit le même DQL avec des appels de méthodes. C'est le plus utilisé dans les repositories.

```php
// Dans TagRepository (createQueryBuilder('t') ≈ "SELECT t FROM Tag t")
public function findPopular(int $minBooks): array
{
    return $this->createQueryBuilder('t')
        ->join('t.books', 'b')
        ->groupBy('t.id')
        ->having('COUNT(b.id) >= :min')
        ->setParameter('min', $minBooks)
        ->orderBy('t.name', 'ASC')
        ->getQuery()          // → objet Query (le DQL est figé)
        ->getResult();        // → exécution + hydratation
}
```

| Méthode | Rôle |
|---|---|
| `createQueryBuilder('alias')` | `SELECT alias FROM <entité du repository> alias` |
| `select(...)` / `addSelect(...)` | **Remplace** / **complète** ce qui est sélectionné |
| `join()` (= `innerJoin()`) / `leftJoin()` | Jointure sur une relation : `->join('b.author', 'a')`, condition en plus : `->leftJoin('a.books', 'b', 'WITH', 'b.pages > 500')` |
| `where()` / `andWhere()` / `orWhere()` | Conditions |
| `setParameter()` / `setParameters()` | Valeurs des paramètres |
| `orderBy()` / `addOrderBy()` | **Remplace** / **complète** le tri |
| `groupBy()` / `addGroupBy()` / `having()` | Regroupement |
| `distinct()` | `SELECT DISTINCT` |
| `setFirstResult()` / `setMaxResults()` | `OFFSET` / `LIMIT` |
| `expr()` | Fabrique d'expressions : `$qb->expr()->in('t.name', ':names')`, `->like()`, `->isNull()`, `->orX()`… |
| `getDQL()` | Le DQL produit : **le premier réflexe pour déboguer** |

### Le piège de `where()`

`where()` **écrase** les conditions précédentes ; `andWhere()` les **complète** :

```php
$qb->where('t.id > 1')->where('t.name = :n');
// SELECT t FROM App\Kata\Entity\Tag t WHERE t.name = :n                ← t.id > 1 a disparu !

$qb->where('t.id > 1')->andWhere('t.name = :n')->orWhere('t.id = 9');
// SELECT t FROM ... WHERE (t.id > 1 AND t.name = :n) OR t.id = 9       ← attention aux parenthèses
```

Même chose pour `select()`/`addSelect()` et `orderBy()`/`addOrderBy()`. Règle simple : `andWhere()` partout.

---

## 3.5 Construire une requête dynamiquement

Le QueryBuilder brille quand les critères sont **optionnels** (filtres d'une liste, ticket **B3**) :

```php
public function findFiltered(?TaskStatus $status, ?string $search): array
{
    $qb = $this->createQueryBuilder('t')->orderBy('t.id', 'ASC');

    if (null !== $status) {
        $qb->andWhere('t.status = :status')->setParameter('status', $status);
    }
    if (null !== $search && '' !== $search) {
        $qb->andWhere('LOWER(t.title) LIKE :search OR LOWER(t.description) LIKE :search')
           ->setParameter('search', '%' . mb_strtolower($search) . '%');
    }

    return $qb->getQuery()->getResult();
}
```

> ⚠️ Un `OR` dans un `andWhere()` est entouré de parenthèses par Doctrine : `WHERE t.status = :status AND (... OR ...)`.
> Vérifiez toujours avec `getDQL()`.

---

## 3.6 Modes d'hydratation

« Hydrater », c'est transformer les lignes SQL en résultat PHP. Le **même** DQL peut renvoyer des formes différentes :

| Méthode | Renvoie | Si 0 résultat | Si plusieurs |
|---|---|---|---|
| `getResult()` | `list<Entity>` (ou tableaux si on sélectionne des champs) | `[]` | — |
| `getOneOrNullResult()` | une entité ou `null` | `null` | `NonUniqueResultException` |
| `getSingleResult()` | une entité | `NoResultException` | `NonUniqueResultException` |
| `getSingleScalarResult()` | une valeur (`COUNT`, `MAX`…) | `NoResultException` | `NonUniqueResultException` |
| `getArrayResult()` | tableaux imbriqués, **pas d'entités ni de suivi** | `[]` | — |
| `getScalarResult()` | tableaux à plat (`alias_champ`) | `[]` | — |
| `toIterable()` | un itérateur, ligne par ligne | — | — |

Exemples (vérifiés) :

```php
$em->createQuery('SELECT COUNT(b.id) FROM App\Kata\Entity\Book b')->getSingleScalarResult();
// 9

$em->createQuery("SELECT t FROM App\Kata\Entity\Tag t WHERE t.name = 'policier'")->getArrayResult();
// [['id' => 3, 'name' => 'policier']]            ← lecture légère, rien n'est suivi par l'Unit of Work

$em->createQuery('SELECT b.title, a.name AS author FROM App\Kata\Entity\Book b JOIN b.author a WHERE b.pages > 1000')->getResult();
// [['title' => '1Q84', 'author' => 'Haruki Murakami'], ['title' => 'Les Misérables', 'author' => 'Victor Hugo']]
```

### Résultats mixtes : entité + valeurs calculées

```php
$rows = $em->createQuery('SELECT t, SIZE(t.books) AS nb FROM App\Kata\Entity\Tag t')->getResult();
// $rows[0][0]    → l'objet Tag
// $rows[0]['nb'] → 2
```

Pratique pour `taskCount` (B1) ou `commentCount` (B3) sans N+1. Pour **trier** sur une valeur calculée sans la
renvoyer : `SELECT t, COUNT(b.id) AS HIDDEN nb ... ORDER BY nb DESC`.

### Gros volumes

`getResult()` charge tout en mémoire **et** tout dans l'Unit of Work. Pour traiter 100 000 lignes (un export,
une commande de maintenance), on parcourt `toIterable()` et on appelle `$em->clear()` régulièrement.

---

## 3.7 Fetch join et pagination

### Fetch join : charger une relation dans la même requête

Rappel du [chapitre 2](02-relations.md#27-le-problème-n1) : `join()` sert à **filtrer**, `join()` + `addSelect()`
sert à **charger**.

```php
$this->createQueryBuilder('t')
    ->leftJoin('t.project', 'p')->addSelect('p')       // les projets arrivent avec les tâches
    ->getQuery()->getResult();
```

### Pourquoi `setMaxResults()` ne suffit pas avec un fetch join de collection

`LIMIT` s'applique aux **lignes SQL**, pas aux entités. Une jointure sur une collection produit une ligne par
élément de la collection :

```
SELECT b, r FROM Book b LEFT JOIN b.reviews r ORDER BY b.title   + setMaxResults(3)

lignes SQL :  1Q84 | -             ← ligne 1
              Kafka | note 4       ← ligne 2
              Le Crime | note 5    ← ligne 3   ✂️ LIMIT 3
              Le Crime | note 4    ← coupée
              Le Crime | note 4    ← coupée
```

Résultat constaté sur les données du kata : 3 livres, mais « Le Crime de l'Orient-Express » n'a que **1** critique
au lieu de 3. Aucun message d'erreur : les données sont simplement fausses.

### La solution : le Paginator Doctrine

```php
use Doctrine\ORM\Tools\Pagination\Paginator;

$query = $this->createQueryBuilder('t')
    ->orderBy('t.id', 'ASC')
    ->setFirstResult(($page - 1) * $limit)    // OFFSET
    ->setMaxResults($limit)                   // LIMIT
    ->getQuery();

$paginator = new Paginator($query);
count($paginator);                            // nombre TOTAL de résultats (requête COUNT séparée) → X-Total-Count
foreach ($paginator as $task) { ... }         // les éléments de la page
iterator_to_array($paginator, false);         // en liste (false = réindexer 0, 1, 2…)
```

Le Paginator commence par récupérer les **identifiants** de la page, puis charge les entités complètes de ces
identifiants : la pagination est juste même avec des fetch joins. C'est l'outil du ticket **B3** (en-tête `X-Total-Count`).

---

## 3.8 Projection dans un DTO : SELECT NEW

Quand on veut seulement **lire** quelques champs pour les renvoyer en JSON, inutile de charger des entités suivies :

```php
final readonly class TagStats
{
    public function __construct(public string $name, public int $bookCount) {}
}

$stats = $em->createQuery(
    'SELECT NEW App\Dto\TagStats(t.name, SIZE(t.books)) FROM App\Kata\Entity\Tag t ORDER BY t.name'
)->getResult();
// list<TagStats>
```

≈ `.Select(t => new TagStats(t.Name, t.Books.Count))` en LINQ. Les arguments sont passés **dans l'ordre** au
constructeur. Pas d'entité, pas d'Unit of Work, pas de risque de N+1 : idéal pour des listes en lecture seule.

---

## 3.9 UPDATE et DELETE en DQL

```php
$updated = $em->createQuery('UPDATE App\Entity\Task t SET t.status = :done WHERE t.createdAt < :limit')
    ->setParameter('done', TaskStatus::Done)
    ->setParameter('limit', new \DateTimeImmutable('-1 year'))
    ->execute();               // ← nombre de lignes modifiées
```

Une seule requête SQL, aucune entité chargée : indispensable pour modifier 10 000 lignes. Mais cette requête
**contourne l'Unit of Work** :

- les entités **déjà en mémoire** gardent leurs anciennes valeurs (`$em->clear()` ou `refresh()` si besoin) ;
- les **événements** (`preUpdate`, listeners) ne sont **pas** déclenchés ;
- `cascade` et `orphanRemoval` ne s'appliquent pas.

| Besoin | Outil |
|---|---|
| Modifier quelques entités avec leur logique métier | charger, modifier, `flush()` |
| Modifier en masse, logique triviale | `UPDATE`/`DELETE` DQL + `execute()` |

---

## 3.10 Criteria, SQL natif

### Criteria : filtrer une collection

```php
use Doctrine\Common\Collections\Criteria;

$excellent = Criteria::create()->where(Criteria::expr()->gte('rating', 5));
$book->getReviews()->matching($excellent);    // si la collection n'est pas chargée : requête SQL ciblée
```

Utile dans une entité pour exposer « les critiques à 5 étoiles » sans repository. Reste marginal dans le projet.

### SQL natif : quand le DQL ne suffit pas

Fonctions spécifiques à PostgreSQL, requête analytique (`WINDOW`, CTE `WITH ...`), performance extrême :
on descend d'un étage, vers **DBAL**.

```php
$rows = $em->getConnection()->fetchAllAssociative(
    'SELECT title FROM kata_book WHERE pages > ? ORDER BY pages DESC',
    [1000],
);
// [['title' => 'Les Misérables'], ['title' => '1Q84']]
```

≈ `FromSqlRaw` / Dapper. On récupère des tableaux, pas des entités. (`NativeQuery` + `ResultSetMapping` permettent
d'hydrater des entités depuis du SQL natif, mais on en a rarement besoin.)

---

## 3.11 Voir et comprendre le SQL généré

| Où | Comment |
|---|---|
| Console | `make sf c='doctrine:query:dql --show-sql "SELECT ..."'` |
| Code | `$qb->getDQL()`, `$query->getSQL()` |
| Profiler | http://localhost:8082/_profiler → requête → onglet **Doctrine** : toutes les requêtes, leurs paramètres, leur durée, et les requêtes **identiques** regroupées (signe de N+1) |
| Tests | les requêtes du kata passent par l'environnement `test` ; reproduisez-les avec `make dql` pour voir le SQL |

### Lire un plan d'exécution

```bash
make sf-psql
taskboard=# EXPLAIN SELECT * FROM kata_book WHERE author_id = 2;
 Index Scan using idx_af13a702f675f31b on kata_book  (cost=0.14..8.16 rows=1 width=533)
   Index Cond: (author_id = 2)
```

- `Index Scan` : PostgreSQL utilise un index (celui que Doctrine a créé pour la clé étrangère) ;
- `Seq Scan` sur une grosse table : il lit toute la table, il manque peut-être un `#[ORM\Index]` ;
- `EXPLAIN ANALYZE` exécute vraiment la requête et donne les temps réels.

Le bouton *Explain query* du profiler fait la même chose depuis le navigateur.

---

## 3.12 Anti-sèche

```php
// Repository → QueryBuilder → Query → résultat
$this->createQueryBuilder('b')                         // SELECT b FROM Book b
    ->addSelect('a')->join('b.author', 'a')            // fetch join (charger) ; sans addSelect : filtrer seulement
    ->leftJoin('b.reviews', 'r')                       // LEFT JOIN sur une collection
    ->andWhere('a.country = :country')                 // toujours andWhere()
    ->setParameter('country', $country)                // jamais de concaténation
    ->groupBy('b.id')->having('COUNT(r.id) > :n')      // agrégats
    ->orderBy('b.title', 'ASC')->addOrderBy('b.id')    // addOrderBy pour compléter
    ->setFirstResult($offset)->setMaxResults($limit)   // + Paginator si fetch join de collection
    ->getQuery()
    ->getResult();                                     // ou getOneOrNullResult / getSingleScalarResult / getArrayResult
```

| Je veux… | J'utilise… |
|---|---|
| Une entité par id | `$repo->find($id)` |
| Des entités par égalité simple | `$repo->findBy([...], ['champ' => 'ASC'])` |
| Une jointure, un `LIKE`, un `>` | méthode de repository + QueryBuilder |
| Éviter un N+1 | `join()` + `addSelect()` |
| Paginer + total | `Paginator` |
| Un nombre | `COUNT()` + `getSingleScalarResult()` |
| Une liste en lecture seule | `SELECT NEW Dto(...)` ou `getArrayResult()` |
| Modifier en masse | `UPDATE ... ` + `execute()` |
| Du SQL spécifique | `$em->getConnection()->fetchAllAssociative()` |
| Comprendre ce qui se passe | `getDQL()`, `--show-sql`, profiler |

---

➡️ Suite : [4. Kata Doctrine](04-kata-doctrine.md)
