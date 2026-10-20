# 3. PHP et HTTP

> Sans framework, c'est **vous** qui faites le travail d'ASP.NET Core : lire la requête, router,
> sérialiser en JSON, envoyer les en-têtes, accéder à la base, gérer les erreurs.
> Ce chapitre donne toutes les briques nécessaires aux [exercices](04-exercices-api-php-pur.md).

## 3.1 Le front controller

Historiquement, chaque URL correspondait à un fichier (`/contact.php`). Aujourd'hui, **toutes les requêtes** sont envoyées à un fichier unique, `public/index.php`, le *front controller* :

```
GET /api/tasks/3  ─┐
POST /api/tasks   ─┼──▶  public/index.php  ──▶ routeur ──▶ contrôleur ──▶ réponse
GET /api/health   ─┘
```

- Le serveur web est configuré pour ça : `php -S 0.0.0.0:8000 -t public` (API PHP pur) renvoie vers `public/index.php` toute URL qui n'est pas un fichier existant ; nginx fait de même avec `try_files` pour Symfony ([docker/nginx/symfony.conf](../../docker/nginx/symfony.conf)).
- Seul `public/` est exposé : `src/`, `vendor/`, la configuration ne sont **jamais** accessibles depuis le web.
- C'est l'équivalent de `Program.cs` : on y câble les objets et on y lance le traitement. Sauf qu'il est exécuté **à chaque requête**.

Squelette type :

```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';   // 1. autoload Composer

$request = Request::fromGlobals();                   // 2. lire la requête

try {
    $router = new Router();                          // 3. câbler les objets et les routes
    $router->get('/api/tasks', $controller->list(...));
    $response = $router->dispatch($request);         // 4. exécuter
} catch (HttpException $e) {
    $response = $e->toResponse();                    // 5. erreurs "prévues" (404, 422...)
} catch (\Throwable $e) {
    error_log((string) $e);
    $response = new JsonResponse(['title' => 'Internal Server Error', 'status' => 500], 500);
}

$response->send();                                   // 6. envoyer
```

`__DIR__` est le dossier du fichier courant (≈ `AppContext.BaseDirectory` mais par fichier), `dirname()` remonte d'un niveau.

## 3.2 Lire la requête : les superglobales

PHP remplit automatiquement des tableaux accessibles partout, les **superglobales** :

| Superglobale | Contenu | ≈ ASP.NET Core |
|---|---|---|
| `$_SERVER` | Infos serveur + requête : `REQUEST_METHOD`, `REQUEST_URI`, `HTTP_*` (en-têtes), `CONTENT_TYPE`... | `HttpContext.Request` |
| `$_GET` | Paramètres de la query string (`?status=todo`) | `Request.Query` |
| `$_POST` | Corps de la requête **formulaire** uniquement | `Request.Form` |
| `$_FILES` | Fichiers uploadés | `Request.Form.Files` |
| `$_COOKIE` | Cookies | `Request.Cookies` |
| `$_SESSION` | Session (après `session_start()`) | `HttpContext.Session` |
| `$_ENV` / `getenv()` | Variables d'environnement | `Environment.GetEnvironmentVariable` |

```php
$method = $_SERVER['REQUEST_METHOD'];                        // 'GET', 'POST', 'PATCH'...
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);    // '/api/tasks' (sans ?query)
$status = $_GET['status'] ?? null;                           // ?status=todo
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;              // en-tête Authorization
```

Les en-têtes HTTP sont dans `$_SERVER`, préfixés par `HTTP_`, en majuscules, avec `_` à la place de `-` : `X-Request-Id` → `$_SERVER['HTTP_X_REQUEST_ID']`.

### Pourquoi le JSON n'est pas dans `$_POST`

`$_POST` n'est rempli que pour les formulaires HTML (`application/x-www-form-urlencoded` ou `multipart/form-data`). Pour une API qui reçoit du JSON, et pour les méthodes `PATCH`/`PUT`/`DELETE`, il faut lire le **corps brut** dans le flux `php://input` :

```php
$body = file_get_contents('php://input');   // '{"title":"Apprendre PHP"}'
$data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
```

Bonne pratique : encapsuler les superglobales dans un objet `Request` immuable créé une fois dans le front controller (`Request::fromGlobals()`), puis ne plus jamais y toucher ailleurs. Le code devient testable (on peut créer une `Request` à la main) — c'est exactement ce que fait Symfony avec `Request::createFromGlobals()`.

## 3.3 Écrire la réponse

En PHP, tout ce qui est **affiché** (`echo`, `print`, du HTML hors des balises `<?php`) part dans le corps de la réponse HTTP. Les en-têtes et le code de statut se positionnent avec des fonctions :

```php
http_response_code(201);                                    // code de statut (200 par défaut)
header('Content-Type: application/json; charset=utf-8');    // un en-tête
header('Location: /api/tasks/42');
echo json_encode($task);                                    // le corps
```

