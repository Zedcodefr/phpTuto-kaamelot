# Labo Doctrine : prédire, exécuter, expliquer

> Vous savez ce qu'est un ORM. Ce qui piège, ce n'est pas le principe, c'est le **moment** où Doctrine parle à la base,
> et ce qu'il fait (ou ne fait pas) quand vous touchez à vos objets. Plutôt qu'un cours sur l'Unit of Work, neuf petites
> expériences : vous **pariez** sur ce qui va se passer, vous **lancez**, et vous lisez le **SQL réellement envoyé**.
> La théorie vient après, pour expliquer les surprises.

## Règles du jeu

- On fait les expériences **ensemble**, dans l'ordre, L01 → L09. Pour chacune :
  1. lisez le code du test (`apps/api-symfony/tests/Labo/L01IdentityMapTest.php`…) : une dizaine de lignes ;
  2. notez **votre pronostic** dans `apps/api-symfony/tests/Labo/pronostics.php` (remplacez les `null`) ;
  3. lancez `make sf-labo c="--filter=L01"` ;
  4. lisez le journal : chaque étape du code, suivie du SQL qu'elle a envoyé (ou de « aucune requête »).
- Tant qu'un pronostic vaut `null`, l'expérience **refuse de s'exécuter** : on parie avant de regarder.
- Un pronostic faux, c'est le but du jeu. Ne corrigez pas `pronostics.php` pour « faire passer le test » :
  cherchez d'abord **quelle ligne** a envoyé (ou pas) du SQL, et **pourquoi**.
- Pas d'IA pendant le pronostic : c'est votre intuition qu'on teste. Pour expliquer ensuite, elle est autorisée
  ([charte](../../00-charte-ia.md)), à condition de savoir le redire avec vos mots.
