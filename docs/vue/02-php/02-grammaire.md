# 2. La grammaire PHP pour un dev JS / TS

> Objectif : lire et écrire du PHP moderne sans traduire du JavaScript mot à mot.
> Les exemples ont été exécutés sur PHP 8.4 (`make php-sh`, puis `php -a` pour essayer).

## Sommaire

- [Tableau récapitulatif JS / TS → PHP](#tableau-récapitulatif-js--ts--php)
- [2.1 Les bases de la syntaxe](#21-les-bases-de-la-syntaxe)
- [2.2 Variables et types](#22-variables-et-types)
- [2.3 Chaînes de caractères](#23-chaînes-de-caractères)
- [2.4 Le tableau PHP (`array`)](#24-le-tableau-php-array)
- [2.5 Comparaisons, null et vérité](#25-comparaisons-null-et-vérité)
- [2.6 Structures de contrôle](#26-structures-de-contrôle)
- [2.7 Fonctions](#27-fonctions)
- [2.8 Classes et objets](#28-classes-et-objets)
- [2.9 Enums](#29-enums)
- [2.10 Attributs](#210-attributs)
- [2.11 Namespaces et `use`](#211-namespaces-et-use)
- [2.12 Exceptions](#212-exceptions)
- [2.13 Dates](#213-dates)
- [2.14 Pas de génériques : PHPDoc et PHPStan](#214-pas-de-génériques--phpdoc-et-phpstan)
- [2.15 Les 15 pièges pour un dev JS](#215-les-15-pièges-pour-un-dev-js)

---

## Tableau récapitulatif JS / TS → PHP

| JS / TS | PHP | Remarque |
|---|---|---|
| `const x = 1` / `let x = 1` | `$x = 1;` | toujours `$`, pas de mot-clé, pas de `const` local |
| `'a' + 'b'` | `'a' . 'b'` | `+` ne concatène **jamais** |
| `` `Bonjour ${name}` `` | `"Bonjour $name"` / `"Bonjour {$user->name}"` | interpolation seulement entre `"` |
| `obj.prop`, `obj.method()` | `$obj->prop`, `$obj->method()` | |
| `Class.staticMethod()` | `ClassName::staticMethod()` | `::` pour le statique et les constantes |
| `[1, 2, 3]` | `[1, 2, 3]` | un `array`, **copié** à l'affectation |
| `{ id: 1, title: 'x' }` | `['id' => 1, 'title' => 'x']` | le même `array` sert de dictionnaire |
| `arr.length` | `count($arr)` | |
| `arr.push(x)` | `$arr[] = $x;` | |
| `arr.map(fn)` | `array_map($fn, $arr)` | ordre des arguments inversé ! |
| `arr.filter(fn)` | `array_filter($arr, $fn)` | garde les clés d'origine |
| `arr.includes(x)` | `in_array($x, $arr, true)` | pensez au `true` (comparaison stricte) |
| `Object.keys(o)` | `array_keys($a)` | |
| `'key' in obj` | `array_key_exists('key', $a)` | |
| `a === b` | `$a === $b` | mêmes opérateurs, règles de `==` différentes |
| `a ?? b`, `a?.b`, `a ??= b` | `$a ?? $b`, `$a?->b`, `$a ??= $b` | |
| `undefined` / `null` | `null` uniquement | |
| `str.length` | `mb_strlen($str)` | `strlen()` compte les **octets** |
| `str.includes('x')` | `str_contains($str, 'x')` | |
| `JSON.stringify(x)` / `JSON.parse(s)` | `json_encode($x)` / `json_decode($s, true)` | |
| `(x) => x * 2` | `fn ($x) => $x * 2` | capture **par valeur** |
| `function (x) { ... }` | `function ($x) use ($y) { ... }` | variables extérieures listées dans `use` |
| `switch` | `match` (expression) / `switch` | `match` compare en `===` |
| `for (const x of arr)` | `foreach ($arr as $x)` | |
| `for (const [k, v] of Object.entries(o))` | `foreach ($a as $k => $v)` | |
| `class A extends B implements C` | `class A extends B implements C` | identique |
| `private x: number` | `private int $x;` | type **avant** le nom |
| `x?: number` / `number \| null` | `?int $x = null` | |
| `type S = 'a' \| 'b'` / `enum` | `enum S: string { case A = 'a'; ... }` | |
| `@Decorator()` | `#[Attribute]` | métadonnées lues par réflexion |
| `import { X } from './x'` | `use App\X;` | on importe un nom de classe, pas un fichier |
| `throw new Error('x')` | `throw new \RuntimeException('x');` | |
| `new Date()` | `new \DateTimeImmutable()` | |
| `console.log(x)` | `error_log(print_r($x, true));` / `var_dump($x)` | |

---

## 2.1 Les bases de la syntaxe

```php
<?php

declare(strict_types=1);

// Commentaire sur une ligne
# Commentaire sur une ligne (rare)
/* Commentaire sur plusieurs lignes */

/**
 * Docblock (≈ JSDoc) : lu par les éditeurs et par PHPStan.
 */
function greet(string $name): string
{
    return "Bonjour $name";
}

echo greet('Alice');   // le point-virgule est OBLIGATOIRE, pas d'ASI comme en JS
```

- Un fichier PHP commence par `<?php`. Dans un fichier qui ne contient **que** du PHP, on **ne ferme pas** la balise
  (`?>`) : une ligne vide après elle serait envoyée au client et casserait les en-têtes HTTP.
- `;` est obligatoire à la fin de chaque instruction. Il n'y a pas d'insertion automatique.
- `echo` écrit dans la **réponse HTTP** (≈ `res.write()`), pas dans une console.

---

## 2.2 Variables et types

```php
$title = 'Apprendre PHP';   // string
$count = 3;                 // int
$ratio = 0.75;              // float
$done = false;              // bool
$nothing = null;            // null
$tags = ['php', 'symfony']; // array
```

- Toutes les variables commencent par `$`. Pas de `let` ni de `const` : une variable existe dès sa première affectation.
- **Portée** : une variable est locale à la **fonction**. Il n'y a pas de portée de bloc (`if`, `for`) comme avec `let`.
  Une fonction ne voit **pas** les variables extérieures (voir [les closures](#closures--capture-explicite)).
- `const` existe, mais pour des constantes de classe ou globales : `const MAX = 100;`.

### Les types disponibles

| PHP | Équivalent TS |
|---|---|
| `int`, `float` | `number` (PHP distingue entiers et flottants) |
| `string` | `string` |
| `bool` | `boolean` |
| `array` | `T[]` **et** `Record<K, V>` |
| `?string` | `string \| null` |
| `int\|string` | `number \| string` |
| `mixed` | `unknown` / `any` |
| `void` | `void` |
| `never` | `never` (fonction qui lance toujours une exception) |
| `object`, `callable`, `iterable` | `object`, `Function`, `Iterable` |
| un nom de classe / d'interface | une classe / interface TS |

```php
function find(int $id): ?Task { /* ... */ }        // retourne Task ou null
function parse(int|string $value): float { /* ... */ }
```

Divisions : `7 / 2` vaut `3.5` (un `float`) ; `intdiv(7, 2)` vaut `3`. Et comme en JS, `0.1 + 0.2 == 0.3` est `false`.

---

## 2.3 Chaînes de caractères

### Concaténation : `.` et jamais `+`

```php
$full = 'Bonjour ' . $name . ' !';   // le point concatène
$full .= ' Ça va ?';                  // ≈ +=
```

`+` est **uniquement arithmétique** :

```php
'5' + '3';      // 8 (deux chaînes numériques → addition)
'5' . '3';      // '53'
'a' + 1;        // TypeError : Unsupported operand types: string + int
```

### Interpolation

| JS | PHP |
|---|---|
| `` `Bonjour ${name}` `` | `"Bonjour $name"` |
| `` `Tâche ${task.title}` `` | `"Tâche {$task->title}"` (ou `"Tâche $task->title"`) |
| `` `Total : ${a + b}` `` | impossible : `'Total : ' . ($a + $b)` |
| `'texte brut'` | `'texte brut'` : **aucune** interpolation entre apostrophes |

```php
$id = 42;
echo "Task $id not found";    // Task 42 not found
echo 'Task $id not found';    // Task $id not found  (tel quel)
echo "Ligne 1\nLigne 2";      // \n interprété seulement entre guillemets
```

Seules des **variables** s'interpolent, jamais des expressions arbitraires.

### Heredoc et nowdoc

L'équivalent des template literals sur plusieurs lignes :

```php
$sql = <<<SQL
    SELECT *
    FROM task
    WHERE id = :id
    SQL;                      // heredoc : interpole les variables, comme "..."

$doc = <<<'TXT'
    Pas d'interpolation : $id reste $id
    TXT;                      // nowdoc : comme '...'
```

L'indentation de la balise de fin est retirée de toutes les lignes.

### UTF-8 : les chaînes sont des octets

En JS, une chaîne est une suite de caractères UTF-16. En PHP, c'est une suite d'**octets** :

```php
strlen('Été');        // 5  (É et é font 2 octets chacun en UTF-8)
mb_strlen('Été');     // 3  (caractères)
'abc'[0];             // 'a' — mais 'été'[0] renvoie un octet, pas « é »
strtoupper('été');    // ne gère pas les accents : utilisez mb_strtoupper()
```

Dès qu'une chaîne peut contenir des accents (un titre de tâche, un nom), utilisez les fonctions `mb_*` :
`mb_strlen`, `mb_substr`, `mb_strtolower`, `mb_strtoupper`. Le contrat d'API compte les longueurs en **caractères**.

### Fonctions utiles

| JS | PHP |
|---|---|
| `s.length` | `mb_strlen($s)` |
| `s.trim()` | `trim($s)` (aussi `rtrim`, `ltrim`, avec des caractères au choix : `rtrim($path, '/')`) |
| `s.toLowerCase()` | `mb_strtolower($s)` |
| `s.includes('x')` | `str_contains($s, 'x')` |
| `s.startsWith('x')` / `endsWith` | `str_starts_with($s, 'x')` / `str_ends_with` |
| `s.split(',')` | `explode(',', $s)` |
| `arr.join(', ')` | `implode(', ', $arr)` |
| `s.replace(/a/g, 'b')` | `str_replace('a', 'b', $s)` |
| `s.replace(/regex/, ...)` | `preg_replace('#regex#', '...', $s)` |
| `/regex/.test(s)` | `preg_match('#regex#', $s) === 1` |
| `s.padStart(5, '0')` | `str_pad($s, 5, '0', STR_PAD_LEFT)` |
| `String(n)` / `Number(s)` | `(string) $n` / `(int) $s`, `(float) $s` |

Les regex PHP sont des **chaînes** avec un délimiteur au choix : `'#^/api/tasks/(\d+)$#'` (le `#` évite d'échapper les `/`).

---

## 2.4 Le tableau PHP (`array`)

C'est la structure de données centrale de PHP, et la principale source de surprises pour un dev JS.

### Liste et dictionnaire : la même structure

Un `array` PHP est **à la fois** un tableau JS et un objet / `Map` : c'est une table **ordonnée** de paires clé → valeur,
dont les clés sont des `int` ou des `string`.

```php
$list = ['php', 'symfony', 'doctrine'];            // clés 0, 1, 2 implicites
$task = ['id' => 1, 'title' => 'Apprendre PHP'];   // clés string (≈ objet JS)
$mixed = [10 => 'a', 'b', 'x' => 'c'];             // clés 10, 11, 'x' : valide, mais à éviter

$list[] = 'react';               // ≈ push
$task['status'] = 'todo';        // ajout d'une clé
unset($task['status']);          // ≈ delete
count($list);                    // ≈ length
array_is_list($list);            // true : clés 0..n-1 dans l'ordre
array_is_list([1 => 'a']);       // false
```

`json_encode()` produit un **tableau** JSON pour une liste, et un **objet** JSON sinon :

```php
json_encode(['a', 'b']);                 // ["a","b"]
json_encode(['id' => 1]);                // {"id":1}
json_encode([1 => 'a', 3 => 'b']);       // {"1":"a","3":"b"}  — piège classique après array_filter
json_encode([]);                         // []  (un tableau vide est une liste)
```

Il n'y a pas d'objets littéraux comme en JS : pour des données structurées, on utilise un `array` associatif ou, mieux, **une classe**.

### Copie par valeur

**Le piège majeur.** En JS, un tableau ou un objet est passé **par référence** : deux variables pointent vers la même donnée.
En PHP, un `array` est **copié** à l'affectation et au passage en argument :

```js
// JS
const a = [1, 2]
const b = a
b.push(3)
console.log(a)   // [1, 2, 3] — a et b sont le même tableau
```

```php
// PHP
$a = [1, 2];
$b = $a;         // COPIE
$b[] = 3;
print_r($a);     // [1, 2] — $a n'a pas bougé

function addTag(array $tags): void { $tags[] = 'php'; }
addTag($a);      // ne modifie PAS $a : la fonction a reçu une copie
```

Pour modifier un tableau depuis une fonction, **renvoyez-le** : `$tags = addTag($tags);`.

Les **objets**, eux, se comportent comme en JS : `$p = $o;` partage le même objet, et le modifier via `$p` modifie `$o`.

| | JS | PHP |
|---|---|---|
| tableau | partagé | **copié** |
| objet | partagé | partagé (comme JS) |

### Les méthodes de tableau JS, version PHP

PHP n'a **pas de méthodes** sur les tableaux : ce sont des **fonctions globales**, sans chaînage possible,
et l'ordre des arguments n'est pas cohérent (héritage historique).

| JS | PHP |
|---|---|
| `arr.map(t => t.title)` | `array_map(fn ($t) => $t->title, $arr)` — **fonction d'abord** |
| `arr.filter(t => t.done)` | `array_filter($arr, fn ($t) => $t->done)` — **tableau d'abord** |
| `arr.reduce((s, n) => s + n, 0)` | `array_reduce($arr, fn ($s, $n) => $s + $n, 0)` |
| `arr.find(t => t.id === 3)` | `array_find($arr, fn ($t) => $t->id === 3)` (PHP 8.4) |
| `arr.some(...)` / `arr.every(...)` | `array_any(...)` / `array_all(...)` (PHP 8.4) |
| `arr.sort((a, b) => a.id - b.id)` | `usort($arr, fn ($a, $b) => $a->id <=> $b->id)` — trie **sur place**, renvoie `true` |
| `arr.slice(1, 3)` | `array_slice($arr, 1, 2)` (offset, **longueur**) |
| `[...a, ...b]` | `[...$a, ...$b]` ou `array_merge($a, $b)` |
| `arr.map(o => o.title)` sur des tableaux associatifs | `array_column($rows, 'title')` |
| `Object.keys` / `Object.values` | `array_keys($a)` / `array_values($a)` |
| `arr.indexOf(x)` | `array_search($x, $arr, true)` (renvoie `false` si absent) |

```php
// Pas de chaînage : on imbrique, ou on passe par des variables intermédiaires (plus lisible)
$done = array_filter($tasks, fn (Task $t) => $t->status === TaskStatus::Done);
$titles = array_map(fn (Task $t) => $t->title, $done);
```

**`array_filter` conserve les clés** : filtrer `[1, 2, 3, 4]` pour garder les pairs donne `[1 => 2, 3 => 4]`,
qui devient l'objet JSON `{"1":2,"3":4}`. Repassez par `array_values()` si vous voulez une liste.

### Déstructuration et spread

```php
[$first, $second] = ['a', 'b'];                 // ≈ const [first, second] = ...
[, $second] = ['a', 'b'];                       // on saute un élément
['id' => $id, 'title' => $title] = $row;        // ≈ const { id, title } = row

$all = [...$list, 'react'];                     // spread
$merged = [...$defaults, ...$options];          // fonctionne aussi avec des clés string (PHP 8.1+)
foreach ($rows as ['id' => $id, 'title' => $title]) { /* ... */ }
```

---

## 2.5 Comparaisons, null et vérité

### `==` vs `===`

Même principe qu'en JS : `===` compare **type et valeur**, `==` convertit avant de comparer. Mais les règles de conversion
ne sont **pas les mêmes**, donc vos réflexes JS ne s'appliquent pas :

```php
'1' == '01';       // true  (deux chaînes numériques → comparées comme des nombres)
'10' == '1e1';     // true
'abc' == 0;        // false (depuis PHP 8 ; true en PHP 7 !)
null == false;     // true
0 == '';           // false (depuis PHP 8)
in_array('1e1', ['10']);         // true  — in_array compare en == par défaut
in_array('1e1', ['10'], true);   // false — troisième argument : comparaison stricte
```

Règle simple : **utilisez toujours `===` et `!==`**, et passez `true` en troisième argument de `in_array()` et `array_search()`.

`<=>` (*spaceship*) renvoie -1, 0 ou 1 : c'est le comparateur idéal pour `usort()`.

### null, pas d'undefined

PHP n'a **qu'une** valeur vide : `null`. Pas d'`undefined`.

```php
$task = ['title' => 'x', 'description' => null];

$task['nope'];                         // Warning : Undefined array key "nope" (et vaut null)
isset($task['description']);           // false : la clé existe, mais vaut null
array_key_exists('description', $task); // true  : la clé existe
$task['description'] ?? 'aucune';      // 'aucune' (pas d'avertissement)
```

| Question | JS | PHP |
|---|---|---|
| La clé existe-t-elle ? | `'k' in obj` | `array_key_exists('k', $a)` |
| La clé existe et n'est pas null ? | `obj.k != null` | `isset($a['k'])` |
| Valeur ou défaut | `obj.k ?? 'défaut'` | `$a['k'] ?? 'défaut'` |

La différence entre `isset()` et `array_key_exists()` compte dans une API : un PATCH `{"description": null}` signifie
« effacer la description », alors qu'une clé absente signifie « ne pas toucher ».

### Truthy et falsy

| Valeur | JS | PHP |
|---|---|---|
| `0`, `0.0`, `''`, `null`, `false` | falsy | falsy |
| `'0'` | **truthy** | **falsy** ⚠️ |
| `[]` (tableau vide) | **truthy** | **falsy** ⚠️ |
| `'0.0'`, `' '` | truthy | truthy |
| `NaN`, `undefined` | falsy | n'existent pas (`NAN` existe mais est truthy) |

```php
if ($title) { ... }        // ⚠️ un titre "0" serait considéré comme vide
if ($title !== '') { ... } // explicite : préférez-le
```

`empty($x)` teste « absent ou falsy » : `empty('0')` vaut `true`, ce qui surprend souvent.

### Autres opérateurs

```php
$name = $input['name'] ?? 'Anonyme';         // ≈ ??
$length = $user?->profile?->bio;             // ≈ ?.   (sur des objets uniquement)
$options['limit'] ??= 50;                    // ≈ ??=
$label = $done ? 'Terminé' : 'À faire';      // ternaire, identique
$value = $input ?: 'défaut';                 // « Elvis » : $input si truthy, sinon 'défaut' (≈ ||)
```

`?->` ne fonctionne que sur des **objets**. Pour un tableau, c'est `??` qu'il faut.

---

## 2.6 Structures de contrôle

```php
if ($a > 0) {
    // ...
} elseif ($a === 0) {     // elseif en un mot (else if fonctionne aussi)
    // ...
} else {
    // ...
}

foreach ($tasks as $task) { /* ... */ }               // ≈ for (const task of tasks)
foreach ($task as $key => $value) { /* ... */ }       // ≈ for (const [key, value] of Object.entries(task))
for ($i = 0; $i < 10; $i++) { /* ... */ }
while ($row = $statement->fetch()) { /* ... */ }
```

### `match` : un switch qui est une expression

```php
$label = match ($status) {
    'todo' => 'À faire',
    'in_progress' => 'En cours',
    'done' => 'Terminé',
};

$httpTitle = match (true) {
    $code >= 500 => 'Erreur serveur',
    $code >= 400 => 'Erreur client',
    default => 'OK',
};
```

- `match` **renvoie** une valeur (comme une expression `switch` d'autres langages) ;
- il compare en `===` (le `switch` classique compare en `==`) ;
- pas de `break`, pas de fall-through ;
- aucune branche ne correspond et pas de `default` → `UnhandledMatchError`.

### Références `&`

Rarement utiles, mais à reconnaître :

```php
foreach ($tasks as &$task) { $task['done'] = true; }   // modifie le tableau d'origine
unset($task);                                         // ⚠️ indispensable après une boucle par référence

function increment(int &$n): void { $n++; }           // paramètre par référence
```

Oublier `unset()` après un `foreach` par référence est un bug classique : la variable reste liée au dernier élément,
et une seconde boucle sur le même tableau l'écrase. Préférez `array_map()` ou une nouvelle variable.

---

## 2.7 Fonctions

```php
function createTask(string $title, ?string $description = null, bool $urgent = false): Task
{
    return new Task($title, $description);
}

createTask('Apprendre PHP');
createTask(title: 'Apprendre PHP', urgent: true);   // arguments NOMMÉS : on saute $description

function sum(int ...$numbers): int                  // variadique ≈ (...numbers: number[])
{
    return array_sum($numbers);
}

sum(1, 2, 3);
sum(...[1, 2, 3]);                                  // spread à l'appel
```

- Les arguments nommés remplacent le pattern JS de « l'objet d'options » : `createTask({ title, urgent: true })`.
- Avec un tableau **associatif**, le spread produit des arguments nommés : `f(...['id' => '42'])` équivaut à `f(id: '42')`.
  Le routeur des exercices utilise exactement cette mécanique.
- Pas de surcharge : une seule fonction par nom. Utilisez des paramètres optionnels ou des types union.

### Closures : capture explicite

En JS, une fonction capture **automatiquement** toutes les variables extérieures, **par référence** :

```js
let x = 1
const f = () => x
x = 2
f()   // 2 — la closure voit la valeur actuelle
```

En PHP, une fonction anonyme `function` ne voit **rien** de l'extérieur, sauf ce que vous listez dans `use`,
et la capture se fait **par valeur, au moment de la création** :

```php
$x = 1;
$f = function () use ($x) { return $x; };
$x = 2;
$f();   // 1 — $x a été copié à la création

$g = function () use (&$x) { return $x; };   // capture par référence (rarement utile)
$x = 3;
$g();   // 3
```

La **fonction fléchée** `fn` capture automatiquement les variables utilisées, mais toujours **par valeur** :

```php
$min = 3;
$long = array_filter($titles, fn (string $t) => mb_strlen($t) >= $min);   // $min capturé automatiquement
```

- `fn` ne contient qu'**une seule expression** (pas d'accolades, pas de `return`).
- Pour plusieurs instructions, il faut `function () use (...) { ... }`.

### `$this` : pas de piège de liaison

En JS, `this` dépend de la façon dont la fonction est appelée (d'où `bind`, ou les fonctions fléchées dans les classes).
En PHP, `$this` désigne **toujours** l'objet de la méthode, et une closure créée dans une méthode le capture automatiquement :

```php
final class Counter
{
    private int $n = 0;

    public function incrementer(): \Closure
    {
        return function (): int { return ++$this->n; };   // $this est lié à Counter, pas de bind()
    }
}
```

### Callables

Plusieurs façons de passer une fonction en paramètre :

```php
array_map(fn ($t) => $t->title, $tasks);       // closure
array_map(strtoupper(...), $words);            // « first-class callable » (PHP 8.1) : la fonction elle-même
array_map(Task::fromRow(...), $rows);          // méthode statique
$router->get('/api/tasks', $controller->list(...));   // méthode d'un objet, liée à cet objet
```

La syntaxe `nom(...)` (avec trois points littéraux) crée une closure à partir d'une fonction ou d'une méthode.
C'est l'équivalent de passer `controller.list.bind(controller)` en JS.

Une closure stockée dans une propriété ou un tableau s'appelle avec des parenthèses autour :
`($this->handler)($request)` ou `($route['handler'])($request)`.

---

## 2.8 Classes et objets

Dans un projet Vue, les classes sont rares : on manipule des objets littéraux, des composables, des stores.
En PHP (et dans Symfony), **presque tout est une classe** : contrôleurs, services, entités, DTO, exceptions.

### Une classe complète commentée

```php
<?php

declare(strict_types=1);

namespace App\Entity;                        // ≈ le module auquel appartient la classe

use App\Enum\TaskStatus;

final class Task implements \JsonSerializable   // final : on ne peut pas en hériter
{
    private const MAX_TITLE = 255;            // constante de classe : self::MAX_TITLE

    public function __construct(
        public string $title,                 // « constructor promotion » : déclare ET affecte la propriété
        public ?string $description = null,   //  ≈ constructor(public title: string, ...) en TS
        public TaskStatus $status = TaskStatus::Todo,
        public readonly ?int $id = null,      // readonly : affectable une seule fois
    ) {
    }

    public function isDone(): bool
    {
        return $this->status === TaskStatus::Done;   // $this-> obligatoire, pas de raccourci
    }

    public static function fromRow(array $row): self   // self = la classe courante
    {
        return new self(title: $row['title'], id: (int) $row['id']);
    }

    public function jsonSerialize(): array    // ≈ toJSON() en JS
    {
        return ['id' => $this->id, 'title' => $this->title];
    }
}

$task = new Task('Apprendre PHP');
$task->title = 'Apprendre PHP 8.4';
echo $task->isDone() ? 'oui' : 'non';
```

| TS | PHP |
|---|---|
| `this.title` | `$this->title` (le `$this->` n'est jamais implicite) |
| `public` par défaut | `public`, `protected`, `private` : **précisez toujours** |
| `title: string` | `public string $title;` (type **avant** le nom) |
| `constructor(...)` | `public function __construct(...)` |
| `static create()` | `public static function create()`, appelée par `Task::create()` |
| `obj instanceof Task` | `$obj instanceof Task` |
| `new Task()` | `new Task()` |
| `structuredClone(obj)` | `clone $obj` (copie superficielle) |

Une propriété typée **non initialisée** ne vaut pas `undefined` : la lire lance une `Error`.
Initialisez-la (`private array $routes = [];`) ou affectez-la dans le constructeur.

Une classe entièrement immuable s'écrit `final readonly class Request { ... }` : toutes ses propriétés sont `readonly`.

### `$this`, `self`, `static`, `parent`

| Mot-clé | Désigne | Exemple |
|---|---|---|
| `$this` | l'objet courant | `$this->title` |
| `self` | la classe où le code est écrit | `self::MAX_TITLE`, `new self(...)` |
| `static` | la classe réellement appelée (avec l'héritage) | `new static(...)` |
| `parent` | la classe mère | `parent::__construct($message)`, `parent::toArray()` |

`->` sert pour ce qui appartient à un **objet** ; `::` pour ce qui appartient à une **classe** (statique, constante, `parent`).

### Héritage, interfaces, classes abstraites

```php
interface TaskRepositoryInterface
{
    public function find(int $id): ?Task;
}

final class PdoTaskRepository implements TaskRepositoryInterface
{
    public function find(int $id): ?Task { /* ... */ }
}

abstract class HttpError extends \RuntimeException     // abstract : jamais instanciée directement
{
    abstract protected function title(): string;
}

class NotFound extends HttpError
{
    protected function title(): string { return 'Not Found'; }
}
```

**Différence majeure avec TS : le typage est nominal.** En TS, un objet qui a les bonnes méthodes « est » du bon type
(typage structurel). En PHP, une classe doit **déclarer** `implements` :

```php
interface HasName { public function name(): string; }
class Duck { public function name(): string { return 'duck'; } }   // a la méthode, mais n'implémente pas HasName

function greet(HasName $n): string { return $n->name(); }
greet(new Duck());   // TypeError, à l'exécution
```

Les interfaces sont **vérifiées à l'exécution** : elles servent de contrat réel entre deux morceaux de code,
c'est ce qu'utilisent les lots des exercices (`TaskRepositoryInterface`).

### Traits

Un **trait** est un bloc de méthodes et de propriétés « copié-collé » dans une classe par le langage
(un peu comme un mixin, ou un composable qui serait fusionné dans la classe) :

```php
trait Timestampable
{
    private ?\DateTimeImmutable $updatedAt = null;

    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}

final class Task
{
    use Timestampable;   // Task gagne $updatedAt et touch()
}
```

À utiliser avec parcimonie : la composition (injecter un service) est souvent plus claire.

### Property hooks et visibilité asymétrique (PHP 8.4)

Deux nouveautés de PHP 8.4, proches des `get` / `set` de JS :

```php
final class User
{
    // hook « set » : transforme la valeur à l'écriture (≈ un setter)
    public string $email {
        set(string $value) { $this->email = strtolower(trim($value)); }
    }

    // propriété calculée (≈ un getter, ou un computed de Vue)
    public string $domain {
        get => substr($this->email, strpos($this->email, '@') + 1);
    }

    // lisible partout, modifiable seulement depuis la classe
    public private(set) int $logins = 0;

    public function __construct(string $email) { $this->email = $email; }
    public function login(): void { $this->logins++; }
}

$u = new User('  Alice@Example.COM ');
$u->email;     // 'alice@example.com'
$u->domain;    // 'example.com'
$u->logins = 5;   // Error : Cannot modify private(set) property
```

Vous les croiserez dans du code récent ; les squelettes du projet n'en ont pas besoin.

---

## 2.9 Enums

L'équivalent d'une union de littéraux TS (`'todo' | 'in_progress' | 'done'`), mais avec un vrai type :

```php
enum TaskStatus: string          // « backed enum » : chaque cas porte une valeur string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string        // un enum peut avoir des méthodes
    {
        return match ($this) {
            self::Todo => 'À faire',
            self::InProgress => 'En cours',
            self::Done => 'Terminé',
        };
    }
}

$s = TaskStatus::Done;
$s->value;                        // 'done' (ce qu'on stocke en base et en JSON)
$s->name;                         // 'Done'
TaskStatus::from('todo');         // TaskStatus::Todo — exception ValueError si inconnu
TaskStatus::tryFrom('nope');      // null
TaskStatus::cases();              // les 3 cas
$s === TaskStatus::Done;          // comparaison avec ===
```

`from()` convertit une donnée **externe** (JSON, base) en enum ; `tryFrom()` permet de valider sans exception.
`json_encode()` sérialise un backed enum en sa valeur : `"done"`.

---

## 2.10 Attributs

Les attributs `#[...]` sont des **métadonnées** attachées à une classe, une méthode, une propriété ou un paramètre.
Ils ressemblent aux décorateurs TS, avec une différence : ils ne **modifient pas** le code, ils sont **lus** par un
framework via la réflexion.

```php
#[Route('/api/tasks', methods: ['GET'])]
public function list(): JsonResponse { /* ... */ }

#[Assert\NotBlank]
#[Assert\Length(min: 3, max: 255)]
public string $title;
```

Vous n'en écrirez pas dans l'API PHP pur, mais Symfony et Doctrine les utilisent partout (routes, validation, mapping de la base).

---

## 2.11 Namespaces et `use`

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Task;                          // import d'une classe
use App\Repository\TaskRepositoryInterface;
use App\Http\JsonResponse as Json;            // alias (≈ import { X as Y })

final class TaskController
{
    public function show(): Json
    {
        $now = new \DateTimeImmutable();      // \ initial : classe globale de PHP
        $len = \strlen('abc');                // \ facultatif pour les fonctions, obligatoire pour les classes
        // ...
    }
}
```

- Dans un fichier qui déclare un `namespace`, `DateTimeImmutable` sans `\` est cherché dans `App\Controller\DateTimeImmutable`
  et provoque une erreur « Class not found ». Les classes natives (`\PDO`, `\DateTimeImmutable`, `\JsonException`,
  `\RuntimeException`) s'écrivent donc avec un `\` initial, ou s'importent avec `use DateTimeImmutable;`.
- Le séparateur est `\`, pas `/` ni `.`.

---

## 2.12 Exceptions

Pas de Promise rejetée ni de `.catch()` : les erreurs sont des **exceptions**, levées et attrapées de façon synchrone.

```php
try {
    $task = $repository->find($id) ?? throw new HttpException(404, "Task $id not found");
    $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    throw new HttpException(400, 'Invalid JSON: ' . $e->getMessage());
} catch (HttpException $e) {
    $response = $e->toResponse();
} catch (\Throwable $e) {              // attrape tout : à placer en DERNIER
    error_log((string) $e);
} finally {
    // toujours exécuté
}
```

| JS | PHP |
|---|---|
| `throw new Error('x')` | `throw new \RuntimeException('x');` |
| `catch (e)` puis `if (e instanceof X)` | plusieurs blocs `catch (X $e)`, du plus précis au plus général |
| `class MyError extends Error` | `class MyException extends \RuntimeException` |
| `e.message`, `e.stack` | `$e->getMessage()`, `$e->getTraceAsString()` |
| `x ?? (() => { throw ... })()` | `$x ?? throw new ...` (`throw` est une expression depuis PHP 8) |

Hiérarchie : `\Throwable` → `\Exception` (erreurs « métier » : `\RuntimeException`, `\InvalidArgumentException`, `\JsonException`...)
et `\Error` (erreurs du moteur : `\TypeError`, `\ValueError`, appel d'une méthode inexistante...).
`catch (\Exception $e)` n'attrape **pas** un `\TypeError` : pour tout attraper, c'est `\Throwable`.

---

## 2.13 Dates

```php
$now = new \DateTimeImmutable();                            // ≈ new Date()
$date = new \DateTimeImmutable('2026-10-15 09:30:00+02');   // parse de nombreux formats, dont celui de PostgreSQL
$date->format(DATE_ATOM);                                   // '2026-10-15T09:30:00+02:00' (≈ toISOString, mais avec le fuseau)
$date->format('d/m/Y H:i');                                 // format personnalisé
$later = $date->modify('+1 day');                           // renvoie un NOUVEL objet
$date < $later;                                             // comparaison directe des objets
```

- Utilisez **`DateTimeImmutable`** : chaque modification renvoie une nouvelle date. `DateTime` (mutable) modifie l'objet
  sur place, comme `Date.setDate()` en JS, source de bugs quand la date est partagée.
- Comme en JS, `modify('+1 month')` sur le 31 janvier tombe début mars (pas de 31 février).
- Le fuseau par défaut est réglé dans la configuration PHP du projet (`Europe/Paris`).

---

## 2.14 Pas de génériques : PHPDoc et PHPStan

PHP n'a pas de génériques : `array` ne dit pas ce qu'il contient. On complète le type par un **docblock**,
exactement comme du JSDoc dans un fichier `.js` :

```php
/**
 * @param list<Task>               $tasks   ≈ Task[]
 * @param array<string, string>    $errors  ≈ Record<string, string>
 * @return array{items: list<Task>, total: int}   ≈ { items: Task[]; total: number }
 */
function paginate(array $tasks, array $errors): array { /* ... */ }
```

PHP **ignore** ces annotations à l'exécution. Elles sont lues par l'éditeur (autocomplétion) et par **PHPStan**,
l'analyseur statique (≈ `tsc --noEmit`), qui signale les incohérences avant l'exécution.

---

## 2.15 Les 15 pièges pour un dev JS

1. **`+` ne concatène pas.** C'est `.`. `'a' + 1` lance une `TypeError`.
2. **Les tableaux sont copiés** à l'affectation et au passage en argument. Les objets, eux, sont partagés.
3. **`'0'` et `[]` sont falsy.** Testez explicitement (`!== ''`, `=== []`, `count($a) === 0`).
4. **`==` n'a pas les mêmes règles qu'en JS.** Utilisez `===`, et `in_array($x, $arr, true)`.
5. **Pas d'`undefined`.** Une clé absente provoque un avertissement : utilisez `??`, `isset()` ou `array_key_exists()`.
6. **`isset()` répond `false` pour une clé qui vaut `null`.** Pour « la clé est présente », c'est `array_key_exists()`.
7. **Les closures ne voient rien de l'extérieur** sans `use`, et capturent **par valeur**. `fn` capture automatiquement, mais par valeur aussi.
8. **Pas de `this` implicite** : `$this->title`, jamais `title`, même dans la classe.
9. **`strlen()` compte les octets.** Pour du texte, `mb_strlen()`, `mb_substr()`, `mb_strtolower()`.
10. **Interpolation seulement entre `"`.** `'Task $id'` reste littéral, et seules les variables s'interpolent, pas les expressions.
11. **L'ordre des arguments varie** : `array_map($fn, $arr)` mais `array_filter($arr, $fn)`. Et aucun chaînage.
12. **`array_filter()` garde les clés** : le résultat devient un objet JSON. `array_values()` pour revenir à une liste.
13. **Le `\` devant les classes natives** dans un fichier avec namespace : `\PDO`, `\DateTimeImmutable`.
14. **Pas d'`await`** : tout est synchrone. Et `echo` écrit dans la réponse HTTP, pas dans une console.
15. **Le typage est nominal et vérifié à l'exécution** : un objet « qui a les bonnes méthodes » ne suffit pas,
    il faut `implements`. Et sans `declare(strict_types=1)`, PHP convertit silencieusement `"5"` en `5`.
