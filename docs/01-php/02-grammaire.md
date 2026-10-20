# 2. La grammaire PHP pour développeurs C#

> Tous les exemples supposent `declare(strict_types=1);` et PHP 8.4.
> Testez-les : `make php-sh` puis `php -a`, ou `php -r '...'`.

## Sommaire

1. [Tableau récapitulatif C# → PHP](#21-tableau-récapitulatif)
2. [Bases : fichiers, variables, commentaires](#22-bases)
3. [Types](#23-types)
4. [Chaînes de caractères](#24-chaînes-de-caractères)
5. [Le tableau PHP (array)](#25-le-tableau-php-array)
6. [Comparaisons et opérateurs](#26-comparaisons-et-opérateurs)
7. [Structures de contrôle](#27-structures-de-contrôle)
8. [Fonctions, closures, callables](#28-fonctions-closures-callables)
9. [Programmation orientée objet](#29-programmation-orientée-objet)
10. [Enums](#210-enums)
11. [Attributs](#211-attributs)
12. [Namespaces](#212-namespaces)
13. [Exceptions](#213-exceptions)
14. [Dates](#214-dates)
15. [Génériques, null-safety et PHPDoc](#215-génériques-null-safety-et-phpdoc)
16. [15 pièges pour un dev C#](#216-15-pièges-pour-un-dev-c)

---

## 2.1 Tableau récapitulatif

| Concept | C# | PHP |
|---|---|---|
| Variable | `var name = "x";` | `$name = 'x';` |
| Constante | `const int Max = 10;` | `const MAX = 10;` (classe) / `define('MAX', 10)` |
| Concaténation | `"a" + b` | `'a' . $b` |
| Interpolation | `$"Hello {name}"` | `"Hello $name"` / `"Hello {$user->name}"` |
| Accès membre d'instance | `obj.Prop` / `obj.Method()` | `$obj->prop` / `$obj->method()` |
| Accès statique | `Class.Method()` | `ClassName::method()` |
| `this` | `this.x` | `$this->x` (obligatoire, jamais implicite) |
| Classe parente | `base.Method()` | `parent::method()` |
| Instanciation | `new Task("x")` | `new Task('x')` |
| Liste | `new List<int> { 1, 2 }` | `[1, 2]` |
| Dictionnaire | `new Dictionary<string,int> { ["a"] = 1 }` | `['a' => 1]` |
| Ajouter à une liste | `list.Add(3)` | `$list[] = 3;` |
| Taille | `list.Count` | `count($list)` |
| foreach | `foreach (var x in list)` | `foreach ($list as $x)` |
| foreach clé/valeur | `foreach (var (k, v) in dict)` | `foreach ($dict as $k => $v)` |
| Égalité | `==` | `===` (strict) — **jamais `==`** |
| Null-coalescing | `a ?? b`, `a ??= b` | idem |
| Null-conditional | `a?.B` | `$a?->b` |
| switch expression | `x switch { 1 => "a", _ => "b" }` | `match ($x) { 1 => 'a', default => 'b' }` |
| Lambda | `x => x * 2` | `fn($x) => $x * 2` |
| Méthode | `public int Add(int a, int b)` | `public function add(int $a, int $b): int` |
| Nullable | `int?`, `string?` | `?int`, `?string` |
| Propriété auto | `public string Name { get; private set; }` | `public private(set) string $name;` |
| Readonly | `readonly` / `init` | `readonly` |
| Record | `record Point(int X, int Y)` | `final readonly class Point { public function __construct(public int $x, public int $y) {} }` |
| Interface | `interface IRepo` | `interface Repo` (pas de préfixe `I` par convention) |
| Héritage | `class B : A, IFoo` | `class B extends A implements Foo` |
| `sealed` | `sealed` | `final` |
| Namespace | `namespace App.Http;` | `namespace App\Http;` |
| using | `using App.Http;` | `use App\Http\Router;` (une classe par `use`) |
| Attribut | `[HttpGet("x")]` | `#[Route('x')]` |
| typeof / nameof | `typeof(Task)` | `Task::class` (chaîne `'App\Entity\Task'`) |
| is / as | `x is Task t` | `$x instanceof Task` |
| Cast | `(int)x` | `(int) $x` |
| Exception | `throw new InvalidOperationException()` | `throw new \LogicException()` |
| Afficher | `Console.WriteLine(x)` | `echo $x;` / `var_dump($x);` |
| Génériques | `List<Task>` | ❌ — PHPDoc `@var list<Task>` + PHPStan |
| async/await | ✅ | ❌ (modèle synchrone, voir [chapitre 1](01-principes.md#12-le-modèle-dexécution--share-nothing-)) |

---

## 2.2 Bases

```php
<?php

declare(strict_types=1);

// Commentaire ligne
# Commentaire ligne (rare)
/* Commentaire bloc */

/**
 * PHPDoc (≈ /// <summary> en C#) : lu par l'IDE et PHPStan.
 *
 * @param list<string> $names
 */
function greet(array $names): void
{
    $count = count($names);          // toute variable commence par $
    echo "Bonjour à $count personnes\n";
}
```

- Un fichier PHP commence par `<?php`. Dans un fichier 100 % PHP, **on ne ferme pas** la balise `?>` (évite qu'un retour à la ligne final soit envoyé au navigateur).
- Chaque instruction se termine par `;`.
- Les variables ne se déclarent pas : elles existent dès leur première affectation. Lire une variable inexistante produit un **warning** et donne `null`.
- Les noms de variables sont **sensibles à la casse** (`$name` ≠ `$Name`), les noms de fonctions et de classes **non** (mais respectez la casse quand même : l'autoload, lui, dépend du système de fichiers).
- Portée : une fonction **ne voit pas** les variables extérieures (pas de capture implicite comme en C#, voir [closures](#28-fonctions-closures-callables)).

---

## 2.3 Types

| PHP | C# | Remarque |
|---|---|---|
| `int` | `long` | 64 bits |
| `float` | `double` | Pas de `decimal` natif ! Pour de l'argent : entiers (centimes) ou `bcmath` |
| `string` | `string` | Mais ce sont des **octets**, pas des caractères UTF-16 (voir [chaînes](#24-chaînes-de-caractères)) |
| `bool` | `bool` | |
| `array` | `List<T>` + `Dictionary<K,V>` | Voir [2.5](#25-le-tableau-php-array) |
| `null` | `null` | |
| `object`, `NomDeClasse` | `object`, `NomDeClasse` | |
| `callable` | `Delegate` / `Func<>` | |
| `iterable` | `IEnumerable` | `array` ou `Traversable` |
| `mixed` | `object?` / `dynamic` | N'importe quoi |
| `void` | `void` | |
| `never` | `[DoesNotReturn]` | La fonction lève toujours une exception ou fait `exit` |
| `?int` | `int?` | Nullable (= `int\|null`) |
| `int\|string` | — | Type union |
| `Countable&Traversable` | — | Type intersection |
| `static`, `self` | — | Type de retour « la classe courante » |

```php
function find(int $id): ?Task { /* ... */ }            // peut renvoyer null
function parse(int|string $value): int { /* ... */ }  // union
function fail(string $message): never { throw new \RuntimeException($message); }
```

Où peut-on mettre un type ? **Paramètres, retours, propriétés, constantes de classe.** Jamais sur une variable locale.

Vérifier un type à l'exécution : `is_int()`, `is_string()`, `is_array()`, `is_null()`, `$x instanceof Task`, `get_debug_type($x)`.

Doc : [Types](https://www.php.net/manual/fr/language.types.declarations.php)

---

## 2.4 Chaînes de caractères

### Guillemets simples vs doubles

```php
$name = 'Alice';
echo 'Bonjour $name\n';     // Bonjour $name\n   (littéral, aucune interprétation)
echo "Bonjour $name\n";     // Bonjour Alice + retour à la ligne
echo "Titre : {$task->title}, premier : {$items[0]}";  // accolades pour les expressions
echo "Statut : {$task->status->value}";
```

Convention courante : `'simples'` par défaut, `"doubles"` quand on interpole.

⚠️ On ne peut interpoler que des **variables** (avec `->`, `[]`), pas des appels de fonction ni des calculs : `"{$a + 1}"` est une erreur.

### Concaténation : le point

```php
$full = $first . ' ' . $last;   // + est réservé aux nombres
$sql .= ' ORDER BY id';         // .= ajoute à la fin
```

`'1' + '2'` vaut `3` (int) et `'1' . '2'` vaut `'12'`.

### Heredoc / Nowdoc (≈ raw string literals `"""`)

```php
$sql = <<<SQL
    SELECT *
    FROM task
    WHERE status = '$status'
    SQL;                      // heredoc : interpolé (ici dangereux ! voir PDO)

$text = <<<'TXT'
    Pas d'interpolation : $rien
    TXT;                      // nowdoc : littéral
```

L'indentation de la balise de fin est retirée de toutes les lignes.

### Fonctions utiles

| C# | PHP |
|---|---|
| `s.Length` | `strlen($s)` (octets !) / `mb_strlen($s)` (caractères) |
| `s.ToUpper()` | `strtoupper($s)` / `mb_strtoupper($s)` |
| `s.Trim()` | `trim($s)` |
| `s.Contains("x")` | `str_contains($s, 'x')` |
| `s.StartsWith("x")` / `EndsWith` | `str_starts_with($s, 'x')` / `str_ends_with()` |
| `s.Replace("a", "b")` | `str_replace('a', 'b', $s)` ⚠️ ordre des arguments |
| `s.Split(',')` | `explode(',', $s)` |
| `string.Join(",", list)` | `implode(',', $list)` |
| `s.Substring(1, 3)` | `substr($s, 1, 3)` / `mb_substr()` |
| `s.IndexOf("x")` | `strpos($s, 'x')` → renvoie `false` si absent (pas `-1`) |
| `string.Format("{0}", x)` | `sprintf('%s', $x)` |
| `int.Parse(s)` | `(int) $s` (ne lève **pas** d'erreur) ou `filter_var($s, FILTER_VALIDATE_INT)` |

### UTF-8 : les chaînes sont des octets

```php
strlen('é');      // 2  (octets)
mb_strlen('é');   // 1  (caractères)
```

Dès qu'un texte peut contenir des accents, utilisez les fonctions `mb_*` (multibyte). La validation « 3 à 255 caractères » de l'API utilise `mb_strlen`.

Doc : [Chaînes](https://www.php.net/manual/fr/language.types.string.php) · [Fonctions mb_*](https://www.php.net/manual/fr/ref.mbstring.php)

---

## 2.5 Le tableau PHP (array)

Le type le plus utilisé de PHP, et le plus déroutant pour un dev C#. Un `array` PHP est **une table de hachage ordonnée** : il sert à la fois de liste, de dictionnaire, de pile, de file, de tuple...

### Liste et dictionnaire : la même structure

```php
$list = ['a', 'b', 'c'];            // clés implicites 0, 1, 2
$dict = ['id' => 1, 'title' => 'x']; // clés explicites
$mixed = [10 => 'a', 'b'];          // 'b' reçoit la clé 11

$list[] = 'd';                       // ajout en fin (≈ Add)
$dict['status'] = 'todo';            // ajout/modification par clé
unset($dict['title']);               // suppression (≈ Remove)

echo $dict['id'];                    // 1
echo $dict['nope'] ?? 'défaut';      // clé absente : warning sans ??, donc toujours ??
isset($dict['id']);                  // true si la clé existe ET n'est pas null
array_key_exists('id', $dict);       // true si la clé existe (même valeur null)
count($list);                        // 4
in_array('b', $list, true);          // ≈ Contains (toujours passer true = comparaison stricte)
```

Les clés sont des `int` ou des `string`. Une chaîne numérique est **convertie en int** : `['1' => 'a']` a pour clé `1` (int), mais `['01' => 'a']` garde `'01'`.

`array_is_list($a)` vérifie que les clés sont exactement `0, 1, 2...` : c'est ce qui décide si `json_encode` produit un **tableau** JSON `[...]` ou un **objet** `{...}`.

### Copie par valeur

C'est **le** piège principal. Un `array` n'est pas un objet référence comme `List<T>` : **l'affectation et le passage en paramètre copient**.

```php
$a = [1, 2];
$b = $a;      // copie !
$b[] = 3;
count($a);    // 2 — $a n'a pas bougé

function addItem(array $items): void { $items[] = 'x'; }  // modifie une COPIE
addItem($a);  // $a inchangé
```

Pour modifier le tableau d'origine : renvoyer le nouveau tableau (`$a = addItem($a);`), ou passer par référence (`array &$items`, à éviter), ou utiliser un objet. Pas d'inquiétude pour les performances : PHP fait du **copy-on-write**, la copie réelle n'a lieu qu'au moment d'une modification.

Les **objets**, eux, sont manipulés par référence (comme les classes C#) : `$t2 = $task; $t2->title = 'x';` modifie aussi `$task`. Pour copier un objet : `clone $task`.

### Les « LINQ » de PHP

| LINQ | PHP | ⚠️ |
|---|---|---|
| `Select(x => ...)` | `array_map(fn($x) => ..., $array)` | callback **en premier** |
| `Where(x => ...)` | `array_filter($array, fn($x) => ...)` | callback **en second** ; **conserve les clés** |
| `Aggregate(...)` | `array_reduce($array, fn($carry, $x) => ..., $initial)` | |
| `Select(x => x.Title)` | `array_column($rows, 'title')` | sur des tableaux associatifs (ou objets aux propriétés publiques) |
| `ToDictionary(x => x.Id)` | `array_column($rows, null, 'id')` | |
| `Any(...)` / `All(...)` | `array_any()` / `array_all()` | PHP 8.4 |
| `First(...)` | `array_find()` | PHP 8.4, renvoie `null` si absent |
| `Sum()` | `array_sum()` | |
| `OrderBy` | `usort($a, fn($x, $y) => $x->id <=> $y->id)` | **trie en place** (référence), renvoie `bool` |
| `Keys` / `Values` | `array_keys()` / `array_values()` | |
| `Concat` | `array_merge($a, $b)` ou `[...$a, ...$b]` | |
| `Distinct` | `array_unique()` | |
| `Take` / `Skip` | `array_slice($a, $offset, $length)` | |

```php
$numbers = [1, 2, 3, 4];
$even = array_filter($numbers, fn(int $n) => $n % 2 === 0);
// [1 => 2, 3 => 4] — les clés d'origine sont conservées !
json_encode($even);                 // {"1":2,"3":4}  ← un OBJET JSON, pas un tableau
json_encode(array_values($even));   // [2,4]          ← réindexé : c'est ce qu'on veut
```

L'**ordre des arguments est incohérent** (héritage historique) : `array_map(callback, array)` mais `array_filter(array, callback)`, `str_replace(search, replace, subject)` mais `strpos(haystack, needle)`. Utilisez les arguments nommés ou laissez l'IDE vous guider.

### Déstructuration et spread

```php
[$first, $second] = [10, 20];                   // ≈ var (a, b) = (10, 20);
['id' => $id, 'title' => $title] = $row;        // par clé
foreach ($rows as ['id' => $id, 'title' => $t]) { /* ... */ }

$all = [...$listA, ...$listB];                  // spread
$merged = [...$defaults, ...$options];          // clés string : la dernière gagne
```

Doc : [Tableaux](https://www.php.net/manual/fr/language.types.array.php) · [Fonctions de tableaux](https://www.php.net/manual/fr/ref.array.php)

---

## 2.6 Comparaisons et opérateurs

### `==` vs `===` : utilisez toujours `===`

`==` compare **après conversion de type** (« type juggling »). `===` compare valeur **et** type, comme le `==` de C#.

```php
"1" == "01";      // true   (deux chaînes numériques comparées comme des nombres)
"10" == "1e1";    // true
100 == "1e2";     // true
null == false;    // true
[] == false;      // true
"0" == false;     // true
"" == null;       // true
"abc" == 0;       // false depuis PHP 8 (true en PHP 7 !)

"1" === "01";     // false
1 === 1.0;        // false (int vs float)
```

Même règle pour `!==` au lieu de `!=`, et `in_array($x, $a, true)` / `array_search($x, $a, true)` avec le 3ᵉ argument à `true`.

Pour les objets : `==` compare les propriétés, `===` vérifie que c'est **la même instance** (≈ `ReferenceEquals`).

### Autres opérateurs

| Opérateur | Exemple | Remarque |
|---|---|---|
| `??` | `$data['status'] ?? 'todo'` | Ne lève pas d'avertissement si la clé n'existe pas |
| `??=` | `$options['limit'] ??= 50;` | |
| `?->` | `$task->project?->name` | Null-safe |
| `?:` | `$name ?: 'Anonyme'` | « Elvis » : si `$name` est *falsy* (`''`, `0`, `'0'`, `null`, `[]`) |
| `<=>` | `$a <=> $b` | « Spaceship » : -1, 0 ou 1 (≈ `CompareTo`) — idéal dans `usort` |
| `.` / `.=` | `'a' . 'b'` | Concaténation |
| `**` | `2 ** 10` | Puissance |
| `intdiv` / `/` | `intdiv(7, 2)` → 3 ; `7 / 2` → 3.5 | `/` renvoie un float si la division n'est pas exacte |
| `%` | `7 % 2` | |
| `and` / `or` | ⚠️ priorité plus faible que `=` | Utilisez `&&` et `\|\|` |

### Valeurs « falsy »

En condition, sont considérés comme faux : `false`, `0`, `0.0`, `''`, `'0'` (!), `[]`, `null`. Préférez des conditions explicites : `if ($items !== [])`, `if ($task === null)`.

Doc : [Comparaisons](https://www.php.net/manual/fr/language.operators.comparison.php)

---

## 2.7 Structures de contrôle

```php
if ($status === 'done') {
    // ...
} elseif ($status === 'in_progress') {   // elseif en un mot
    // ...
} else {
    // ...
}

// match ≈ switch expression C# : expression, comparaison STRICTE (===), pas de "break"
$label = match ($status) {
    'todo' => 'À faire',
    'in_progress' => 'En cours',
    'done', 'archived' => 'Terminée',  // plusieurs valeurs
    default => 'Inconnu',
};
// Sans default et sans correspondance → UnhandledMatchError

// match (true) : remplace une suite de if
$size = match (true) {
    $count === 0 => 'vide',
    $count < 10 => 'petit',
    default => 'grand',
};

for ($i = 0; $i < 10; $i++) { /* ... */ }
while ($row = $statement->fetch()) { /* ... */ }

foreach ($tasks as $task) { /* ... */ }
foreach ($tasks as $index => $task) { /* ... */ }  // clé => valeur
```

Le vieux `switch` existe mais compare avec `==` et nécessite des `break` : préférez `match`.

### Références `&` (et leur piège)

Une référence est un alias vers la même variable (≈ `ref` en C#).

```php
function increment(int &$n): void { $n++; }
$x = 1;
increment($x);   // $x vaut 2

foreach ($tasks as &$task) {   // modifie les éléments du tableau sur place
    $task['done'] = true;
}
unset($task);                  // ⚠️ OBLIGATOIRE
```

Pourquoi `unset` ? Après la boucle, `$task` reste une référence vers **le dernier élément**. Une deuxième boucle qui réutilise `$task` l'écrase :

```php
$arr = [1, 2, 3];
foreach ($arr as &$v) {}
foreach ($arr as $v) {}
echo implode(',', $arr);   // 1,2,2   😱
```

Règle : **évitez les références**. Utilisez `array_map` ou `foreach ($arr as $k => $v) { $arr[$k] = ...; }`.

Doc : [Structures de contrôle](https://www.php.net/manual/fr/language.control-structures.php) · [match](https://www.php.net/manual/fr/control-structures.match.php)

---

## 2.8 Fonctions, closures, callables

### Fonctions

```php
function createTask(string $title, ?string $description = null, string $status = 'todo'): array
{
    return ['title' => $title, 'description' => $description, 'status' => $status];
}

createTask('Apprendre PHP');
createTask('Apprendre PHP', status: 'done');   // arguments nommés (≈ C#), on saute $description

function sum(int ...$numbers): int              // variadique (≈ params int[])
{
    return array_sum($numbers);
}
sum(1, 2, 3);
sum(...[1, 2, 3]);                              // spread à l'appel
```

**Pas de surcharge** : deux fonctions ou méthodes ne peuvent pas avoir le même nom. On utilise des valeurs par défaut, des types union, ou des noms différents (`fromRow()`, `fromArray()`...).

### Closures : capture explicite

En C#, une lambda capture automatiquement les variables. En PHP, il y a deux syntaxes :

```php
$min = 3;

// 1. Fonction anonyme : capture EXPLICITE avec use, PAR VALEUR (au moment de la création)
$filter = function (string $s) use ($min): bool {
    return mb_strlen($s) >= $min;
};

// 2. Arrow function (PHP 7.4) : UNE expression, capture AUTOMATIQUE par valeur
$filter = fn(string $s): bool => mb_strlen($s) >= $min;
```

Capture **par valeur** = une copie au moment de la création, contrairement à C# qui capture la variable elle-même :

```php
$x = 1;
$f = fn() => $x;
$x = 2;
echo $f();   // 1  (en C#, ce serait 2)
```

Capture par référence possible avec `function () use (&$x)`, rarement utile.

### Callables

Tout ce qui peut être appelé : closure, nom de fonction, `[$objet, 'methode']`... Depuis PHP 8.1, la syntaxe **first-class callable** `f(...)` crée une closure proprement (≈ method group en C#) :

```php
$lengths = array_map(strlen(...), ['a', 'bb']);         // [1, 2]
$tasks = array_map(Task::fromRow(...), $rows);          // méthode statique
$router->get('/api/tasks', $controller->list(...));     // méthode d'instance
```

Type des paramètres : `callable` ou `\Closure`.

Doc : [Fonctions](https://www.php.net/manual/fr/language.functions.php) · [Arrow functions](https://www.php.net/manual/fr/functions.arrow.php) · [First-class callable](https://www.php.net/manual/fr/functions.first_class_callable_syntax.php)

---

## 2.9 Programmation orientée objet

### Une classe complète, commentée

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TaskStatus;

final class Task                                  // final ≈ sealed
{
    public const int MAX_TITLE_LENGTH = 255;      // constante de classe (typée depuis 8.3)

    private static int $instances = 0;            // propriété statique (vit le temps d'une requête !)

    public function __construct(                  // constructeur : toujours __construct
        private string $title,                    // constructor promotion : déclare ET affecte
        private TaskStatus $status = TaskStatus::Todo,
        public readonly ?int $id = null,          // readonly : affectable une seule fois
    ) {
        self::$instances++;                       // accès statique : self:: + $
    }

    public function getTitle(): string
    {
        return $this->title;                      // $this-> obligatoire
    }

    public function rename(string $title): void
    {
        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new \InvalidArgumentException('Title too long');
        }
        $this->title = $title;
    }

    public static function count(): int
    {
        return self::$instances;
    }
}

$task = new Task('Apprendre PHP');
$task->rename('Maîtriser PHP');        // instance : ->
echo Task::count();                    // statique : ::
echo Task::MAX_TITLE_LENGTH;
echo Task::class;                      // 'App\Entity\Task' (≈ typeof(Task).FullName)
```

| C# | PHP |
|---|---|
| `this.title` | `$this->title` |
| `Task.Count()` | `Task::count()` |
| `Task.MaxTitleLength` | `Task::MAX_TITLE_LENGTH` |
| `static int instances` → `instances` | `self::$instances` (avec `$` !) |
| `base.Method()` | `parent::method()` |
| `new Task { Title = "x" }` (initialiseur) | ❌ → arguments nommés : `new Task(title: 'x')` |

### Visibilité

`public`, `protected`, `private` — comme C#. Pas d'`internal` (pas d'assembly). Par défaut une méthode est `public`, mais écrivez-le toujours.

### Propriétés : PHP 8.4 rattrape C#

Historiquement, PHP n'avait pas de propriétés au sens C# : on écrivait des getters/setters (`getTitle()`, `setTitle()`), ce que vous verrez encore partout (Doctrine, Symfony). PHP 8.4 apporte deux nouveautés :

```php
final class User
{
    public function __construct(
        public private(set) string $email,    // visibilité asymétrique ≈ { get; private set; }
        public string $firstName,
        public string $lastName,
    ) {}

    // Property hook ≈ propriété calculée C# { get => ... }
    public string $fullName {
        get => $this->firstName . ' ' . $this->lastName;
    }
}

$u = new User('a@b.c', 'Ada', 'Lovelace');
echo $u->fullName;       // Ada Lovelace
echo $u->email;          // lecture publique OK
$u->email = 'x';         // Error: Cannot modify private(set) property User::$email from global scope
```

### `readonly`

```php
final readonly class Money                // toutes les propriétés sont readonly (≈ record immuable)
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {}

    public function add(Money $other): self   // on renvoie une NOUVELLE instance
    {
        return new self($this->amount + $other->amount, $this->currency);
    }
}
```

Modifier une propriété readonly après initialisation → `Error: Cannot modify readonly property`.

### `self`, `static`, `parent`

- `self::` → la classe **où le code est écrit**.
- `static::` → la classe **réellement appelée** (late static binding, ≈ appel virtuel sur du statique).
- `parent::` → la classe parente.

```php
class Model { public static function create(): static { return new static(); } }
class Task extends Model {}
get_class(Task::create());   // 'Task' (avec new self() ce serait 'Model')
```

### Héritage, interfaces, classes abstraites

```php
interface TaskRepository                          // pas de préfixe I
{
    public function find(int $id): ?Task;
}

abstract class AbstractController
{
    abstract protected function name(): string;

    protected function json(mixed $data): JsonResponse { /* ... */ }
}

final class PdoTaskRepository implements TaskRepository   // implements (≠ extends)
{
    public function find(int $id): ?Task { /* ... */ }
}

final class TaskController extends AbstractController implements \Countable
{
    protected function name(): string { return 'tasks'; }
    public function count(): int { return 0; }
}
```

- Héritage simple (`extends`), interfaces multiples (`implements A, B`).
- Toutes les méthodes sont « virtuelles » : pas de mot-clé `virtual`/`override` (PHP 8.3 ajoute l'attribut `#[\Override]` pour vérifier qu'on redéfinit bien une méthode). `final` empêche la redéfinition.
- Les signatures redéfinies doivent être compatibles (paramètres contravariants, retour covariant).

### Traits : du copier-coller contrôlé

Un trait est un bloc de méthodes/propriétés **injecté** dans une classe (≈ default interface methods, ou mixin). Pas d'équivalent exact en C#.

```php
trait Timestampable
{
    private ?\DateTimeImmutable $updatedAt = null;

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

final class Task
{
    use Timestampable;   // les membres du trait sont copiés ici
}
```

À utiliser avec parcimonie : privilégiez la composition.

### Méthodes magiques

Méthodes au nom en `__xxx` appelées automatiquement par PHP :

| Méthode | Appelée quand | ≈ C# |
|---|---|---|
| `__construct()` | `new` | Constructeur |
| `__toString()` | Conversion en chaîne (`echo $obj`) | `ToString()` |
| `__invoke()` | `$obj()` (l'objet est appelé comme une fonction) | Delegate |
| `__get()` / `__set()` | Accès à une propriété inexistante | `DynamicObject` — à éviter |
| `__call()` | Appel d'une méthode inexistante | — à éviter |
| `__clone()` | `clone $obj` | `ICloneable` |

### Interfaces utiles de la bibliothèque standard

`\JsonSerializable` (contrôle de `json_encode`), `\Countable` (`count($obj)`), `\IteratorAggregate` (`foreach` sur un objet ≈ `IEnumerable`), `\Stringable`.

Doc : [Classes et objets](https://www.php.net/manual/fr/language.oop5.php) · [Property hooks](https://www.php.net/manual/fr/language.oop5.property-hooks.php)

---

## 2.10 Enums

PHP 8.1. Contrairement à C# où un enum est un entier déguisé, un enum PHP est un **vrai type** dont chaque cas est un objet unique.

```php
// Enum "pure" : pas de valeur scalaire
enum Suit
{
    case Hearts;
    case Spades;
}

// Enum "backed" : chaque cas porte une valeur int ou string (stockable en base / JSON)
enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    // Les enums peuvent avoir des méthodes (et des constantes, implémenter des interfaces)
    public function label(): string
    {
        return match ($this) {
            self::Todo => 'À faire',
            self::InProgress => 'En cours',
            self::Done => 'Terminée',
        };
    }
}

$status = TaskStatus::Done;
$status->value;                 // 'done'
$status->name;                  // 'Done'
$status->label();               // 'Terminée'
$status === TaskStatus::Done;   // true (comparaison d'identité)

TaskStatus::from('done');       // TaskStatus::Done
TaskStatus::from('nope');       // ValueError
TaskStatus::tryFrom('nope');    // null (≈ Enum.TryParse)
TaskStatus::cases();            // [Todo, InProgress, Done]
```

`json_encode(TaskStatus::Done)` produit `"done"` pour un enum backed.

Doc : [Enums](https://www.php.net/manual/fr/language.enumerations.php)

---

## 2.11 Attributs

Les attributs PHP 8 (`#[...]`) sont l'équivalent direct des attributs C# `[...]` : des métadonnées lues par réflexion. PHP lui-même n'en fait presque rien, ce sont les frameworks qui les exploitent. Vous les verrez partout en Symfony :

```php
#[Route('/api/tasks', methods: ['GET'])]           // ≈ [HttpGet("api/tasks")]
public function list(): JsonResponse { /* ... */ }

#[ORM\Column(length: 255)]                          // ≈ [MaxLength(255)]
#[Assert\NotBlank]                                  // ≈ [Required]
private string $title;
```

Créer le sien : une classe marquée `#[\Attribute]`. Le lire : `(new \ReflectionClass(Task::class))->getAttributes()`.

Doc : [Attributs](https://www.php.net/manual/fr/language.attributes.php)

---

## 2.12 Namespaces

```php
<?php

declare(strict_types=1);

namespace App\Controller;               // séparateur : antislash

use App\Entity\Task;                    // un use par classe (pas d'import de namespace entier)
use App\Http\{Request, JsonResponse};   // groupement
use App\Repository\TaskRepository as Repo;  // alias
use function array_map;                 // import de fonction (rare)

final class TaskController
{
    public function show(Request $request): JsonResponse
    {
        $now = new \DateTimeImmutable();   // \ initial : classe du namespace GLOBAL
        throw new \RuntimeException();     // idem pour les exceptions natives
    }
}
```

⚠️ Dans un fichier avec `namespace App\Controller;`, écrire `new DateTimeImmutable()` cherche `App\Controller\DateTimeImmutable` → erreur « Class not found ». Il faut `\DateTimeImmutable` ou `use DateTimeImmutable;`.

Pour les **fonctions** natives (`strlen`, `count`...), PHP retombe automatiquement sur le namespace global : pas besoin du `\`.

Comme vu au [chapitre 1](01-principes.md#15-composer--nuget--lautoload), le namespace doit correspondre au chemin du fichier (PSR-4).

---

## 2.13 Exceptions

```php
try {
    $task = $repository->find($id) ?? throw new NotFoundException("Task $id not found");
    // throw est une EXPRESSION depuis PHP 8 (utilisable après ??, dans un match, une arrow fn)
} catch (NotFoundException $e) {
    return new JsonResponse(['detail' => $e->getMessage()], 404);
} catch (\PDOException | \JsonException $e) {     // plusieurs types
    error_log($e->getMessage());
    throw $e;
} finally {
    // toujours exécuté
}
```

Hiérarchie :

```
Throwable (interface)
├── Exception          ← erreurs "métier" et applicatives, à capturer
│   ├── RuntimeException, LogicException, InvalidArgumentException...
│   ├── JsonException, PDOException...
│   └── vos exceptions (class NotFoundException extends \RuntimeException)
└── Error              ← erreurs du moteur (≈ bugs) : rarement capturées
    ├── TypeError        (mauvais type de paramètre en strict_types)
    ├── ValueError       (TaskStatus::from('nope'))
    ├── ArgumentCountError
    └── UnhandledMatchError
```

`catch (\Exception $e)` ne capture **pas** les `Error`. Pour tout attraper (gestionnaire global) : `catch (\Throwable $e)`.

Il existe aussi les **warnings/notices** (variable indéfinie, clé de tableau absente...) qui ne sont pas des exceptions : ils s'affichent et l'exécution continue. Traitez-les comme des bugs.

Doc : [Exceptions](https://www.php.net/manual/fr/language.exceptions.php) · [Exceptions SPL](https://www.php.net/manual/fr/spl.exceptions.php)

---

## 2.14 Dates

```php
$now = new \DateTimeImmutable();                        // ≈ DateTimeOffset.Now
$date = new \DateTimeImmutable('2026-10-15 09:30:00');
$tomorrow = $now->modify('+1 day');                     // renvoie une NOUVELLE instance
echo $now->format(DATE_ATOM);                           // 2026-10-15T09:30:00+02:00 (ISO 8601)
echo $now->format('d/m/Y H:i');                         // 15/10/2026 09:30
```

**Toujours `DateTimeImmutable`**, jamais `DateTime` : `DateTime` est **mutable**, `modify()` change l'objet lui-même, et comme les objets sont partagés par référence :

```php
$start = new \DateTime('2026-01-01');
$end = $start;
$end->modify('+1 day');
echo $start->format('Y-m-d');   // 2026-01-02 😱
```

Doc : [DateTimeImmutable](https://www.php.net/manual/fr/class.datetimeimmutable.php) · [Formats](https://www.php.net/manual/fr/datetime.format.php)

---

## 2.15 Génériques, null-safety et PHPDoc

PHP n'a **pas de génériques** et pas de *nullable reference types*. On compense avec la PHPDoc, comprise par l'IDE et vérifiée par **PHPStan** :

```php
/**
 * @param list<Task> $tasks              ≈ IReadOnlyList<Task> (tableau indexé 0..n)
 * @param array<int, Task> $byId         ≈ Dictionary<int, Task>
 * @param array{id: int, title: string} $row   forme exacte d'un tableau associatif (≈ tuple nommé)
 * @return ?Task
 */
function pick(array $tasks, array $byId, array $row): ?Task { /* ... */ }

/** @var list<Task> $tasks */
$tasks = $repository->findAll();
```

Côté null-safety : un paramètre `Task $task` refuse `null` (TypeError), `?Task` l'accepte. Le reste repose sur vous (`?->`, `??`) et sur PHPStan.

Doc PHPStan : <https://phpstan.org/writing-php-code/phpdoc-types>

---

## 2.16 15 pièges pour un dev C#

1. **Oublier le `$`** devant une variable, ou le mettre sur une propriété : `$this->title` et non `$this->$title` (qui lit la propriété dont le *nom* est contenu dans `$title`).
2. **`+` pour concaténer** : `'a' + 'b'` lève une `TypeError`. C'est `.`.
3. **Les tableaux sont copiés** à l'affectation et au passage en paramètre (les objets, eux, ne le sont pas).
4. **`==` au lieu de `===`** : `null == false`, `"1" == "01"`... Toujours `===`, et `in_array(..., true)`.
5. **`array_filter` conserve les clés** → `json_encode` produit un objet `{"1": ...}` au lieu d'un tableau. Ajouter `array_values()`.
6. **Oublier `declare(strict_types=1);`** : PHP convertit silencieusement `"21"` en `21`.
7. **`strlen` compte des octets** : `strlen('é') === 2`. Utiliser `mb_strlen`.
8. **Oublier le `\`** devant les classes natives dans un namespace : `new DateTimeImmutable()` → « Class App\...\DateTimeImmutable not found ».
9. **Les closures ne capturent rien** sans `use` (ou `fn`), et capturent par **valeur**.
10. **`foreach` par référence** sans `unset()` derrière.
11. **Croire qu'un `static` survit** entre deux requêtes : tout est détruit à la fin de chaque requête.
12. **`strpos` renvoie `false`**, pas `-1`, et `0` est une position valide : `if (strpos($s, 'x'))` est faux si `x` est en position 0. Utiliser `str_contains`.
13. **Pas de surcharge** de méthodes, pas d'initialiseur d'objet : arguments nommés et valeurs par défaut.
14. **`DateTime` est mutable** : toujours `DateTimeImmutable`.
15. **Afficher avant d'envoyer les en-têtes** : un `echo` ou un espace avant `<?php` empêche ensuite `header()` (« headers already sent ») — voir [chapitre 3](03-php-et-http.md).

Bonus : `usort()` et `sort()` trient **sur place** et renvoient un booléen ; `(int) 'abc'` vaut `0` sans erreur ; lire une clé absente d'un tableau donne un *warning* et `null`, pas une exception ; il n'y a pas de `decimal`.

---

➡️ Suite : [3. PHP et HTTP](03-php-et-http.md)