- Chaque expérience propose un **« Et si… ? »** : modifiez le test, refaites un pronostic, relancez.
- Les données sont celles du [kata](04-kata-doctrine.md#43-le-domaine--une-bibliothèque) (9 livres, 5 auteurs) et
  chaque test est annulé à la fin : vous ne pouvez rien casser. À la fin du labo :
  `git restore apps/api-symfony/tests/Labo/`.

```bash
make up && make sf-labo      # 9 expériences « en attente de pronostic »
```

Le journal vient du même collecteur que l'onglet **Doctrine du profiler**, que vous retrouverez sur l'API
(http://localhost:8082/_profiler). Seuls les alias de colonnes (`t0.title AS title_3`) sont retirés pour la lisibilité.

> ⚠️ Côté Pinia, vous êtes habitués à un store qui **reflète** l'état et qu'on **pousse** vers l'API à la main.
> Gardez cette intuition sous le coude : plusieurs expériences sont là pour la contredire.

---

## L01 — Trois lectures du même livre

```php
$a = $this->books->find($id);
$b = $this->books->find($id);
$c = $this->books->findOneBy(['title' => 'Les Misérables']);   // le même livre
```

**Pronostic** : combien de requêtes SQL ? `$a === $c` ?

**Et si… ?** Ajoutez `$this->em->clear();` entre `$a` et `$b`. Puis comparez `$a === $b`.

**À savoir expliquer** : pourquoi la deuxième lecture ne coûte rien, et pourquoi la troisième coûte quand même une
requête. Ce que garantit Doctrine sur « une ligne en base ».
Cours : [1.4 L'Identity Map](01-orm-et-mapping.md#14-lidentity-map).

---

## L02 — Modifier sans `persist()`

```php
$miserables = $this->book('Les Misérables');   // pas épuisé
$nil = $this->book('Mort sur le Nil');         // pas épuisé non plus

$miserables->setOutOfPrint(true);
$nil->setOutOfPrint(false);                    // il l'était déjà
$this->em->flush();                            // aucun persist()
```

**Pronostic** : combien de requêtes au total ? Combien d'`UPDATE` ?

**Et si… ?** Ajoutez `$this->em->persist($miserables);` avant le `flush()`. Puis, sur le même livre,
`setOutOfPrint(true)` suivi de `setOutOfPrint(false)`.

**À savoir expliquer** : comment Doctrine sait-il quoi écrire sans qu'on le lui dise ? Que contient exactement
l'`UPDATE` (toutes les colonnes ?) ? À quoi sert vraiment `persist()`, alors ?
Cours : [1.5 L'Unit of Work](01-orm-et-mapping.md#15-lunit-of-work-et-les-états-dune-entité),
[le suivi des changements est implicite](01-orm-et-mapping.md#le-suivi-des-changements-est-implicite).

---

## L03 — `persist()`, puis `flush()`

```php
$leGuin = new Author('Ursula K. Le Guin', 'États-Unis');
$this->em->persist($leGuin);
// ← combien de requêtes ici ? que renvoie $leGuin->getId() ?
$this->em->flush();
```

**Pronostic** : combien de requêtes juste après `persist()` ? L'id est-il déjà connu ? Combien de requêtes au total ?

**Et si… ?** Appelez `flush()` une deuxième fois. Puis créez deux auteurs et un seul `flush()`.

**À savoir expliquer** : qui fournit l'id, et à quel moment ? Pourquoi `persist()` n'écrit-il rien ? Si l'API
reçoit `POST /api/tasks`, où se trouve la transaction ?
Cours : [quand faut-il appeler `persist()` ?](01-orm-et-mapping.md#quand-faut-il-appeler-persist-),
[1.6 Transactions](01-orm-et-mapping.md#16-transactions).

---

## L04 — Suivre une relation

```php
$book = $this->books->find($id);    // « Kafka sur le rivage »
$author = $book->getAuthor();
$author->getId();
$author->getName();
```

**Pronostic** : le nombre total de requêtes après **chacune** des quatre lignes.

**Et si… ?** Ajoutez `$author instanceof Author` et `$author::class` au journal (`$this->commenter(…)`).
Puis remplacez `find()` par une lecture avec `join('b.author', 'a')->addSelect('a')`.

**À savoir expliquer** : qu'est-ce qu'un objet « encore vide » ? Quand se remplit-il, et pourquoi `getId()` ne le
remplit pas ? Quel risque si cet objet est sérialisé en JSON après la fin de la requête ?
Cours : [2.6 Le chargement paresseux](02-relations.md#26-le-chargement-paresseux).

---

## L05 — Changer l'auteur d'un livre… par le mauvais bout

```php
$atwood = $this->author('Margaret Atwood');
$kafka = $this->book('Kafka sur le rivage');   // de Haruki Murakami

$atwood->getBooks()->add($kafka);
$this->em->flush();
// puis clear() et relecture du livre en base
```

**Pronostic** : combien d'`UPDATE` pendant le `flush()` ? Qui est l'auteur du livre quand on relit la base ?

**Et si… ?** Remplacez la ligne `add()` par `$kafka->setAuthor($atwood);`. Relisez ensuite `Book::setAuthor()`
dans `src/Kata/Entity/Book.php` : pourquoi fait-elle deux choses ?

**À savoir expliquer** : pourquoi l'objet en mémoire et la base ne disent pas la même chose. Lequel des deux
côtés de la relation Doctrine écoute, et comment le reconnaître dans le mapping (`mappedBy` / `inversedBy`).
Pourquoi la ligne `add()` a quand même envoyé un `SELECT`.
Cours : [2.2 Côté propriétaire, côté inverse](02-relations.md#22-côté-propriétaire-côté-inverse--le-piège),
[2.3 Garder les deux côtés synchronisés](02-relations.md#23-garder-les-deux-côtés-synchronisés-en-mémoire).

---

## L06 — La liste des livres avec leur auteur

```php
foreach ($this->books->findAll() as $book) {        // 9 livres, 4 auteurs différents
    $lignes[] = $book->getTitle().' — '.$book->getAuthor()->getName();
}

// puis la même boucle sur :
$this->books->createQueryBuilder('b')->join('b.author', 'a')->addSelect('a')->getQuery()->getResult();
```

**Pronostic** : combien de requêtes pour la boucle naïve ? Et avec le `join … addSelect` ?

**Et si… ?** Dans la boucle naïve, affichez aussi `$book->getReviews()->count()`. Puis retirez `addSelect('a')`
de la deuxième version en gardant le `join`.

**À savoir expliquer** : d'où vient le nom « N+1 », et pourquoi on n'a pas ici 1 + 9. Ce que change `addSelect`
(un `join` seul ne suffit pas). Comment repérer ce problème dans le profiler, sur un vrai endpoint.
Cours : [2.7 Le problème N+1](02-relations.md#27-le-problème-n1),
[3.8 Fetch join](03-dql-et-query-builder.md#38-fetch-join-et-pagination).

---

## L07 — Accrocher un nouvel objet sans `persist()`

```php
$book = $this->book('Mort sur le Nil');

new Review($book, 5, 'Relu avec plaisir');   // s'ajoute à $book->getReviews()
$this->em->flush();

$book->addTag(new Tag('égypte'));
$this->em->flush();
```

Avant de parier, relisez le mapping de `Book::$reviews` et de `Book::$tags` dans `src/Kata/Entity/Book.php`.

**Pronostic** : chacun des deux `flush()` passe-t-il ?

**Et si… ?** Ajoutez `$this->em->persist(…)` sur l'étiquette. Pourquoi le premier `flush()` a-t-il d'abord fait un
`SELECT` sur `kata_review` ?

**À savoir expliquer** : ce que veut dire `cascade: ['persist']`, et pourquoi Doctrine refuse au lieu de deviner.
Pourquoi on ne met pas `cascade` partout. La différence avec `orphanRemoval` et `onDelete: 'CASCADE'`.
Cours : [2.5 Cascade, orphanRemoval et onDelete](02-relations.md#25-cascade-orphanremoval-et-ondelete).

---

## L08 — Un `UPDATE` DQL sur un livre déjà chargé

```php
$book = $this->book('Mort sur le Nil');   // isOutOfPrint() = false

$this->em->createQuery('UPDATE App\Kata\Entity\Book b SET b.outOfPrint = true WHERE b.title = :title')
    ->setParameter('title', 'Mort sur le Nil')
    ->execute();

$book->isOutOfPrint();                                  // ?
$this->books->find($book->getId())->isOutOfPrint();     // ?
$this->em->refresh($book); $book->isOutOfPrint();       // ?
```

**Pronostic** : ce que renvoie `execute()`, puis `isOutOfPrint()` à chacune des trois étapes.

**Et si… ?** Remplacez `refresh()` par `clear()` suivi d'un nouveau `find()`.

**À savoir expliquer** : pourquoi un `UPDATE` DQL et un `setOutOfPrint()` + `flush()` ne se comportent pas pareil.
Pourquoi le nouveau `find()` ment, et pourquoi on choisit quand même le DQL pour modifier des milliers de lignes
(kata K12).
Cours : [3.10 UPDATE et DELETE en DQL](03-dql-et-query-builder.md#310-update-et-delete-en-dql),
[1.4 L'Identity Map](01-orm-et-mapping.md#14-lidentity-map).

---

## L09 — Combien de « pavés » ?

Trois façons de compter les livres de plus de 1000 pages. Les trois répondent la même chose.

```php
// A
count(array_filter($this->books->findAll(), fn (Book $b) => $b->getPages() > 1000));
// B
count($this->books->createQueryBuilder('b')->where('b.pages > :pages')->setParameter('pages', 1000)
    ->getQuery()->getResult());
// C
$this->books->createQueryBuilder('b')->select('COUNT(b.id)')->where('b.pages > :pages')->setParameter('pages', 1000)
    ->getQuery()->getSingleScalarResult();
```

**Pronostic** : combien d'objets `Book` chaque version crée-t-elle en mémoire ?

**Et si… ?** Avec 100 000 livres en base, que transporte chaque version sur le réseau, et combien d'objets PHP
crée-t-elle ? Laquelle écririez-vous en JS avec un tableau `books.filter(...)`, et pourquoi c'est un réflexe à perdre ici ?

**À savoir expliquer** : la règle d'or, avec vos mots. Où vous l'appliquerez dans le projet (filtres et pagination de B3).
Cours : [3.1 La règle d'or](03-dql-et-query-builder.md#31-la-règle-dor--le-travail-se-fait-en-base).

---

## Et ensuite

Tout ce que le labo vous a fait voir est détaillé, avec les cas limites, dans les chapitres
[1](01-orm-et-mapping.md), [2](02-relations.md) et [3](03-dql-et-query-builder.md), qui servent maintenant de
**référence**. Le [kata](04-kata-doctrine.md) vous fait écrire les requêtes vous-même : K06 reprend L06, K12 reprend L08,
K20 reprend L05.

```bash
git restore apps/api-symfony/tests/Labo/   # remet les pronostics à zéro
```