| ASP.NET Core | PHP |
|---|---|
| `return Ok(x)` | `echo json_encode($x);` |
| `return StatusCode(201, x)` | `http_response_code(201); echo json_encode($x);` |
| `Response.Headers["X"] = "y"` | `header('X: y');` |
| `return NoContent()` | `http_response_code(204);` (et rien d'affiché) |

### « Headers already sent »

HTTP envoie les en-têtes **avant** le corps. Dès que PHP affiche le premier octet du corps, les en-têtes partent et ne peuvent plus être modifiés :

```php
echo 'debug';
header('Content-Type: application/json');
// Warning: Cannot modify header information - headers already sent by (output started at ...)
```

Causes classiques : un `echo`/`var_dump` de débogage, un espace ou une ligne vide **avant** `<?php`, un `?>` final suivi d'un retour à la ligne, un BOM UTF-8. D'où la règle : on construit un objet réponse, et on l'envoie **une seule fois**, à la fin (`$response->send()`).

## 3.4 JSON

```php
// PHP → JSON
json_encode(['id' => 1, 'title' => 'Été']);
// {"id":1,"title":"Été"}

json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
// {"id":1,"title":"Été"}

// JSON → PHP
json_decode('{"a":1}');                 // objet stdClass  ($obj->a)
json_decode('{"a":1}', true);           // tableau associatif ['a' => 1]  ← ce qu'on veut
json_decode('{oups', true);             // null... silencieusement !
json_decode('{oups', true, flags: JSON_THROW_ON_ERROR);   // \JsonException ← ce qu'on veut
```

| Flag | Effet |
|---|---|
| `JSON_THROW_ON_ERROR` | Lève `\JsonException` au lieu de renvoyer `null`/`false` — **toujours** |
| `JSON_UNESCAPED_UNICODE` | Garde « é » au lieu de `é` |
| `JSON_UNESCAPED_SLASHES` | Garde `/` au lieu de `\/` |
| `JSON_PRETTY_PRINT` | Indentation (debug) |

Correspondances de types :

| PHP | JSON |
|---|---|
| liste (`array_is_list`) | `[...]` |
| tableau associatif, `stdClass`, objet | `{...}` |
| `[]` vide | `[]` (⚠️ jamais `{}`) |
| enum backed | sa valeur (`"done"`) |
| objet quelconque | ses propriétés **publiques** |
| objet `\JsonSerializable` | ce que renvoie `jsonSerialize()` |

### Contrôler la forme avec `JsonSerializable`

C'est l'équivalent d'un DTO de sortie ou d'un `JsonConverter` : l'objet décide de sa représentation (noms en camelCase, format de date...).

```php
final class Task implements \JsonSerializable
{
    // ...
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DATE_ATOM),   // "2026-10-15T09:30:00+02:00"
            'status' => $this->status->value,
            // ...
        ];
    }
}

json_encode([$task1, $task2]);   // appelle jsonSerialize() sur chaque élément
```

## 3.5 PDO : l'accès aux bases de données

PDO (PHP Data Objects) est l'API d'accès aux bases intégrée à PHP, l'équivalent d'**ADO.NET** : connexion, requêtes, lecture des lignes. Pas d'ORM (Doctrine viendra avec Symfony).

| ADO.NET | PDO |
|---|---|
| `new NpgsqlConnection(connString)` | `new \PDO($dsn, $user, $password)` |
| `cmd.CommandText = "... @id"` | `$pdo->prepare('... :id')` |
| `cmd.Parameters.AddWithValue("id", 3)` | `$stmt->execute(['id' => 3])` |
| `reader.Read()` | `$stmt->fetch()` |
| Lire toutes les lignes | `$stmt->fetchAll()` |
| `ExecuteNonQuery()` (lignes affectées) | `$stmt->rowCount()` |
| `BeginTransaction()` / `Commit()` | `$pdo->beginTransaction()` / `$pdo->commit()` / `$pdo->rollBack()` |

### Connexion

```php
$pdo = new \PDO(
    'pgsql:host=db;port=5432;dbname=taskboard_php',   // DSN (≈ chaîne de connexion)
    'app',
    'app',
);
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);       // erreurs SQL → \PDOException (défaut depuis PHP 8)
$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);  // lignes = ['id' => 1, 'title' => ...]
$pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);                // vraies requêtes préparées côté serveur
```

Dans le projet, les informations de connexion sont fournies par des variables d'environnement (voir `api-php` dans [compose.yaml](../../compose.yaml)) : `getenv('DB_DSN')`, `getenv('DB_USER')`, `getenv('DB_PASSWORD')`.

Rappel du modèle d'exécution : la connexion est ouverte **à chaque requête** et fermée automatiquement à la fin. Pas de pool de connexions applicatif.

### Lire

```php
// Sans paramètre : query() suffit
$rows = $pdo->query('SELECT * FROM task ORDER BY id')->fetchAll();
// [['id' => 1, 'title' => '...', 'created_at' => '2026-10-15 09:30:00+02', ...], ...]

// Avec paramètre : TOUJOURS prepare + execute
$stmt = $pdo->prepare('SELECT * FROM task WHERE id = :id');
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();          // un tableau, ou false si aucune ligne (pas null !)
```

Les colonnes arrivent avec leur nom SQL (`created_at`, snake_case) : c'est à vous de les mapper vers vos objets (`Task::fromRow($row)`) puis vers le JSON (`createdAt`).

### Injection SQL

```php
// ❌ JAMAIS : concaténer une valeur venant de l'utilisateur dans le SQL
$pdo->query("SELECT * FROM task WHERE status = '$status'");
// ?status=' OR '1'='1  → toutes les lignes ; pire avec un DROP TABLE...

// ✅ Requête préparée : la valeur est envoyée séparément du SQL, jamais interprétée
$stmt = $pdo->prepare('SELECT * FROM task WHERE status = :status');
$stmt->execute(['status' => $status]);
```

Les paramètres ne peuvent remplacer que des **valeurs**, pas des noms de colonnes ni des mots-clés (`ORDER BY :col` ne marche pas) : pour ceux-là, utilisez une liste blanche.

### Écrire

PostgreSQL permet `RETURNING`, qui renvoie la ligne écrite : pratique pour récupérer l'`id` et la date générés par la base en une seule requête.

```php
$stmt = $pdo->prepare(
    'INSERT INTO task (title, description, status) VALUES (:title, :description, :status) RETURNING *'
);
$stmt->execute(['title' => 'Apprendre PDO', 'description' => null, 'status' => 'todo']);
$row = $stmt->fetch();   // la ligne complète, avec id et created_at

$stmt = $pdo->prepare('DELETE FROM task WHERE id = :id');
$stmt->execute(['id' => $id]);
$deleted = $stmt->rowCount() > 0;   // false → la tâche n'existait pas → 404
```

(Sans `RETURNING`, par exemple en MySQL : `$pdo->lastInsertId()`.)

### Transactions

```php
$pdo->beginTransaction();
try {
    // plusieurs requêtes...
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

Doc : [PDO](https://www.php.net/manual/fr/book.pdo.php) · [Requêtes préparées](https://www.php.net/manual/fr/pdo.prepared-statements.php)

## 3.6 Routage manuel

Un routeur associe une méthode HTTP et un motif d'URL à un *handler*. Avec des paramètres (`/api/tasks/{id}`), on passe par une **expression régulière** avec groupes nommés :

```php
$pattern = '/api/tasks/{id}';
$regex = '#^' . preg_replace('#\{(\w+)\}#', '(?<$1>\d+)', $pattern) . '$#';
// '#^/api/tasks/(?<id>\d+)$#'

if (preg_match($regex, '/api/tasks/42', $matches)) {
    // $matches = [0 => '/api/tasks/42', 'id' => '42', 1 => '42']
    $id = (int) $matches['id'];
}
```

- `preg_match` remplit `$matches` (passé par référence) : clés numériques **et** nommées. On ne garde que les nommées (`array_filter(..., ARRAY_FILTER_USE_KEY)` avec `is_string(...)`).
- Les délimiteurs `#...#` remplacent les `/` habituels pour ne pas avoir à échapper les `/` de l'URL.
- Les valeurs extraites sont des **chaînes** : convertir avec `(int)`.
- Si le chemin correspond mais pas la méthode → `405 Method Not Allowed`. Si rien ne correspond → `404`.

Un handler est un `callable` ; on enregistre des méthodes de contrôleur avec la syntaxe first-class callable :

```php
$router->get('/api/tasks/{id}', $taskController->show(...));
// au dispatch : ($handler)($request, ...$params)  → show(Request $request, string $id)
```

Le spread d'un tableau à clés `string` (`...['id' => '42']`) passe les valeurs en **arguments nommés** : le paramètre du contrôleur doit donc s'appeler `$id`.

Doc : [PCRE](https://www.php.net/manual/fr/book.pcre.php)

## 3.7 Gestion d'erreurs centralisée

Comme le middleware d'exception d'ASP.NET Core, on centralise dans le front controller :

1. Le code métier **lève** des exceptions qui portent un code HTTP : `throw new HttpException(404, "Task $id not found")`. Une sous-classe `ValidationException` porte la liste des violations (422).
2. Le front controller les **capture** et les transforme en réponse JSON au format du [contrat](../api-contract.md#format-des-erreurs).
3. Tout le reste (`\Throwable`) devient une 500, et est **journalisé**.

```php
class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $detail = '')
    {
        parent::__construct($detail);
    }
}

// Dans un contrôleur, avec throw comme expression :
$task = $this->repository->find($id) ?? throw new HttpException(404, "Task $id not found");
```

### Journaliser

```php
error_log('Création de la tâche ' . $task->id);
error_log((string) $exception);          // message + stack trace
error_log(print_r($data, true));         // dump d'un tableau
```

Avec le serveur intégré, `error_log()` écrit sur la sortie d'erreur du conteneur : `make logs s=api-php`. Les erreurs et warnings PHP non capturés y apparaissent aussi (et dans la réponse, car `display_errors` est activé en dev — ce qui peut casser votre JSON, autre bonne raison de les corriger).

---

➡️ Suite : [4. Exercices : l'API en PHP pur](04-exercices-api-php-pur.md)
