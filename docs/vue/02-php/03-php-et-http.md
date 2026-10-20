# 3. PHP et HTTP — comparé à Express

> Objectif : savoir traiter une requête HTTP à la main en PHP, en vous appuyant sur ce que vous connaissez
> d'Express (ou du module `http` de Node). C'est exactement ce que vous ferez dans les exercices.

## Sommaire

- [3.1 Le front controller](#31-le-front-controller)
- [3.2 Lire la requête : les superglobales](#32-lire-la-requête--les-superglobales)
- [3.3 Écrire la réponse](#33-écrire-la-réponse)
- [3.4 JSON](#34-json)
- [3.5 PDO : parler à la base de données](#35-pdo--parler-à-la-base-de-données)
- [3.6 Routage manuel](#36-routage-manuel)
- [3.7 Gestion d'erreurs centralisée](#37-gestion-derreurs-centralisée)
- [À retenir](#à-retenir)

---

## 3.1 Le front controller

Avec Express, un fichier d'entrée crée l'application, enregistre les routes, puis écoute un port :

```js
// app.js
const app = express()
app.use(express.json())
app.get('/api/tasks', listTasks)
app.post('/api/tasks', createTask)
app.use(errorHandler)
app.listen(3000)
```

En PHP, le serveur web (nginx, ou `php -S` dans ce projet) est configuré pour envoyer **toutes** les requêtes
vers un seul fichier : `public/index.php`. C'est le **front controller**.

```
GET /api/tasks/3 ──▶ serveur web ──▶ public/index.php (exécuté de zéro)
                                       ├─ lit la requête (méthode, chemin, corps)
                                       ├─ choisit le code à exécuter (routage)
                                       ├─ produit la réponse (code, en-têtes, JSON)
                                       └─ fin du script : tout est libéré
```

La grande différence avec `app.js` : **rien n'est en attente**. Pas de `listen()`, pas de routes enregistrées une fois
pour toutes. À chaque requête, `index.php` recrée le routeur, les contrôleurs et la connexion à la base,
traite **cette** requête, puis s'arrête (voir [le modèle share-nothing](01-principes.md#12-le-grand-changement--share-nothing)).

Seul le dossier `public/` est exposé par le serveur web : le code de `src/` et les dépendances de `vendor/`
ne sont pas accessibles directement depuis le navigateur.

---

## 3.2 Lire la requête : les superglobales

Pas d'objet `req` fourni : PHP remplit des **tableaux globaux** avant d'exécuter le script.

| Express | PHP | Exemple de valeur |
|---|---|---|
| `req.method` | `$_SERVER['REQUEST_METHOD']` | `'POST'` |
| `req.originalUrl` | `$_SERVER['REQUEST_URI']` | `'/api/tasks?status=todo'` (avec la query string) |
| `req.path` | `parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)` | `'/api/tasks'` |
| `req.query` | `$_GET` | `['status' => 'todo']` |
| `req.get('User-Agent')` | `$_SERVER['HTTP_USER_AGENT']` | en-têtes préfixés `HTTP_`, en majuscules |
| `req.body` (formulaire) | `$_POST` | **uniquement** pour les formulaires HTML |
| `req.body` (JSON) | `file_get_contents('php://input')` puis `json_decode()` | voir ci-dessous |
| `req.cookies` | `$_COOKIE` | |
| `req.params.id` | n'existe pas : c'est **votre** routeur qui l'extrait | |

```php
$method = $_SERVER['REQUEST_METHOD'];                          // 'GET'
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);      // '/api/tasks'
$status = $_GET['status'] ?? null;                             // ?status=todo
```

Ces tableaux s'appellent « superglobales » parce qu'ils sont accessibles **partout**, y compris dans les fonctions.
Bonne pratique : les lire **à un seul endroit** et les encapsuler dans un objet `Request` immuable, que le reste du code
reçoit en paramètre. C'est le rôle de `Request::fromGlobals()` dans les exercices.

### Pourquoi le JSON n'est pas dans `$_POST`

En Express, `req.body` est vide tant que vous n'ajoutez pas `express.json()`. En PHP, c'est pareil, mais il n'existe
pas de middleware « body-parser » intégré :

- `$_POST` n'est rempli que pour les corps `application/x-www-form-urlencoded` et `multipart/form-data` (formulaires HTML) ;
- pour `application/json`, `$_POST` reste **vide**. Le corps brut se lit dans le **flux** `php://input` :

```php
$raw = file_get_contents('php://input');   // '{"title":"Apprendre PHP"}'  (string, ou false en cas d'erreur)
$data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);   // ['title' => 'Apprendre PHP']
```

C'est à vous de gérer le cas où le corps n'est pas du JSON valide (→ 400).

---

## 3.3 Écrire la réponse

| Express | PHP |
|---|---|
| `res.status(201)` | `http_response_code(201);` |
| `res.set('Location', '/api/tasks/3')` | `header('Location: /api/tasks/3');` |
| `res.type('json')` | `header('Content-Type: application/json; charset=utf-8');` |
| `res.json(data)` | `echo json_encode($data);` |
| `res.status(204).end()` | `http_response_code(204);` et aucun `echo` |
| `res.send()` met fin à la réponse | la réponse se termine avec le script (ou `return` / `exit`) |

```php
http_response_code(201);
header('Content-Type: application/json; charset=utf-8');
header('Location: /api/tasks/3');
echo json_encode($task);
```

Tout ce que le script **affiche** (`echo`, `print`, HTML hors des balises `<?php`) part dans le corps de la réponse.

### « Headers already sent »

Une réponse HTTP, c'est d'abord le code et les en-têtes, **puis** le corps. Dès que PHP envoie le premier octet du corps,
les en-têtes partent avec, et il est trop tard pour en ajouter :

```php
echo 'debug';                                // le corps commence : les en-têtes sont envoyés
header('Content-Type: application/json');    // Warning : Cannot modify header information - headers already sent
```

C'est l'équivalent de l'erreur Express `Cannot set headers after they are sent to the client`.

Causes classiques :

- un `echo`, `var_dump()` ou `print_r()` de débogage **avant** le `header()` ;
- une **ligne vide ou un espace avant `<?php`** dans n'importe quel fichier chargé (d'où la règle : ne pas fermer `?>`) ;
- un avertissement PHP affiché à l'écran avant les en-têtes.

Règle : **tous** les `header()` et `http_response_code()` d'abord, puis **un seul** `echo` à la fin.
C'est ce que garantit une classe `JsonResponse` avec une méthode `send()`.

---

## 3.4 JSON

| JS | PHP |
|---|---|
| `JSON.stringify(data)` | `json_encode($data)` |
| `JSON.parse(text)` | `json_decode($text, true)` |
| erreur : exception `SyntaxError` | par défaut : renvoie `null` **sans** exception ⚠️ |

```php
json_encode(['title' => 'Été / hiver']);
// {"title":"été \/ hiver"}  — par défaut, accents et "/" sont échappés

json_encode(['title' => 'Été / hiver'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
// {"title":"Été / hiver"}
```

Pour décoder :

```php
json_decode('{"a":1}');                 // un objet stdClass ($x->a)
json_decode('{"a":1}', true);           // un tableau associatif ($x['a']) — ce qu'on veut en général
json_decode('pas du json', true);       // null, sans erreur : on ne distingue pas d'un "null" JSON valide
json_decode('pas du json', true, flags: JSON_THROW_ON_ERROR);   // lève \JsonException : à préférer
```

Pièges de forme :

- `{}` et `[]` se décodent tous les deux en `[]` (tableau vide) avec `json_decode(..., true)` : impossible de les distinguer
  ensuite (`array_is_list()` non plus, et il rejetterait à tort `{"0":"a"}`). Pour savoir si le client a envoyé un objet,
  décodez d'abord **sans** `true` et testez `$data instanceof \stdClass`.
- Un tableau aux clés non consécutives (après `array_filter()`) s'encode en **objet** JSON.

### Contrôler la forme avec `JsonSerializable`

L'équivalent de la méthode `toJSON()` en JS : `json_encode()` l'appelle automatiquement.

```php
final class Task implements \JsonSerializable
{
    // ...
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status->value,                  // enum → string
            'createdAt' => $this->createdAt->format(DATE_ATOM), // camelCase et format ISO 8601 du contrat
        ];
    }
}

json_encode([$task1, $task2]);   // jsonSerialize() est appelé pour chaque tâche
```

C'est ici que l'on décide de la forme publique d'un objet : noms des champs en camelCase, format des dates,
champs à ne **pas** exposer.

---

## 3.5 PDO : parler à la base de données

**PDO** est l'API standard d'accès aux bases de données en PHP, l'équivalent d'un client comme `pg` (node-postgres),
mais commun à PostgreSQL, MySQL, SQLite, etc.

### Connexion

```js
// Node, avec pg
const pool = new Pool({ connectionString: process.env.DATABASE_URL })
```

```php
$pdo = new \PDO(
    dsn: getenv('DB_DSN'),          // 'pgsql:host=db;port=5432;dbname=taskboard_php'
    username: getenv('DB_USER'),
    password: getenv('DB_PASSWORD'),
);

$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);        // erreur SQL → exception
$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);   // lignes → tableaux associatifs
$pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);                 // vraies requêtes préparées
```

- Le **DSN** (*Data Source Name*) décrit le pilote et la base. Les variables d'environnement sont définies dans `compose.yaml`
  et se lisent avec `getenv()` (≈ `process.env`). `getenv()` renvoie `false` si la variable n'existe pas.
- La connexion n'est **pas** un pool : elle est ouverte pour la requête courante et fermée à la fin du script.
- Fuseau horaire : PostgreSQL renvoie les dates dans le fuseau de la **session SQL**. Alignez-le sur celui de PHP
  (`SET TIME ZONE 'Europe/Paris'`), sinon les dates reviennent en `+00:00`.

### Lire

```js
// Node
const { rows } = await pool.query('SELECT * FROM task WHERE id = $1', [id])
```

```php
// Sans paramètre
$rows = $pdo->query('SELECT * FROM task ORDER BY id')->fetchAll();   // list<array>

// Avec paramètre : TOUJOURS une requête préparée
$statement = $pdo->prepare('SELECT * FROM task WHERE id = :id');
$statement->execute(['id' => $id]);
$row = $statement->fetch();          // un tableau associatif... ou FALSE s'il n'y a pas de ligne
```

| `pg` | PDO |
|---|---|
| `$1`, `$2` | paramètres nommés `:id` (ou `?` positionnels) |
| `await pool.query(sql, params)` | `prepare($sql)` puis `execute($params)` (synchrone) |
| `rows` | `fetchAll()` |
| `rows[0]` ou `undefined` | `fetch()`, qui renvoie **`false`** s'il n'y a pas de ligne |
| `rowCount` | `$statement->rowCount()` |

Attention aux types : PDO renvoie souvent des **chaînes** (`'7'` au lieu de `7`). Avec `strict_types`, convertissez
explicitement (`(int) $row['id']`) avant de passer la valeur à un paramètre typé.

### Écrire

PostgreSQL sait renvoyer la ligne écrite avec `RETURNING`, comme vous le feriez avec `pg` :

```php
$statement = $pdo->prepare(
    'INSERT INTO task (title, description, status) VALUES (:title, :description, :status) RETURNING *'
);
$statement->execute([
    'title' => $title,
    'description' => $description,     // null est géré
    'status' => $status->value,        // PDO ne sait pas convertir un enum : passez sa valeur
]);
$row = $statement->fetch();            // la ligne insérée, avec id et created_at générés par la base

$statement = $pdo->prepare('DELETE FROM task WHERE id = :id');
$statement->execute(['id' => $id]);
$deleted = $statement->rowCount() > 0; // false si l'id n'existait pas
```

### Injection SQL

Ne **jamais** concaténer une valeur dans une requête :

```php
$pdo->query("SELECT * FROM task WHERE title = '$title'");   // ❌ fonctionne... jusqu'à ce que $title contienne une apostrophe
```

Avec `$title = "x' OR '1'='1"`, la requête renvoie toutes les lignes ; avec pire, elle peut supprimer des données.
C'est le même problème qu'avec une requête `pg` construite par template literal.

Avec une requête **préparée**, la requête et les valeurs voyagent **séparément** jusqu'à la base :
une valeur ne peut jamais devenir du SQL. Règle absolue : **toute** valeur venant de l'extérieur passe par un paramètre `:nom`.

### Transactions

```php
$pdo->beginTransaction();
try {
    // plusieurs requêtes qui doivent réussir ou échouer ensemble
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

Le script de vérification du Lot B (`make php-check-b`) utilise ce mécanisme : il ouvre une transaction, teste vos méthodes
sur la vraie base, puis fait `rollBack()`. Aucune donnée n'est modifiée.

---

## 3.6 Routage manuel

Express transforme `'/api/tasks/:id'` en expression régulière (via la bibliothèque `path-to-regexp`) et remplit `req.params`.
En PHP pur, vous faites la même chose à la main :

```php
// '/api/tasks/{id}'  →  '#^/api/tasks/(?<id>\d+)$#'
$regex = '#^' . preg_replace('#\{(\w+)\}#', '(?<$1>\d+)', '/api/tasks/{id}') . '$#';

if (preg_match($regex, '/api/tasks/42', $matches)) {
    // $matches = [0 => '/api/tasks/42', 'id' => '42', 1 => '42']
    $params = array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY);   // ['id' => '42']
}
```

- `(?<id>\d+)` est un **groupe nommé** : `preg_match()` remplit `$matches` avec les groupes numérotés **et** nommés.
- `^` et `$` imposent que **tout** le chemin corresponde : sans eux, `/api/tasks/42/extra` serait accepté.
- `\d+` limite le paramètre aux chiffres : `/api/tasks/abc` ne correspond pas → 404.

Un routeur minimal stocke une liste de routes `méthode + regex + handler`, puis, pour une requête :

1. parcourt les routes et teste la regex sur le chemin ;
2. si le chemin correspond **et** la méthode aussi : appelle le handler avec les paramètres ;
3. si le chemin correspond mais pas la méthode : **405 Method Not Allowed** ;
4. si aucun chemin ne correspond : **404 Not Found**.

Un handler est un *callable* (voir [la grammaire](02-grammaire.md#callables)) :

```php
$router->get('/api/tasks/{id}', $taskController->show(...));
// GET /api/tasks/42  →  $taskController->show($request, id: '42')
```

---

## 3.7 Gestion d'erreurs centralisée

En Express, un middleware à quatre arguments attrape toutes les erreurs :

```js
app.use((err, req, res, next) => {
  if (err instanceof HttpError) return res.status(err.status).json(err.toJSON())
  console.error(err)
  res.status(500).json({ title: 'Internal Server Error' })
})
```

En PHP pur, c'est un `try` / `catch` dans le front controller, autour de tout le traitement :

```php
$request = Request::fromGlobals();

try {
    // créer les objets, enregistrer les routes...
    $response = $router->dispatch($request);
} catch (HttpException $e) {             // erreurs « prévues » : 400, 404, 405, 422
    $response = $e->toResponse();
} catch (\Throwable $e) {                // tout le reste : bug, base indisponible...
    error_log((string) $e);
    $response = new JsonResponse(['title' => 'Internal Server Error', 'status' => 500], 500);
}

$response->send();                       // un seul envoi, à la fin
```

- Le code métier **lance** des exceptions (`throw new HttpException(404, 'Task 3 not found')`) au lieu de construire
  des réponses d'erreur partout, comme `next(err)` en Express.
- L'ordre des `catch` compte : le premier qui correspond gagne. `\Throwable` attrape tout, il vient donc **en dernier**.
- Une erreur non attrapée ne fait pas tomber un serveur (il n'y a pas de serveur qui tourne) : seule cette requête échoue.

### Journaliser

`error_log()` est votre `console.error()` : le message part dans les logs du serveur, pas dans la réponse.

```php
error_log('Création de la tâche ' . $task->id);
error_log(print_r($data, true));     // print_r(..., true) renvoie une chaîne au lieu de l'afficher
error_log((string) $e);              // une exception convertie en chaîne : message + pile d'appels
```

```bash
make logs s=api-php      # suit les logs du conteneur
```

N'utilisez pas `var_dump()` ou `echo` pour déboguer une API : ils écrivent dans la réponse et cassent le JSON
(et provoquent souvent un « headers already sent »).

---

## À retenir

1. **Un seul point d'entrée**, `public/index.php`, exécuté de zéro à chaque requête.
2. **Les superglobales** (`$_SERVER`, `$_GET`) remplacent `req` ; le corps JSON se lit dans **`php://input`**, pas dans `$_POST`.
3. **En-têtes d'abord, corps ensuite** : un seul `echo`, à la fin.
4. **`json_decode($x, true, flags: JSON_THROW_ON_ERROR)`** et **`json_encode($x, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)`**.
5. **PDO + requêtes préparées**, toujours. `fetch()` renvoie `false` quand il n'y a rien.
6. **Les erreurs sont des exceptions**, converties en réponse JSON à un seul endroit.
