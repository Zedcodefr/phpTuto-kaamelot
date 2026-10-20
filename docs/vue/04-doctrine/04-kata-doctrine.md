# 4. Kata Doctrine & DQL

> Travail **individuel** : chacun des trois devs back fait le kata, puis un débrief collectif.
> **Socle : K01 → K16 et K20.** Bonus : K17, K18, K19, K21 (une fois le socle terminé).
> **À votre rythme** : le kata est terminé quand chaque requête est **comprise**. Si vous avancez vite, passez aux
> bonus ou à l'étape suivante. Ce qui compte, c'est de **comprendre** ce que vous écrivez.
> Prérequis : chapitres [1](01-orm-et-mapping.md), [2](02-relations.md) et [3](03-dql-et-query-builder.md).

- [4.1 Le principe](#41-le-principe)
- [4.2 L'IA pendant le kata](#42-lia-pendant-le-kata)
- [4.3 Le domaine : une bibliothèque](#43-le-domaine--une-bibliothèque)
- [4.4 Méthode de travail](#44-méthode-de-travail)
- [4.5 Les 21 katas](#45-les-21-katas)
- [4.6 Indices](#46-indices)
- [4.7 Organisation à trois](#47-organisation-à-trois)
- [4.8 Et ensuite : le lien avec le projet](#48-et-ensuite--le-lien-avec-le-projet)

---

## 4.1 Le principe

Un *kata* est un petit exercice qu'on répète pour ancrer un geste (le mot vient des arts martiaux). Ici, chaque
kata est une **méthode vide** :

```php
public function k01FindAllOrderedByTitle(): array
{
    throw new \LogicException('TODO K01');
}
```

et un **test PHPUnit** déjà écrit qui dit exactement ce qu'elle doit renvoyer — un peu comme un test Vitest qui
vous attendrait déjà. Votre travail : remplacer le `throw` par une requête Doctrine jusqu'à ce que le test passe.

```bash
make sf-kata                       # tous les katas
make sf-kata c="--filter=K05"      # un seul
```

```
 ✔ K01 : tous les livres triés par titre
 ✔ K02 : livres d'un auteur (jointure + paramètre)
 ✘ K03 : livres publiés entre deux dates, du plus ancien au plus récent
   │ LogicException: TODO K03
```

Trois niveaux :

| Niveau | Thème | Fichier(s) à compléter | Tests |
|---|---|---|---|
| **1** | QueryBuilder et DQL : sélection, jointures, paramètres, tri | `src/Kata/Repository/BookRepository.php` | `tests/Kata/K1QueryBuilderTest.php` |
| **2** | Agrégats, projections, pagination, sous-requêtes | `BookRepository.php` + `src/Kata/Repository/AuthorRepository.php` | `tests/Kata/K2AggregatesTest.php` |
| **3** | Performance (fetch join, requête de masse) et Unit of Work | `BookRepository.php` (K06, K12) + `src/Kata/Library.php` | `tests/Kata/K3UnitOfWorkTest.php` |

Les **entités** (`src/Kata/Entity/`) et les **données** (`src/DataFixtures/KataFixtures.php`) sont fournies :
lisez-les, ne les modifiez pas.

---

## 4.2 L'IA pendant le kata

L'IA est autorisée, comme dans votre futur projet : sur 21 katas, elle vous aidera à avancer.
Mais le kata existe pour une raison : **savoir lire et écrire une requête Doctrine, et prédire le SQL qu'elle produit**.
Sur le projet puis en production, vous relirez des requêtes générées : c'est ici que vous apprenez à les juger.

Pour que l'IA vous fasse apprendre au lieu de faire à votre place :

- **Essayez d'abord** chaque kata par vous-même avec le test, la docblock et `make dql`. Ensuite seulement, demandez de l'aide.
- **Demandez des explications plutôt que la réponse** : « quelle différence entre `join` et `addSelect` ? »,
  « c'est quoi l'équivalent Prisma de cette requête ? », « pourquoi mon `AVG` revient en string ? »,
  « relis ma requête sans la réécrire ».
- **Si l'IA écrit la méthode**, lancez `make sf c='doctrine:query:dql --show-sql "..."'` sur sa requête et vérifiez que
  vous comprenez chaque ligne du SQL. Une requête que vous ne savez pas expliquer n'est pas terminée, même si le test est vert.
- **Méfiez-vous** de ce que l'IA propose souvent ici :
  - un `findAll()` suivi d'un `array_filter` / `usort` (le réflexe « je filtre côté client » — voir
    [la règle d'or](03-dql-et-query-builder.md#31-la-règle-dor--le-travail-se-fait-en-base)) ;
  - les alias courts `Kata:Book` (supprimés en Doctrine ORM 3) ;
  - la concaténation de valeurs au lieu de paramètres ;
  - `getResult()` là où le test attend un scalaire ;
  - `setMaxResults` sans `Paginator` avec un fetch join ;
  - `remove()` dans K21 (interdit par la **consigne de l'exercice**, qui porte sur `orphanRemoval`).

**Le critère final** : pour chaque kata, vous devez pouvoir expliquer au formateur **ce que fait la requête, le SQL
qu'elle génère et pourquoi ce choix**. Au débrief, le formateur interroge chacun sur un kata parmi K06, K12 et K20.
Voir la [charte IA](../../00-charte-ia.md).

---

## 4.3 Le domaine : une bibliothèque

```
┌────────────────────┐ 1          * ┌────────────────────────┐ 1          * ┌────────────────────┐
│ Author             │──────────────│ Book                   │──────────────│ Review             │
│ kata_author        │  books       │ kata_book              │  reviews     │ kata_review        │
├────────────────────┤ (inverse)    ├────────────────────────┤ (inverse,    ├────────────────────┤
│ id                 │              │ id                     │  cascade     │ id                 │
│ name               │      author  │ title                  │  persist,    │ book    (book_id)  │
│ country            │◀─────────────│ author   (author_id)   │  orphan-     │ rating  1..5       │
└────────────────────┘ (propriétaire)│ publishedAt (DATE)     │  Removal)    │ comment            │
                                    │ pages                  │◀─────────────│ createdAt          │
                                    │ outOfPrint             │  book        └────────────────────┘
                                    └───────────┬────────────┘ (propriétaire)
                                                │ * tags (propriétaire)
                                                │   table kata_book_tag
                                                │ * books (inverse)
                                    ┌───────────┴────────────┐
                                    │ Tag         kata_tag   │
                                    │ id, name (unique)      │
                                    └────────────────────────┘
```

Vu depuis un front, ce serait un JSON du genre
`{ title, author: { name, country }, reviews: [{ rating }], tags: [{ name }] }` : ici, chaque niveau d'imbrication
est une **table** et une **relation** Doctrine.

### Les données (`KataFixtures`)

| Auteur | Pays |
|---|---|
| Victor Hugo | France |
| Jules Verne | France |
| Agatha Christie | Royaume-Uni |
| Haruki Murakami | Japon |
| Margaret Atwood | Canada — **aucun livre** |

| Livre | Auteur | Publication | Pages | Épuisé | Étiquettes | Notes des critiques |
|---|---|---|---|---|---|---|
| Les Misérables | Victor Hugo | 1862-04-03 | 1900 | non | classique | 5, 4, 5 |
| Notre-Dame de Paris | Victor Hugo | 1831-03-16 | 940 | non | classique | 3, 4 |
| Vingt mille lieues sous les mers | Jules Verne | 1870-06-20 | 480 | non | classique, aventure, science-fiction | 5, 5 |
| Le Tour du monde en quatre-vingts jours | Jules Verne | 1872-12-22 | 320 | non | classique, aventure | 4, 3 |
| Voyage au centre de la Terre | Jules Verne | 1864-11-25 | 350 | **oui** | classique, aventure, science-fiction | — |
| Le Crime de l'Orient-Express | Agatha Christie | 1934-01-01 | 256 | non | classique, policier | 5, 4, 4 |
| Mort sur le Nil | Agatha Christie | 1937-11-01 | 300 | non | classique, policier | 2 |
| Kafka sur le rivage | Haruki Murakami | 2002-09-12 | 640 | non | contemporain | 4 |
| 1Q84 | Haruki Murakami | 2009-05-29 | 1100 | non | contemporain, science-fiction | — |

Chaque critique a pour commentaire « Note de N/5 » et une date de création qui suit l'ordre du tableau
(données des fixtures).

> 💡 L'ordre alphabétique de PostgreSQL dans ce projet compare les caractères un par un (`1Q84` < `Kafka` <
> `Le Crime` < `Le Tour` < `Les Misérables`…), un peu comme un `.sort()` JS sans `localeCompare`.
> Ne vous étonnez pas : le test a raison.

Explorez ces données avant de commencer :

```bash
make dql q="SELECT b.title, a.name FROM App\Kata\Entity\Book b JOIN b.author a ORDER BY b.title"
make sql q="SELECT * FROM kata_book_tag"
```

---

## 4.4 Méthode de travail

Pour **chaque** kata :

1. **Lire le test** (`tests/Kata/K…Test.php`) : il donne le résultat exact attendu, le type (entités ? tableaux ?) et l'ordre.
2. **Lire la docblock** de la méthode à compléter : elle liste les notions à utiliser et les contraintes
   (« sans QueryBuilder », « avec une sous-requête »…).
3. **Prototyper en DQL** avec `make dql q="..."` jusqu'à obtenir les bonnes données (comme on teste une requête
   d'API dans un client HTTP avant de l'écrire dans un composant).
4. **Regarder le SQL** : `make sf c='doctrine:query:dql --show-sql "..."'`. Est-ce le SQL que vous auriez écrit
   à la main à l'étape 1 ?
5. **Coder** la méthode (QueryBuilder ou DQL selon la consigne), puis `make sf-kata c="--filter=K0X"`.
6. **Noter en une phrase** dans votre PR ce que vous avez appris ou le piège rencontré.

Les tests tournent dans une transaction annulée à la fin : un kata qui écrit en base (niveau 3) ne casse pas les autres.
Si les données semblent abîmées, relancez simplement `make sf-kata` : il recharge les fixtures.

---

## 4.5 Les 21 katas

🟢 = socle · 🔵 = bonus.

| Kata | Ce qu'il faut renvoyer | Fichier | Notions | Cours |
|---|---|---|---|---|
| 🟢 **K01** | Tous les livres triés par titre | BookRepository | `createQueryBuilder`, `orderBy` | [3.5](03-dql-et-query-builder.md#35-le-querybuilder) |
| 🟢 **K02** | Les livres d'un auteur donné par son nom | BookRepository | jointure sur une relation, paramètre | [3.3](03-dql-et-query-builder.md#33-sql--dql--js--prisma), [3.4](03-dql-et-query-builder.md#34-paramètres) |
| 🟢 **K03** | Les livres publiés entre deux dates | BookRepository | `BETWEEN`, paramètres date | [3.4](03-dql-et-query-builder.md#34-paramètres) |
| 🟢 **K04** | Les livres d'au moins N pages, les plus longs d'abord | BookRepository | **DQL écrit à la main** (`createQuery`) | [3.2](03-dql-et-query-builder.md#32-le-dql--du-sql-qui-parle-objet) |
| 🟢 **K05** | Nombre de livres par auteur, auteurs sans livre compris | BookRepository | `LEFT JOIN`, `COUNT`, `GROUP BY`, résultat scalaire | [3.3](03-dql-et-query-builder.md#33-sql--dql--js--prisma), [3.7](03-dql-et-query-builder.md#37-modes-dhydratation) |
| 🟢 **K06** | Un livre **avec** ses critiques en une seule requête | BookRepository | fetch join, `getOneOrNullResult` | [2.7](02-relations.md#27-le-problème-n1), [3.8](03-dql-et-query-builder.md#38-fetch-join-et-pagination) |
| 🟢 **K07** | Notes moyennes par livre au-dessus d'un seuil | BookRepository | `AVG`, `HAVING`, champs sélectionnés | [3.3](03-dql-et-query-builder.md#33-sql--dql--js--prisma) |
| 🟢 **K08** | Les livres d'une étiquette | BookRepository | jointure ManyToMany | [2.1](02-relations.md#21-les-quatre-types-de-relations) |
| 🟢 **K09** | Recherche à critères optionnels | BookRepository | requête dynamique, `LOWER`, `LIKE` | [3.6](03-dql-et-query-builder.md#36-construire-une-requête-dynamiquement) |
| 🟢 **K10** | Une page de livres + le total | BookRepository | `setFirstResult`, `setMaxResults`, `Paginator` | [3.8](03-dql-et-query-builder.md#38-fetch-join-et-pagination) |
| 🟢 **K11** | Un résumé par livre dans un DTO | BookRepository | `SELECT NEW` | [3.9](03-dql-et-query-builder.md#39-projection-dans-un-dto--select-new) |
| 🟢 **K12** | Marquer comme épuisés les livres anciens | BookRepository | `UPDATE` DQL, `execute()` | [3.10](03-dql-et-query-builder.md#310-update-et-delete-en-dql) |
| 🟢 **K13** | Les livres sans critique | BookRepository | `LEFT JOIN … IS NULL` (ou équivalent) | [3.3](03-dql-et-query-builder.md#33-sql--dql--js--prisma) |
| 🟢 **K14** | Les auteurs ayant reçu une note donnée, sans doublon | AuthorRepository | jointures en chaîne, `distinct` | [3.5](03-dql-et-query-builder.md#35-le-querybuilder) |
| 🟢 **K15** | Les auteurs sans livre | AuthorRepository | **sous-requête `NOT EXISTS`** | [3.3](03-dql-et-query-builder.md#33-sql--dql--js--prisma) |
| 🟢 **K16** | L'auteur le plus critiqué | AuthorRepository | agrégat dans `ORDER BY`, `setMaxResults(1)` | [3.7](03-dql-et-query-builder.md#37-modes-dhydratation) |
| 🔵 **K17** | Créer un livre avec des étiquettes (existantes ou nouvelles) | Library | `persist`, `flush`, réutilisation | [1.5](01-orm-et-mapping.md#15-lunit-of-work-et-les-états-dune-entité) |
| 🔵 **K18** | Ajouter une critique à un livre | Library | `cascade: ['persist']` | [2.5](02-relations.md#25-cascade-orphanremoval-et-ondelete) |
| 🔵 **K19** | Renommer une étiquette | Library | suivi des changements, entité *managed* | [1.5](01-orm-et-mapping.md#15-lunit-of-work-et-les-états-dune-entité) |
| 🟢 **K20** | Transférer les livres d'un auteur à un autre | Library | côté propriétaire vs inverse | [2.2](02-relations.md#22-côté-propriétaire-côté-inverse--le-piège) |
| 🔵 **K21** | Supprimer les critiques d'un livre via la collection | Library | `orphanRemoval` | [2.5](02-relations.md#25-cascade-orphanremoval-et-ondelete) |

---

## 4.6 Indices

Ouvrez un indice **seulement** après avoir vraiment cherché. Ils sont progressifs : lisez le premier, réessayez,
puis le suivant.

<details>
<summary>K01 — tri</summary>

1. Dans un repository, `$this->createQueryBuilder('b')` part déjà de `SELECT b FROM Book b`.
2. Il ne manque que le tri, puis `getQuery()->getResult()`.
3. Pas de `findAll()` + `usort()` : le tri se fait en base.
</details>

<details>
<summary>K02 — jointure + paramètre</summary>

1. On ne filtre pas sur `author_id` mais sur le **nom** de l'auteur : il faut une jointure sur la relation `b.author`
   (≈ `where: { author: { name } }` en Prisma).
2. La jointure sert seulement à filtrer : pas besoin d'`addSelect`.
3. Le nom passe par un paramètre nommé, jamais par concaténation. Un nom inconnu doit donner `[]`, pas une exception.
</details>

<details>
<summary>K03 — dates</summary>

1. `BETWEEN :from AND :to` inclut les bornes.
2. Passez directement les `\DateTimeImmutable` à `setParameter()` : Doctrine les convertit.
3. Attention au tri demandé : chronologique, pas alphabétique.
</details>

<details>
<summary>K04 — DQL à la main</summary>

1. `$this->getEntityManager()->createQuery('...')`.
2. Dans la chaîne DQL, la classe s'écrit en entier : `App\Kata\Entity\Book`. Dans une chaîne PHP entre apostrophes,
   un seul `\` suffit.
3. Testez d'abord la requête avec `make dql` en remplaçant le paramètre par une valeur.
</details>

<details>
<summary>K05 — compter par auteur</summary>

1. Partir des **auteurs** (sinon Margaret Atwood n'apparaît jamais) : une requête DQL sur `App\Kata\Entity\Author`, ou
   un QueryBuilder depuis le gestionnaire d'entités.
2. `LEFT JOIN` sur la collection `books`, `COUNT()` sur l'id du livre (pas `COUNT(*)`), `GROUP BY` l'auteur.
3. Vous obtenez une liste de lignes `['name' => …, 'total' => …]`. Transformez-la en `['nom' => total]`
   (≈ `Object.fromEntries(rows.map(r => [r.name, r.total]))`) : regardez `array_column()` avec un 3e argument.
   Et pensez au cast en `int`.
</details>

<details>
<summary>K06 — fetch join</summary>

1. Le test vérifie `isInitialized()` sur la collection : les critiques doivent arriver **avec** le livre
   (≈ `include: { reviews: true }`).
2. `leftJoin` (un livre sans critique doit être trouvé quand même) + `addSelect`.
3. Un seul livre attendu, `null` s'il n'existe pas : quelle méthode d'hydratation ?
</details>

<details>
<summary>K07 — moyennes</summary>

1. On ne veut pas des entités mais deux valeurs : `select('b.title AS title', 'AVG(r.rating) AS average')`.
2. Un filtre sur un agrégat va dans `HAVING`, pas dans `WHERE`.
3. `AVG` revient en string : `(float)` puis `round(..., 2)`. Le tri secondaire par titre départage les égalités.
</details>

<details>
<summary>K08 — ManyToMany</summary>

1. La jointure sur `b.tags` traverse la table `kata_book_tag` sans que vous ayez à la nommer.
2. Ensuite, c'est un filtre ordinaire sur le nom de l'étiquette.
</details>

<details>
<summary>K09 — recherche dynamique</summary>

1. Construisez le QueryBuilder dans une variable, puis ajoutez un `andWhere()` **par critère non null** — comme
   l'objet `params` qu'on remplit côté front avant d'appeler l'API.
2. Le pays est sur l'auteur : la jointure peut être faite systématiquement.
3. Insensible à la casse : `LOWER()` côté DQL **et** `mb_strtolower()` sur la valeur, avec les `%` dans le paramètre.
4. Attention : `false` n'est pas `null`. Un `if ($outOfPrint)` serait faux.
</details>

<details>
<summary>K10 — pagination</summary>

1. Page 1 → offset 0 ; page 2 avec 4 éléments par page → offset 4 (≈ `skip` / `take`).
2. Le total doit compter **tous** les livres, pas ceux de la page : c'est le rôle de `count($paginator)`.
3. Le test exige une **liste** : `iterator_to_array($paginator, false)`.
</details>

<details>
<summary>K11 — SELECT NEW</summary>

1. Uniquement en DQL écrit à la main : `SELECT NEW App\Kata\Dto\BookSummary(..., ..., ...) FROM ...`.
2. Les arguments suivent l'ordre du constructeur de `BookSummary`.
3. Pour compter les critiques sans perdre les livres qui n'en ont pas : `LEFT JOIN` + `COUNT` + `GROUP BY`.
</details>

<details>
<summary>K12 — UPDATE de masse</summary>

1. `UPDATE App\Kata\Entity\Book b SET ... WHERE ...` puis `execute()`, qui renvoie le nombre de lignes modifiées
   (≈ `updateMany`).
2. Le deuxième appel doit renvoyer 0 : ne mettez à jour que les livres qui ne sont pas déjà épuisés.
3. Le test fait `forgetEverything()` avant de relire. Pourquoi est-ce indispensable ici ?
</details>

<details>
<summary>K13 — sans critique</summary>

1. `LEFT JOIN` sur les critiques : un livre sans critique donne une ligne où l'alias de la critique est `NULL`.
2. D'autres solutions existent (`SIZE(...) = 0`, `NOT EXISTS`) : comparez le SQL généré par chacune.
</details>

<details>
<summary>K14 — sans doublon</summary>

1. Deux jointures en chaîne : auteur → livres → critiques.
2. Victor Hugo a deux critiques à 5 sur le même livre : sans `distinct()`, il apparaît deux fois.
</details>

<details>
<summary>K15 — NOT EXISTS</summary>

1. Construisez la sous-requête avec un **second** QueryBuilder (`$this->getEntityManager()->createQueryBuilder()`),
   qui sélectionne des livres dont l'auteur est l'auteur de la requête principale.
2. La sous-requête peut référencer l'alias de la requête principale.
3. `->where('NOT EXISTS (' . $sub->getDQL() . ')')`.
</details>

<details>
<summary>K16 — le plus critiqué</summary>

1. Joindre jusqu'aux critiques, regrouper par auteur.
2. On peut trier sur un agrégat : `orderBy('COUNT(r.id)', 'DESC')`.
3. Un seul résultat : `setMaxResults(1)` et la méthode d'hydratation qui accepte « rien ».
</details>

<details>
<summary>K17 — créer avec des étiquettes</summary>

1. Pour chaque nom d'étiquette : la chercher (`findOneBy`) ; sinon la créer.
2. Le mapping de `Book::$tags` n'a **pas** de `cascade` : une étiquette **nouvelle** doit être `persist()` explicitement.
3. Un seul `flush()` à la fin.
</details>

<details>
<summary>K18 — ajouter une critique</summary>

1. Le constructeur de `Review` l'ajoute déjà à la collection du livre.
2. Le livre est *managed* et `Book::$reviews` a `cascade: ['persist']`. Quel appel devient inutile ?
</details>

<details>
<summary>K19 — renommer</summary>

1. Charger, modifier avec la méthode de l'entité, `flush()`.
2. Si vous écrivez `persist()`, demandez-vous pourquoi il ne sert à rien.
3. Ne pas oublier l'exception si l'étiquette n'existe pas.
</details>

<details>
<summary>K20 — transférer</summary>

1. Ajouter les livres à `$to->getBooks()` ne change **rien** en base : c'est le côté inverse (pousser dans un
   tableau ne suffit pas, contrairement à un store).
2. C'est sur chaque livre (côté propriétaire) qu'il faut agir, avec la méthode qui synchronise les deux côtés.
3. Piège : modifier une collection pendant qu'on la parcourt. Parcourez une **copie** (`toArray()`), comme on
   ferait `[...arr].forEach(...)` en JS.
</details>

<details>
<summary>K21 — orphanRemoval</summary>

1. Contrainte de l'exercice : ni `remove()` ni `DELETE`. Il faut **retirer** les critiques de la collection du livre.
2. Même piège qu'en K20 : ne pas retirer des éléments d'une collection en cours de parcours.
3. Comptez **avant** de supprimer.
</details>

---

## 4.7 Organisation à trois

| Ordre | Qui | Quoi |
|---|---|---|
| 1 | Chacun seul | Niveau 1 (K01 → K04, K08, K09, K13), sur sa branche `kata/<prénom>` |
| 2 | Chacun seul | Niveau 2 (K05, K07, K10, K11, K14 → K16) |
| 3 | Chacun seul | Niveau 3 du socle : K06, K12, K20 |
| 4 | Tous + formateur | Débrief collectif sur **K06** (N+1), **K12** (Unit of Work contourné) et **K20** (côté propriétaire) : chacun explique une des trois — dès que les trois devs ont fini le niveau 3 |

Celui qui a terminé relit les katas d'un collègue (l'auteur explique, le relecteur pose des questions) ou attaque les bonus.

- Les branches `kata/<prénom>` ne sont **jamais mergées** : le kata est un entraînement, pas une fonctionnalité.
- Pas de copie entre collègues : comparer **après** avoir terminé, c'est justement le but de la revue croisée.
- La solution de référence est chez le formateur. Il existe souvent plusieurs bonnes réponses : l'important est de
  savoir justifier la sienne.

---

## 4.8 Et ensuite : le lien avec le projet

Ce que vous venez de pratiquer sert directement dans les [tickets du projet](../05-projet/backlog-back.md) :

| Notion du kata | Utilisée dans |
|---|---|
| Relations ManyToOne et côté propriétaire (K02, K20) | **B1** `Task → Project`, **B2** `Task → User` (`author`, `assignee`), **B3** `Comment → Task / User` |
| `COUNT` + `GROUP BY` / `SIZE` / résultats mixtes (K05, K11) | **B1** `taskCount`, **B3** `commentCount`, sans N+1 |
| Fetch join (K06) | **B1**/**B2**/**B3** : `GET /api/tasks` renvoie projet, auteur, assigné → à charger en une requête |
| Requête dynamique (K09) | **B3** filtres `status`, `projectId`, `search` |
| Paginator (K10) | **B3** `page`, `limit` et en-tête `X-Total-Count` (que le front lit pour sa pagination) |
| Unicité, `findOneBy` (K17) | **B1** nom de projet unique, **B2** email unique |
| `persist` / cascade / orphanRemoval (K17, K18, K21) | **B3** création et suppression des commentaires |
| `UPDATE` DQL qui contourne l'Unit of Work (K12) | **B3** : comprendre pourquoi `updatedAt` doit passer par un listener et des entités chargées |
| Suivi des changements (K19) | Tous les `PATCH` |

➡️ Suite : [backlog du projet](../05-projet/backlog-back.md)
