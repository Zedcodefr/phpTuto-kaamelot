# 2. SQL et bases relationnelles

Côté front, une « donnée » est un objet JSON, éventuellement imbriqué :

```json
{ "id": 1, "title": "Les Misérables", "author": { "name": "Victor Hugo", "country": "France" },
  "tags": ["classique"], "reviews": [{ "rating": 5 }, { "rating": 4 }] }
```

Côté back, cette même donnée est **éclatée en plusieurs tables** reliées entre elles. Ce cours explique pourquoi,
et comment on interroge ces tables en **SQL**. Tout se pratique sur la vraie base du projet.

## 2.1 Du JSON aux tables

| JSON (front) | Base relationnelle (back) |
|---|---|
| Un tableau d'objets `[{...}, {...}]` | Une **table** |
| Un objet | Une **ligne** |
| Une propriété | Une **colonne**, avec un **type** fixe |
| Un objet imbriqué (`author: {...}`) | Une **autre table**, reliée par une **clé étrangère** |
| Un tableau imbriqué (`tags: [...]`) | Une autre table **+ une table de jointure** |
| Rien n'empêche `"title": 42` | Le type de colonne et les **contraintes** l'empêchent |

Le livre ci-dessus devient, dans la base du kata Doctrine :

```
kata_author                    kata_book                              kata_review
┌────┬─────────────┬────────┐  ┌────┬────────────────┬───────────┐   ┌────┬─────────┬────────┐
│ id │ name        │country │  │ id │ title          │ author_id │   │ id │ book_id │ rating │
├────┼─────────────┼────────┤  ├────┼────────────────┼───────────┤   ├────┼─────────┼────────┤
│ 1  │ Victor Hugo │ France │◀─│ 1  │ Les Misérables │     1     │◀──│ 1  │    1    │   5    │
└────┴─────────────┴────────┘  └────┴────────────────┴───────────┘   │ 2  │    1    │   4    │
                                       ▲                             └────┴─────────┴────────┘
kata_tag              kata_book_tag    │
┌────┬───────────┐    ┌─────────┬────────┐
│ id │ name      │◀───│ tag_id  │ book_id│
│ 1  │ classique │    │    1    │   1    │
└────┴───────────┘    └─────────┴────────┘
```

### Types courants (PostgreSQL)

| Type SQL | Équivalent JS/TS | Exemple |
|---|---|---|
| `INTEGER`, `BIGINT` | `number` (entier) | `pages` |
| `NUMERIC(10,2)` | `number`… mais **exact** (pas d'erreur d'arrondi) | un prix |
| `VARCHAR(255)`, `TEXT` | `string` (avec ou sans limite) | `title`, `description` |
| `BOOLEAN` | `boolean` | `out_of_print` |
| `DATE`, `TIMESTAMP WITH TIME ZONE` | `string` ISO / `Date` | `published_at`, `created_at` |
| `NULL` | `null` (absence de valeur) | `description` facultative |

## 2.2 Clés et contraintes

| Notion | Rôle | Analogie front |
|---|---|---|
| **Clé primaire** (`PRIMARY KEY`) | Identifie une ligne de façon unique (`id`) | Le `:key` d'un `v-for`… mais garanti par la base |
| **Clé étrangère** (`FOREIGN KEY`) | `kata_book.author_id` doit exister dans `kata_author.id` | Une référence qui ne peut **pas** pointer dans le vide |
| `NOT NULL` | Valeur obligatoire | `required` — mais impossible à contourner |
| `UNIQUE` | Pas de doublon (`kata_tag.name`, un email) | Une vérification « déjà pris » côté serveur |
| `CHECK` | Règle sur la valeur (`status IN ('todo','in_progress','done')`) | Un `enum` TypeScript… appliqué à la donnée elle-même |

Les contraintes sont la **dernière ligne de défense** : même si le code PHP a un bug, la base refuse une donnée incohérente.

## 2.3 Relations

| Relation | Exemple | Où est la clé ? |
|---|---|---|
| **1-N** (un-à-plusieurs) | Un auteur a plusieurs livres | Côté « plusieurs » : `kata_book.author_id` |
| **N-N** (plusieurs-à-plusieurs) | Un livre a plusieurs étiquettes, une étiquette a plusieurs livres | Dans une **table de jointure** : `kata_book_tag(book_id, tag_id)` |
| **1-1** | Un utilisateur a un profil | Sur l'une des deux tables, avec `UNIQUE` |

Dans TaskBoard (étape 5) : une tâche appartient à **un** projet (1-N), une tâche a **plusieurs** commentaires (1-N),
une tâche a **un** auteur et **un** assigné (deux relations 1-N vers `user`).

## 2.4 Normalisation : pourquoi ne pas tout mettre en JSON dans une colonne

Le réflexe front serait de stocker `tags: ["classique","aventure"]` dans une colonne. Problèmes :

- **Renommer** une étiquette = parcourir et réécrire toutes les lignes qui la contiennent.
- **Rechercher** « tous les livres *aventure* » = lire et décoder chaque ligne (pas d'index efficace).
- **Garantir** que l'étiquette existe, ou compter les livres par étiquette : impossible proprement.
- **Dupliquer** le nom de l'auteur dans chaque livre : une faute de frappe crée « deux » auteurs.

Règle simple : **chaque information est stockée à un seul endroit**, et on la relie par des clés.
(PostgreSQL sait stocker du JSON, utile pour des données vraiment libres, mais ce n'est pas le cas par défaut.)

## 2.5 Les requêtes essentielles

### Lire : `SELECT`

```sql
SELECT title, pages            -- les colonnes voulues           ≈ .map(b => ({ title: b.title, pages: b.pages }))
FROM kata_book                 -- la table
WHERE pages > 500              -- le filtre                       ≈ .filter(b => b.pages > 500)
ORDER BY pages DESC            -- le tri                          ≈ .sort((a, b) => b.pages - a.pages)
LIMIT 10 OFFSET 0;             -- la pagination                   ≈ .slice(0, 10)
```

| SQL | Méthode de tableau JS |
|---|---|
| `SELECT colonnes` | `.map()` |
| `WHERE` | `.filter()` |
| `ORDER BY` | `.sort()` |
| `LIMIT` / `OFFSET` | `.slice()` |
| `COUNT`, `SUM`, `AVG` | `.reduce()` / `.length` |
| `GROUP BY` | `Object.groupBy()` + un `reduce` par groupe |
| `JOIN` | un `.find()` dans un autre tableau pour chaque élément |
| `EXISTS (...)` | `.some()` |

**Pourquoi ne pas charger toute la table et filtrer en PHP ?** Pour la même raison qu'on ne télécharge pas un million
de lignes dans le navigateur pour faire un `.filter()` : la base a des **index**, ne transfère que le résultat,
et fait ce travail des centaines de fois plus vite. **On filtre, trie, pagine et agrège en SQL.**

### Écrire : `INSERT`, `UPDATE`, `DELETE`

```sql
INSERT INTO task (title, status) VALUES ('Apprendre SQL', 'todo') RETURNING id;
UPDATE task SET status = 'done' WHERE id = 5;    -- ⚠️ sans WHERE : TOUTES les lignes
DELETE FROM task WHERE id = 5;                   -- ⚠️ sans WHERE : TOUTE la table
```

`RETURNING` (spécifique à PostgreSQL) renvoie la ligne écrite : pratique pour connaître l'`id` généré.

### Joindre : `INNER JOIN` et `LEFT JOIN`

```sql
SELECT b.title, a.name
FROM kata_book b
JOIN kata_author a ON a.id = b.author_id;        -- INNER JOIN : seulement les livres qui ont un auteur
```

```
INNER JOIN : seulement les correspondances      LEFT JOIN : toutes les lignes de gauche,
                                                 NULL quand il n'y a pas de correspondance
 auteurs        livres                           auteurs        livres
 ┌───────┐    ┌────────┐                         ┌───────┐    ┌────────┐
 │ Hugo  │────│ Misér. │                         │ Hugo  │────│ Misér. │
 │ Verne │────│ 20 000 │                         │ Verne │────│ 20 000 │
 │Atwood │    └────────┘   ← Atwood absente      │Atwood │────  NULL      ← Atwood présente
 └───────┘                                       └───────┘
```

`LEFT JOIN` sert dès qu'on veut **aussi** les lignes sans correspondance : « tous les auteurs, même sans livre »,
« les livres sans critique » (`LEFT JOIN ... WHERE r.id IS NULL`).

### Agréger : `GROUP BY`, `COUNT`, `AVG`, `HAVING`

```sql
SELECT a.name, COUNT(b.id) AS livres
FROM kata_author a
LEFT JOIN kata_book b ON b.author_id = a.id
GROUP BY a.id, a.name                -- une ligne de résultat par auteur
HAVING COUNT(b.id) >= 2              -- filtre APRÈS regroupement (WHERE filtre AVANT)
ORDER BY livres DESC;
```

`WHERE` filtre les **lignes**, `HAVING` filtre les **groupes**.

### Sous-requête : `EXISTS`

```sql
SELECT a.name FROM kata_author a
WHERE EXISTS (SELECT 1 FROM kata_book b WHERE b.author_id = a.id AND b.out_of_print);
-- ≈ authors.filter(a => books.some(b => b.authorId === a.id && b.outOfPrint))
```

## 2.6 Index

Un **index** est une structure (souvent un arbre) qui permet de trouver des lignes sans lire toute la table,
comme l'index d'un livre. Les clés primaires et étrangères en ont un ; on en ajoute sur les colonnes
souvent filtrées ou triées (`status`, `email`…).

Coût : chaque écriture doit aussi mettre l'index à jour. On indexe ce qui est **lu souvent**, pas tout.
Pour voir si une requête utilise un index : `EXPLAIN SELECT ...;` dans psql.

## 2.7 Transactions et ACID

Une **transaction** regroupe plusieurs écritures qui réussissent **toutes** ou **aucune**.

```sql
BEGIN;
UPDATE account SET balance = balance - 100 WHERE id = 1;
UPDATE account SET balance = balance + 100 WHERE id = 2;
COMMIT;      -- ou ROLLBACK; pour tout annuler
```

Sans transaction, une panne entre les deux `UPDATE` ferait disparaître 100 €. C'est l'équivalent d'un
`Promise.all` qui serait **annulé en bloc** si une promesse échoue, sauf que la base le garantit même si le serveur s'arrête.

| Lettre | Garantie |
|---|---|
| **A**tomicité | Tout ou rien |
| **C**ohérence | Les contraintes sont respectées à la fin |
| **I**solation | Deux transactions simultanées ne se voient pas à moitié faites |
| **D**urabilité | Un `COMMIT` confirmé survit à un crash |

Doctrine (étape 4) ouvre une transaction à chaque `flush()` : vous en bénéficierez sans l'écrire.

## 2.8 Migrations : le schéma versionné

Le schéma (tables, colonnes, contraintes) évolue avec le code. Une **migration** est un fichier qui décrit une
évolution (`CREATE TABLE`, `ALTER TABLE ... ADD COLUMN`), rejouable dans l'ordre sur chaque base.
C'est un **historique**, comme les commits Git du schéma :

- dans `apps/api-php/database/schema.sql` : un script unique, rejoué en entier (`make php-db-reset`) ;
- dans Symfony : `apps/api-symfony/migrations/Version*.php`, générées par `make sf-migration`, appliquées par `make sf-migrate`.

Règle d'équipe : on ne modifie **jamais** une migration déjà partagée ; on en ajoute une nouvelle.

## 2.9 Exercices sur la vraie base

Deux bases sont disponibles :

| Commande | Base | Tables |
|---|---|---|
| `make php-psql` | `taskboard_php` | `task` (API PHP pur) |
| `make sf-psql` | `taskboard` | `task` (API Symfony) et les tables du kata : `kata_author`, `kata_book`, `kata_review`, `kata_tag`, `kata_book_tag` |

Dans psql : `\dt` liste les tables, `\d kata_book` décrit une table, `\q` quitte. Terminez chaque requête par `;`.
Vous pouvez aussi utiliser **Adminer** : http://localhost:8090 (serveur `db`, utilisateur `app`, mot de passe `app`).

Écrivez chaque requête vous-même, puis comparez avec le résultat attendu.

**E1 —** (`make php-psql`) Nombre de tâches par statut, triées par statut.
<details><summary>Résultat attendu (données de départ)</summary>

```sql
SELECT status, COUNT(*) FROM task GROUP BY status ORDER BY status;
```
| status | count |
|---|---|
| done | 1 |
| in_progress | 1 |
| todo | 2 |
</details>

Les exercices suivants se font dans `make sf-psql`.

**E2 —** Titre et nombre de pages des livres de plus de 500 pages, du plus long au plus court.
<details><summary>Résultat attendu</summary>

```sql
SELECT title, pages FROM kata_book WHERE pages > 500 ORDER BY pages DESC;
```
Les Misérables (1900) · 1Q84 (1100) · Notre-Dame de Paris (940) · Kafka sur le rivage (640)
</details>

**E3 —** Les livres épuisés (`out_of_print`).
<details><summary>Résultat attendu</summary>

```sql
SELECT title FROM kata_book WHERE out_of_print;
```
Voyage au centre de la Terre
</details>

**E4 —** Titre et nom de l'auteur des livres d'auteurs français, triés par titre (`JOIN`).
<details><summary>Résultat attendu</summary>

```sql
SELECT b.title, a.name
FROM kata_book b JOIN kata_author a ON a.id = b.author_id
WHERE a.country = 'France' ORDER BY b.title;
```
Le Tour du monde en quatre-vingts jours (Jules Verne) · Les Misérables (Victor Hugo) · Notre-Dame de Paris (Victor Hugo) ·
Vingt mille lieues sous les mers (Jules Verne) · Voyage au centre de la Terre (Jules Verne)
</details>

**E5 —** Nombre de livres par auteur, **y compris** les auteurs sans livre, triés par nom.
<details><summary>Résultat attendu</summary>

```sql
SELECT a.name, COUNT(b.id) AS livres
FROM kata_author a LEFT JOIN kata_book b ON b.author_id = a.id
GROUP BY a.id, a.name ORDER BY a.name;
```
Agatha Christie 2 · Haruki Murakami 2 · Jules Verne 3 · Margaret Atwood 0 · Victor Hugo 2

Pourquoi `COUNT(b.id)` et pas `COUNT(*)` ? Avec `COUNT(*)`, Margaret Atwood compterait 1 (la ligne du `LEFT JOIN` existe, remplie de `NULL`).
</details>

**E6 —** Note moyenne (arrondie à 2 décimales) des livres dont la moyenne est d'au moins 4, de la meilleure à la moins bonne.
<details><summary>Résultat attendu</summary>

```sql
SELECT b.title, ROUND(AVG(r.rating), 2) AS moyenne
FROM kata_book b JOIN kata_review r ON r.book_id = b.id
GROUP BY b.id, b.title
HAVING AVG(r.rating) >= 4
ORDER BY moyenne DESC, b.title;
```
Vingt mille lieues sous les mers 5.00 · Les Misérables 4.67 · Le Crime de l'Orient-Express 4.33 · Kafka sur le rivage 4.00
</details>

**E7 —** Les livres qui n'ont reçu **aucune** critique, triés par titre (deux écritures possibles).
<details><summary>Résultat attendu</summary>

```sql
SELECT b.title FROM kata_book b
WHERE NOT EXISTS (SELECT 1 FROM kata_review r WHERE r.book_id = b.id)
ORDER BY b.title;
-- ou : LEFT JOIN kata_review r ON r.book_id = b.id WHERE r.id IS NULL
```
1Q84 · Voyage au centre de la Terre
</details>

**E8 —** Les livres portant l'étiquette `science-fiction`, triés par titre (relation N-N).
<details><summary>Résultat attendu</summary>

```sql
SELECT b.title
FROM kata_book b
JOIN kata_book_tag bt ON bt.book_id = b.id
JOIN kata_tag t ON t.id = bt.tag_id
WHERE t.name = 'science-fiction'
ORDER BY b.title;
```
1Q84 · Vingt mille lieues sous les mers · Voyage au centre de la Terre
</details>

**E9 —** La **page 2** des livres triés par titre, avec 4 livres par page.
<details><summary>Résultat attendu</summary>

```sql
SELECT title FROM kata_book ORDER BY title LIMIT 4 OFFSET 4;
```
Les Misérables · Mort sur le Nil · Notre-Dame de Paris · Vingt mille lieues sous les mers

`OFFSET = (page - 1) × taille de page`. Sans `ORDER BY`, l'ordre n'est **pas garanti** : la pagination serait incohérente.
</details>

**E10 —** Nombre de livres par étiquette, de la plus utilisée à la moins utilisée (à égalité : par nom).
<details><summary>Résultat attendu</summary>

```sql
SELECT t.name, COUNT(*) AS livres
FROM kata_tag t JOIN kata_book_tag bt ON bt.tag_id = t.id
GROUP BY t.name ORDER BY livres DESC, t.name;
```
classique 7 · aventure 3 · science-fiction 3 · contemporain 2 · policier 2
</details>

**Bonus —** Transaction : dans `make php-psql`, lancez `BEGIN;`, supprimez toutes les tâches (`DELETE FROM task;`),
vérifiez avec un `SELECT` qu'elles ont disparu, puis `ROLLBACK;` et vérifiez qu'elles sont revenues.
(Si quelque chose tourne mal : `make php-db-reset` recrée les données de départ.)

## À retenir

- Une donnée « imbriquée » côté front est **éclatée en tables** reliées par des **clés** côté back.
- Les **contraintes** (`NOT NULL`, `UNIQUE`, `CHECK`, clés étrangères) protègent la donnée même si le code a un bug.
- `WHERE` ≈ `filter`, `ORDER BY` ≈ `sort`, `LIMIT/OFFSET` ≈ `slice`, `GROUP BY` + agrégats ≈ `reduce` : mais **en base**, pas en mémoire.
- `LEFT JOIN` garde les lignes sans correspondance ; `HAVING` filtre les groupes.
- Une **transaction** fait tout ou rien ; une **migration** versionne le schéma.

Suite : [Sécurité côté serveur](03-securite-cote-serveur.md).
