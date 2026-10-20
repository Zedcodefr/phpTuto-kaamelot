# 4. Fil rouge — l'API TaskBoard en PHP pur, à trois

> **Point de départ** : [apps/api-php](../../../apps/api-php/README.md). `public/index.php` répond à `GET /api/health`, et `src/` contient des **squelettes de classes** répartis en 3 lots : 🅰️ 🅱️ 🅲.
> **Arrivée** : `make php-check` tout vert, et les tâches affichées dans le front sur <http://localhost:5173>.
> **Spécification** : [contrat d'API, partie 1](../../api-contract.md#partie-1--tâches). Tout le fonctionnel y est décrit : vous n'avez à décider que de la technique.
> **Ensuite** : une revue orale de lot par dev.
> **À votre rythme** : une phase est terminée quand elle est **comprise**. Si vous avancez vite, passez aux bonus ou à l'étape suivante. Ce qui compte, c'est de **comprendre** ce que vous écrivez.

Le front React fourni (`apps/front`) ne sert qu'à **voir le résultat** de votre API (`make use-php`) : vous n'avez pas à y toucher.
Vous êtes ici de l'autre côté du `fetch('/api/tasks')` que vous écrivez d'habitude dans vos composants Vue.

## Sommaire

- [Déroulé en 4 phases](#déroulé-en-4-phases)
- [Avant de commencer](#avant-de-commencer)
- [Phase 1 — Mob programming (Ex 1 et 2)](#phase-1--mob-programming-ex-1-et-2)
- [Phase 2 — Lecture collective des squelettes](#phase-2--lecture-collective-des-squelettes)
- [Organisation à 3 devs](#organisation-à-3-devs)
- [🅰️ Lot A — HTTP & routage (Dev 1)](#lot-a--http--routage-dev-1)
- [🅱️ Lot B — Données & PDO (Dev 2)](#lot-b--données--pdo-dev-2)
- [🅲 Lot C — Métier & validation (Dev 3)](#lot-c--métier--validation-dev-3)
- [Phase 4 — Intégration](#phase-4--intégration)
- [Bonus](#bonus)
- [IA : comment s'en servir ici](#ia--comment-sen-servir-ici)

---

## Déroulé en 4 phases

| Phase | Qui | Quoi | Fin quand... |
|---|---|---|---|
| **1. Mob programming** | les 3 ensemble, 1 écran | Ex 1 : comprendre `index.php` · Ex 2 : `GET /api/tasks` en dur | le front affiche des tâches (`make use-php`) |
| **2. Lecture des squelettes** | les 3 ensemble | tour de `src/` : qui possède quoi, pourquoi les signatures sont figées | chacun sait expliquer le contrat entre lots |
| **3. Lots en parallèle** | chacun son lot | 🅰️ HTTP & routage · 🅱️ Données & PDO · 🅲 Métier & validation | `make php-check-a` / `-b` / `-c` affiche **LOT TERMINÉ** |
| **4. Intégration** | Dev 1 au clavier, Dev 2 et Dev 3 à côté | câblage de `public/index.php` | `make php-check` **TOUT EST VERT** et démo du front |
| Revues orales | chaque dev avec le formateur | une méthode choisie par le dev, une par le formateur | le dev explique son code ligne par ligne |

L'IA est autorisée, et les squelettes, les vérifications et le contrat vous évitent toute décision fonctionnelle :
concentrez-vous sur la technique. Un dev qui termine son lot relit le code d'un collègue ou attaque un bonus. Si un lot
bloque, n'attendez pas : les deux autres viennent en binôme sur ce lot, puis on passe à l'intégration ensemble.

Pourquoi ce découpage ? La phase 1 met tout le monde au même niveau, sur les mêmes outils. Ensuite, des **signatures fournies** (constructeurs, interface du repository) servent de contrat : chacun avance sans attendre les autres et vérifie son lot isolément. C'est ce que vous faites déjà en front quand vous développez un composant contre le **type TypeScript** d'une réponse d'API, avec des données mockées, avant que l'API n'existe.

---

## Avant de commencer

```bash
make up                                   # tout démarre
curl http://localhost:8081/api/health     # {"status":"ok"}
make php-check                            # 1 vérification verte, les autres rouges : normal
make php-check-lots                       # les 3 lots : tout est « … à implémenter »
```

| Outil | Usage |
|---|---|
| `make php-check-a` / `-b` / `-c` | Vérifie **un lot**, indépendamment des deux autres (≈ lancer les tests d'un seul fichier avec Vitest) |
| `make php-check-lots` | Les trois lots d'un coup |
| `make php-check` | Le juge de paix final : tout le contrat HTTP de la partie 1 (utile à la phase 4) |
| `make php-lint` | Erreurs de syntaxe |
| `make logs s=api-php` | Erreurs PHP et `error_log()` |
| `make php-db-reset` | Remet la table `task` dans son état initial (4 tâches) |
| `make php-psql` | Console SQL (`\d task`, `SELECT * FROM task;`) |
| <http://localhost:8090> | Adminer (serveur `db`, utilisateur `app`, mot de passe `app`, base `taskboard_php`) |
| <http://localhost:5173> | Le front React, qui appelle votre API (`make use-php`) |

Lecture d'un résultat de lot :

```
  ✔ méthode en majuscules                    → OK
  … chemin sans query string (TODO Lot A…)   → la méthode lance encore « TODO » : à implémenter
  ✘ query = $_GET                            → implémentée mais fausse (le message d'erreur suit)
```

Règles du jeu, valables pour tous les lots :

- `declare(strict_types=1);` en tête de **chaque** fichier (déjà présent dans les squelettes) ; tout ce qui peut être typé est typé.
- Vous remplacez les `throw new \LogicException('TODO ...')` par votre code. **Vous ne modifiez jamais** une signature, un constructeur ou l'interface fournie.
- Pas de bibliothèque externe : tout est fait main, c'est le but. Pas d'équivalent d'`express` ou de `zod` ici.

Rappels utiles de l'étape précédente : [Fondamentaux back-end](../01-fondamentaux/README.md) (codes HTTP, SQL, injection).

---

## Phase 1 — Mob programming (Ex 1 et 2)

Les trois devs travaillent sur **un seul écran**, et le clavier change régulièrement de main. Celui qui tape ne décide pas : il exécute ce que les deux autres expliquent. L'objectif est que tout le monde comprenne le modèle d'exécution et les outils **avant** de se séparer.

### Ex 1 — Comprendre le squelette

**Objectif** : comprendre le cycle d'une requête PHP et l'environnement.

**Notions** : [modèle share-nothing](01-principes.md#12-le-grand-changement--share-nothing), [front controller](03-php-et-http.md#31-le-front-controller), [superglobales](03-php-et-http.md#32-lire-la-requête--les-superglobales), [écrire la réponse](03-php-et-http.md#33-écrire-la-réponse).

**Consignes**

1. Lisez `apps/api-php/public/index.php` ligne par ligne, et expliquez chaque ligne à voix haute.
   Cherchez l'équivalent de chaque ligne dans un petit serveur Express.
2. Jouez avec curl et observez (`-i` affiche les en-têtes, comme l'onglet Réseau des DevTools) :
   ```bash
   curl -i http://localhost:8081/api/health
   curl -i http://localhost:8081/api/nimporte
   curl -i -X POST http://localhost:8081/api/health
   ```
3. Ajoutez temporairement `error_log(print_r($_SERVER, true));` et regardez `make logs s=api-php`. Trouvez `REQUEST_METHOD`, `REQUEST_URI` et `HTTP_USER_AGENT`.
4. Ajoutez un `echo 'debug';` **avant** l'appel à `header()`. Que se passe-t-il, et pourquoi ? Retirez-le.
5. Ajoutez une variable `static $counter` dans une fonction appelée à chaque requête, et renvoyez sa valeur dans `/api/health`.
   Rafraîchissez plusieurs fois : que constatez-vous ? Comparez avec un `let counter = 0` au niveau d'un module Express.
6. Parcourez `tools/contract-check.php` : c'est du PHP « pur », lui aussi.

**Critères d'acceptation** : chacun sait expliquer pourquoi le compteur ne s'incrémente pas, et ce que signifie « headers already sent ».

### Ex 2 — GET /api/tasks en dur

**Objectif** : premier endpoint, et premier affichage dans le front.

**Notions** : [tableaux](02-grammaire.md#24-le-tableau-php-array), [JSON](03-php-et-http.md#34-json), [dates](02-grammaire.md#213-dates).

**Consignes** : dans `index.php`, ajoutez une condition pour `GET /api/tasks` qui renvoie un tableau PHP **en dur** de 2 ou 3 tâches au format du contrat (`id`, `title`, `description`, `status`, `createdAt`).
C'est l'équivalent d'un `res.json([...])` avec des données mockées.

**Critères d'acceptation**

```bash
curl http://localhost:8081/api/tasks
# [{"id":1,"title":"...","description":null,"status":"todo","createdAt":"2026-10-15T09:30:00+02:00"}, ...]
```

- `make use-php`, puis <http://localhost:5173> : **vos tâches s'affichent dans le front** 🎉
- Dans `make php-check`, les deux lignes `GET /api/tasks` passent au vert.

<details>
<summary>💡 Indice</summary>

```php
if ($method === 'GET' && $path === '/api/tasks') {
    echo json_encode([
        ['id' => 1, 'title' => 'Apprendre PHP', 'description' => null, 'status' => 'todo',
         'createdAt' => (new \DateTimeImmutable())->format(/* quel format ? */)],
        // ...
    ], /* des flags ? */);

    return;
}
```

Les accents sortent en `é` ? Regardez les flags de `json_encode`.
Un objet JS `{ id: 1 }` s'écrit `['id' => 1]` en PHP.
</details>

---

## Phase 2 — Lecture collective des squelettes

Ensemble, en ouvrant chaque fichier de `src/` :

```
src/
├── Http/
│   ├── Request.php               constructeur FOURNI · fromGlobals() 🅰️ · json() 🅲
│   ├── JsonResponse.php          constructeur FOURNI · send() 🅰️
│   ├── HttpException.php         constructeur FOURNI · toResponse() / toArray() 🅰️
│   ├── ValidationException.php   constructeur FOURNI · toArray() 🅰️
│   └── Router.php                🅰️ entièrement
├── Enum/TaskStatus.php           FOURNI complet (exemple d'enum)
├── Entity/Task.php               constructeur FOURNI · fromRow() / jsonSerialize() 🅱️
├── Database/Connection.php       🅱️
├── Repository/
│   ├── TaskRepositoryInterface.php   FOURNI : le contrat entre 🅱️ et 🅲
│   └── TaskRepository.php        🅱️ (implémentation PDO)
├── Validation/TaskValidator.php  🅲
└── Controller/TaskController.php 🅲
```

Les questions à se poser ensemble (le formateur peut les poser à l'oral) :

1. **Pourquoi les constructeurs sont-ils fournis ?** Ce sont eux que les autres lots utilisent. Le Lot C fait `new JsonResponse($task, 201, [...])` sans attendre que le Lot A ait écrit `send()`. Il en va de même pour `new Task(...)` et `new HttpException(404, ...)`.
2. **Pourquoi une interface pour le repository ?** Le `TaskController` dépend de `TaskRepositoryInterface`, pas de la classe PDO. Le Lot C se teste donc avec un faux repository en mémoire (`tools/checks/Fake/InMemoryTaskRepository.php`), un *test double*, pendant que le Lot B écrit la vraie implémentation. C'est l'équivalent d'un `vi.mock()` de votre module d'API dans un test Vitest, mais rendu possible par le **type** du paramètre, sans magie.
3. **Pourquoi les propriétés `public readonly` sur `JsonResponse` ?** Pour qu'une vérification puisse **inspecter** une réponse (`$response->status`, `$response->data`) sans l'envoyer.
4. **Que se passe-t-il quand une méthode n'est pas encore écrite ?** Elle lance `LogicException('TODO Lot X')`, que les scripts de vérification affichent en jaune (« à implémenter ») au lieu de planter.

---

## Organisation à 3 devs

### Répartition

| Lot | Dev | Fichiers / méthodes possédés | Vérification |
|---|---|---|---|
| 🅰️ **HTTP & routage** | Dev 1 | `Request::fromGlobals()`, `JsonResponse::send()`, `HttpException::toResponse()` / `toArray()`, `ValidationException::toArray()`, `Router` (tout), puis **l'intégration** dans `public/index.php` | `make php-check-a` (24 vérifications) |
| 🅱️ **Données & PDO** | Dev 2 | `Task::fromRow()` / `jsonSerialize()`, `Connection::fromEnvironment()`, `TaskRepository` (tout) | `make php-check-b` (16 vérifications, base réelle) |
| 🅲 **Métier & validation** | Dev 3 | `Request::json()`, `TaskValidator`, `TaskController` (tout) | `make php-check-c` (37 vérifications) |

**Convention de propriété** : chacun ne modifie **que ses méthodes**, jamais les signatures ni les constructeurs.
`Request.php` est le seul fichier partagé entre deux lots : `fromGlobals()` appartient à 🅰️ et `json()` à 🅲. Les modifications portent sur des méthodes différentes, donc Git fusionne sans conflit. **Tout changement de signature se discute à trois**, car il casse le contrat des autres (comme modifier un type partagé entre front et back).

### Comment chaque vérification reste indépendante des autres lots

| Lot | Ce qui est testé | Ce qui le rend indépendant |
|---|---|---|
| 🅰️ | `fromGlobals()` en remplissant `$_SERVER` / `$_GET`, `send()` en capturant la sortie (`ob_start()`), le format des erreurs, le routage avec des handlers factices | Aucun code de B ou C n'est appelé : les handlers sont des closures de test. Les en-têtes et le code HTTP réels sont vérifiés plus tard par `make php-check`. |
| 🅱️ | `Task`, `Connection`, `TaskRepository` contre la **vraie** base `taskboard_php` | Tout se passe dans une transaction **annulée** à la fin (`rollBack()`) : aucune donnée n'est modifiée et aucun autre lot n'est nécessaire. |
| 🅲 | `Request::json()`, `TaskValidator`, `TaskController` | Le contrôleur reçoit un `InMemoryTaskRepository`, et les `JsonResponse` sont **inspectées** sans être envoyées : `send()` (🅰️) et la base (🅱️) ne sont jamais utilisés. |

### Équilibre de charge

- 🅲 a le plus de vérifications, mais chacune est simple (des `if`, des tableaux) : c'est le lot le plus **volumineux**, et le plus proche de la validation de formulaire que vous connaissez en front.
- 🅱️ en a le moins, mais c'est le lot le plus **dense** en notions nouvelles (PDO, SQL, dates, fuseaux). Celui qui finit le premier vient **en binôme** avec le Lot B : le Dev 2 garde le clavier, puisqu'il reste propriétaire de son code.
- 🅰️ porte en plus l'**intégration** (phase 4), qu'il prépare en lisant les lots B et C au fil de l'eau.

### Git

Une branche par lot (`feature/lot-a-http`, `feature/lot-b-data`, `feature/lot-c-metier`) et une PR par lot, relue par **un des deux autres devs**. Le relecteur doit pouvoir expliquer le code qu'il approuve. L'intégration se fait sur une 4ᵉ branche, une fois les 3 PR fusionnées.

---

## Lot A — HTTP & routage (Dev 1)

`make php-check-a` — fichiers : `src/Http/*` (sauf `Request::json()`), puis `public/index.php` à la phase 4.
C'est le lot qui réécrit à la main ce qu'Express vous donne : `req`, `res.json()`, `app.get()`.

### A1 — `Request::fromGlobals()`

**Objectif** : transformer les superglobales en un objet `Request` immuable (votre `req`).
**Notions** : [superglobales](03-php-et-http.md#32-lire-la-requête--les-superglobales), [fonctions utiles sur les chaînes](02-grammaire.md#fonctions-utiles), [opérateurs `??` et `?:`](02-grammaire.md#autres-opérateurs), [arguments nommés](02-grammaire.md#27-fonctions).
**Consignes** : méthode en majuscules, chemin **sans** query string ni `/` final (mais `/` seul reste `/`), `query` = `$_GET`, corps lu dans `php://input`.
**Critère** : la section `Request::fromGlobals()` est verte (5 lignes).

<details>
<summary>💡 Indices</summary>

- `parse_url($uri, PHP_URL_PATH)` extrait le chemin, mais peut renvoyer `null` ou `false` : prévoyez un repli.
- `rtrim($path, '/')` transforme `/` en `''`. Comment récupérer `/` dans ce cas ? (`?:` est votre ami, comme `||` en JS.)
- `$_SERVER['REQUEST_METHOD'] ?? 'GET'` : en ligne de commande, la clé peut manquer.
- `file_get_contents('php://input')` renvoie `string|false`.
</details>

### A2 — `JsonResponse::send()`

**Objectif** : envoyer code, en-têtes et corps JSON (votre `res.status().set().json()`).
**Notions** : [écrire la réponse](03-php-et-http.md#33-écrire-la-réponse), [« Headers already sent »](03-php-et-http.md#-headers-already-sent-), [JSON](03-php-et-http.md#34-json), [`JsonSerializable`](03-php-et-http.md#contrôler-la-forme-avec-jsonserializable).
**Consignes** : `http_response_code()`. En 204, **ni corps ni Content-Type**. Sinon, envoyez `Content-Type: application/json; charset=utf-8`, puis les en-têtes de `$this->headers`, puis le JSON sans échapper les accents ni les `/`.
**Critère** : section `JsonResponse::send()` verte.

<details>
<summary>💡 Indices</summary>

- Ordre impératif : **tous** les `header()` avant le premier `echo`.
- Flags utiles : `JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.
- Itérer sur un tableau associatif : `foreach ($this->headers as $name => $value)` (≈ `for (const [name, value] of Object.entries(headers))`). L'interpolation `"$name: $value"` suffit pour construire la ligne.
- `json_encode()` appelle tout seul `jsonSerialize()` sur les objets qui implémentent `JsonSerializable`, comme `JSON.stringify()` appelle `toJSON()`.
</details>

**Piège** : en ligne de commande (les scripts de vérification), PHP signale « headers already sent ». La vérification ignore ces avertissements, et les vrais en-têtes sont contrôlés par `make php-check`.

### A3 — `HttpException` et `ValidationException`

**Objectif** : produire le corps « problem details » du [contrat](../../api-contract.md#format-des-erreurs).
**Notions** : [exceptions](02-grammaire.md#212-exceptions), [héritage, `parent::`](02-grammaire.md#héritage-interfaces-classes-abstraites), [`self`, `static`, `parent`](02-grammaire.md#this-self-static-parent), [spread de tableaux](02-grammaire.md#déstructuration-et-spread).
**Consignes** :
- `toArray()` renvoie `['title' => ..., 'status' => ..., 'detail' => <message>]`, avec les titres : 400 Bad Request, 404 Not Found, 405 Method Not Allowed, 422 Validation Failed, sinon « Error ».
- `toResponse()` emballe ce tableau dans une `JsonResponse`, avec le bon code.
- `ValidationException::toArray()` réutilise le tableau parent et y ajoute `violations` : une liste de `['propertyPath' => champ, 'title' => message]`.

**Critère** : section `HttpException / ValidationException` verte.

<details>
<summary>💡 Indices</summary>

- Une constante de classe `private const TITLES = [400 => 'Bad Request', ...]`, puis `self::TITLES[$this->status] ?? 'Error'`.
- `toResponse()` appelle `$this->toArray()` : grâce au polymorphisme, une `ValidationException` produira automatiquement ses violations. Aucun `instanceof` n'est nécessaire.
- Dans `ValidationException` : `[...parent::toArray(), 'violations' => $violations]`, comme `{ ...parent, violations }` en JS.
- Le message de l'exception se lit avec `$this->getMessage()`.
</details>

### A4 — `Router`

**Objectif** : associer « méthode + motif d'URL » à un handler, et l'appeler avec ses paramètres (votre `app.get('/api/tasks/:id', ...)`).
**Notions** : [routage manuel](03-php-et-http.md#36-routage-manuel), [callables](02-grammaire.md#callables), [closures](02-grammaire.md#closures--capture-explicite), [les méthodes de tableau, version PHP](02-grammaire.md#les-méthodes-de-tableau-js-version-php), [arguments nommés et variadiques](02-grammaire.md#27-fonctions).
**Consignes** :
- `get/post/patch/delete` enregistrent une route. Un motif `/api/tasks/{id}` ne doit accepter que des chiffres et **tout** le chemin (`/api/tasks/42/extra` → 404).
- `dispatch()` appelle le **premier** handler qui correspond, sous la forme `$handler($request, id: '42')`.
- Aucun motif ne correspond au chemin → `HttpException(404)`. Le chemin existe mais pas pour cette méthode → `HttpException(405)`.

**Critère** : section `Router` verte (9 lignes).

<details>
<summary>💡 Indices</summary>

- Stockez les routes dans une propriété typée `private array $routes = [];`. Une méthode privée `add(string $method, ...)` évite de répéter le code quatre fois.
- Transformez le motif en regex **à l'enregistrement** : `preg_replace('#\{(\w+)\}#', '(?<$1>\d+)', $pattern)`, encadré par `#^` et `$#`.
- `preg_match($regex, $path, $matches)` remplit `$matches` avec les groupes **numérotés et nommés** (≈ `match.groups` en JS, mélangé aux index). Gardez les clés string : `array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY)`.
- L'appel `$handler($request, ...$params)` avec `$params = ['id' => '42']` produit des **arguments nommés** : le paramètre du contrôleur doit donc s'appeler `$id`.
- Pour distinguer 404 de 405, retenez si au moins un motif a correspondu au chemin.
</details>

**Pièges** : un handler stocké dans un tableau s'appelle `($route['handler'])(...)`, avec les parenthèses. Une regex sans `^...$` accepte `/api/tasks/42/extra`.

### A5 — Préparer l'intégration

Pendant que B et C terminent, relisez leurs PR et préparez le câblage de `index.php` (voir [Phase 4](#phase-4--intégration)). Vous en êtes responsable.

---

## Lot B — Données & PDO (Dev 2)

`make php-check-b` — fichiers : `src/Entity/Task.php`, `src/Database/Connection.php`, `src/Repository/TaskRepository.php`.
La vérification s'arrête après `Connection` tant que celle-ci n'est pas faite : commencez donc par B1, puis B2.
C'est le lot qui remplace ce que ferait un client `pg` dans une API Node.

### B1 — `Task::fromRow()` et `jsonSerialize()`

**Objectif** : passer d'une ligne SQL à un objet, et d'un objet au JSON du contrat.
**Notions** : [une classe complète](02-grammaire.md#une-classe-complète-commentée), [enums `from()`](02-grammaire.md#29-enums), [dates](02-grammaire.md#213-dates), [`JsonSerializable`](03-php-et-http.md#contrôler-la-forme-avec-jsonserializable), [arguments nommés](02-grammaire.md#27-fonctions).
**Consignes** : colonnes `id, title, description, status, created_at`. Sortie JSON en camelCase avec `createdAt` au format `2026-10-15T09:30:00+02:00`.
**Critère** : section `Task` verte.

<details>
<summary>💡 Indices</summary>

- `new self(title: $row['title'], ..., id: (int) $row['id'])` : les arguments nommés rendent l'appel lisible.
- **PDO renvoie souvent des strings** : `'7'` et non `7`. Avec `strict_types`, un `(int)` explicite s'impose (≈ `Number(row.id)`).
- `TaskStatus::from($row['status'])` et, dans l'autre sens, `$this->status->value`.
- `new \DateTimeImmutable('2026-10-15 09:30:00+02')` sait lire le format PostgreSQL. Pour écrire, utilisez `->format(DATE_ATOM)`.
- `createdAt` est nullable : `$this->createdAt?->format(...)`.
</details>

### B2 — `Connection::fromEnvironment()`

**Objectif** : créer un `PDO` correctement configuré.
**Notions** : [PDO — connexion](03-php-et-http.md#connexion), [`throw` en expression](02-grammaire.md#212-exceptions).
**Consignes** : lisez `DB_DSN`, `DB_USER` et `DB_PASSWORD` avec `getenv()` (≈ `process.env`). Activez les exceptions, les lignes en tableaux associatifs et les vraies requêtes préparées. Alignez le **fuseau de la session SQL** sur `date_default_timezone_get()`.
**Critère** : section `Connection` verte (5 lignes).

<details>
<summary>💡 Indices</summary>

- `getenv('DB_DSN') ?: throw new \RuntimeException('DB_DSN is not defined')` : `getenv()` renvoie `false` si la variable est absente (pas `undefined`). Ce raccourci convient au DSN, jamais vide. Pas au mot de passe : `?:` rejette aussi la chaîne vide, qui est un mot de passe légitime. Testez `getenv('DB_PASSWORD') === false`.
- `$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION)`, puis la même chose pour `ATTR_DEFAULT_FETCH_MODE` et `ATTR_EMULATE_PREPARES`. Avec PostgreSQL, `ATTR_EMULATE_PREPARES` vaut déjà `false` par défaut, mais pas avec MySQL : soyez explicite.
- PostgreSQL : sans fuseau de session, les dates reviennent en UTC (`+00:00`) et ne correspondent plus à ce qu'attend le front. `SET TIME ZONE` n'accepte pas de paramètre `:tz` : ne concaténez pas `date_default_timezone_get()` dans le SQL. Utilisez plutôt `SELECT set_config('TimeZone', :tz, false)` dans une requête préparée (ou, à défaut, la valeur littérale constante `SET TIME ZONE 'Europe/Paris'`, celle de `docker/php/php.ini`).
- N'oubliez pas le `\` devant `PDO` dans un fichier qui déclare un namespace.
</details>

### B3 — Lecture : `findAll()` et `find()`

**Objectif** : lire des tâches avec des requêtes préparées.
**Notions** : [PDO — lire](03-php-et-http.md#lire), [injection SQL](03-php-et-http.md#injection-sql), [`array_map` et callables](02-grammaire.md#les-méthodes-de-tableau-js-version-php), [first-class callable](02-grammaire.md#callables).
**Consignes** : `findAll()` renvoie une `list<Task>` triée par id. `find()` renvoie la tâche, ou `null`.
**Critère** : lignes `findAll()` et `find(id inexistant) → null`.

<details>
<summary>💡 Indices</summary>

- Sans paramètre : `$this->pdo->query('SELECT ...')->fetchAll()`.
- Avec paramètre : `prepare('... WHERE id = :id')`, puis `execute(['id' => $id])`, puis `fetch()`.
- `fetch()` renvoie **`false`** (et non `null` ni `undefined`) quand il n'y a pas de ligne.
- `array_map(Task::fromRow(...), $rows)` : la syntaxe `(...)` transforme une méthode statique en callable (≈ `rows.map(Task.fromRow)`).
</details>

### B4 — Écriture : `save()` et `delete()`

**Objectif** : INSERT ou UPDATE selon que la tâche a un id, puis DELETE.
**Notions** : [PDO — écrire](03-php-et-http.md#écrire), [transactions](03-php-et-http.md#transactions) (pour comprendre comment la vérification annule tout).
**Consignes** : `save()` renvoie la tâche **relue depuis la base**, avec `id` et `createdAt` renseignés par PostgreSQL. `delete()` renvoie `false` si aucune ligne n'a été supprimée.
**Critère** : toute la section `TaskRepository` est verte, y compris « une apostrophe ne casse rien ».

<details>
<summary>💡 Indices</summary>

- PostgreSQL sait renvoyer la ligne écrite : `INSERT INTO task (...) VALUES (...) RETURNING *`, ce qui évite un second SELECT. La même astuce fonctionne avec `UPDATE ... RETURNING *`.
- Préparez un tableau `$params` commun et ajoutez `'id'` seulement pour l'UPDATE.
- Passez `$task->status->value`, pas l'enum : PDO ne sait pas convertir un enum.
- `$statement->rowCount()` donne le nombre de lignes touchées par un DELETE.
</details>

**Piège** : concaténer une valeur dans le SQL (`"... WHERE id = $id"`, l'équivalent d'un template literal) fonctionne, jusqu'à ce que quelqu'un saisisse `'`. La vérification « une apostrophe » est là pour ça.

---

## Lot C — Métier & validation (Dev 3)

`make php-check-c` — fichiers : `Request::json()` dans `src/Http/Request.php`, `src/Validation/TaskValidator.php`, `src/Controller/TaskController.php`.
Le contrôleur est testé avec `InMemoryTaskRepository` : lisez ce fichier, il est court et montre comment se comporte un repository.

### C1 — `Request::json()`

**Objectif** : décoder le corps JSON et refuser ce qui n'est pas un objet JSON (votre `express.json()`, en plus strict).
**Notions** : [pourquoi le JSON n'est pas dans `$_POST`](03-php-et-http.md#pourquoi-le-json-nest-pas-dans-_post), [JSON](03-php-et-http.md#34-json), [liste et dictionnaire](02-grammaire.md#liste-et-dictionnaire--la-même-structure), [exceptions](02-grammaire.md#212-exceptions).
**Consignes** : JSON invalide ou corps vide → `HttpException(400)`. JSON valide mais qui n'est pas un objet (`[1,2]`, `[]`, `42`) → 400 aussi. `{}` → `[]`.
**Critère** : section `Request::json()` verte (8 lignes).

<details>
<summary>💡 Indices</summary>

- `json_decode($this->body, flags: JSON_THROW_ON_ERROR)` lève une `\JsonException` sur un JSON invalide : attrapez-la et relancez une `HttpException(400, ...)`.
- Avec `true`, `{}` et `[]` se décodent tous les deux en `[]` : impossible de les distinguer ensuite, et `array_is_list()` rejetterait à tort l'objet `{"0":"a"}`.
- Décodez d'abord **sans** `true` : un objet JSON devient un `\stdClass`, une liste un `array`, `42` un `int`. Un seul test suffit alors : `$data instanceof \stdClass`. Redécodez ensuite avec `true` pour obtenir le tableau associatif (`(array) $data` ne convertit que le premier niveau).
</details>

### C2 — `TaskValidator::validate()`

**Objectif** : appliquer les règles du contrat et renvoyer **toutes** les erreurs d'un coup, comme un formulaire Vue qui affiche une erreur sous chaque champ.
**Notions** : [UTF-8 : les chaînes sont des octets](02-grammaire.md#utf-8--les-chaînes-sont-des-octets), [`==` vs `===`](02-grammaire.md#-vs-), [null, pas d'undefined](02-grammaire.md#null-pas-dundefined), [enums `tryFrom()`](02-grammaire.md#29-enums), [tableaux](02-grammaire.md#24-le-tableau-php-array).
**Consignes** : les règles et les messages exacts figurent dans le docblock de la classe. En mode `partial` (PATCH), seuls les champs **présents** sont validés. Les erreurs s'accumulent dans un tableau `champ => message`, puis une seule `ValidationException` est lancée.
**Critère** : section `TaskValidator` verte (13 lignes).

<details>
<summary>💡 Indices</summary>

- « Présent » et « non null » sont deux choses différentes : `array_key_exists('title', $data)` vs `isset($data['title'])`. Laquelle convient pour `title` en PATCH ? Et pour `description` ?
- `strlen('Été')` vaut 5 (octets), alors que `mb_strlen('Été')` vaut 3 (caractères, comme `'Été'.length` en JS). Le contrat parle de caractères.
- `trim($title) === ''` détecte un titre composé uniquement d'espaces.
- `TaskStatus::tryFrom((string) $value) === null` détecte un statut inconnu, et `TaskStatus::values()` est déjà écrite pour composer le message.
- Un `elseif` évite d'avoir deux messages pour le même champ.
</details>

### C3 — `TaskController` : lecture (`list`, `show`)

**Objectif** : premières actions, et gestion du 404.
**Notions** : [`throw` en expression](02-grammaire.md#212-exceptions), [interfaces](02-grammaire.md#héritage-interfaces-classes-abstraites).
**Consignes** : `list()` → `new JsonResponse($this->repository->findAll())`. `show()` → la tâche, ou `HttpException(404, "Task $id not found")`. L'id arrive en **string** : convertissez-le.
**Critère** : lignes `list()` et `show(...)`.

<details>
<summary>💡 Indice</summary>

Factorisez dans une méthode privée :
`return $this->repository->find($id) ?? throw new HttpException(404, ...);`
Vous la réutiliserez dans `update()`.
</details>

### C4 — `create()`

**Objectif** : POST complet : décoder, valider, construire, enregistrer, répondre 201 avec un en-tête `Location`.
**Notions** : [arguments nommés](02-grammaire.md#27-fonctions), [enums](02-grammaire.md#29-enums), [`??`](02-grammaire.md#autres-opérateurs).
**Critère** : les 5 lignes `create()`.

<details>
<summary>💡 Indices</summary>

- L'enchaînement : `$data = $request->json();`, puis `$this->validator->validate($data);`, puis `new Task(...)`, puis `$this->repository->save(...)`. Aucun `await` : tout est synchrone.
- Le statut par défaut : `TaskStatus::from($data['status'] ?? TaskStatus::Todo->value)`.
- **Utilisez la tâche renvoyée par `save()`** : c'est elle qui porte l'id.
- `new JsonResponse($task, 201, ['Location' => "/api/tasks/$task->id"])`.
</details>

### C5 — `update()` et `delete()`

**Objectif** : PATCH partiel (seuls les champs envoyés changent) et DELETE.
**Consignes** : `description: ""` est enregistrée comme `null`, en POST comme en PATCH (où elle signifie « effacer »). DELETE → 204 sans corps, ou 404 si `repository->delete()` renvoie `false`.
**Critère** : toute la section `TaskController` est verte. Le lot affiche **LOT TERMINÉ**.

<details>
<summary>💡 Indices</summary>

- Validez avec `partial: true`.
- `if (array_key_exists('status', $data)) { $task->status = TaskStatus::from($data['status']); }`, et de même pour les autres champs.
- 204 : `new JsonResponse(status: 204)`. Les arguments nommés permettent de sauter `$data`.
</details>

**Piège** : en PHP, les **objets** sont partagés comme en JS, mais les **tableaux** sont copiés. Modifier `$data` dans une fonction appelée ne modifie pas le tableau de l'appelant ([copie par valeur](02-grammaire.md#copie-par-valeur)).

---

## Phase 4 — Intégration

Le Dev 1 est au clavier et les deux autres l'aident, une fois les trois lots fusionnés (`make php-check-lots` entièrement vert).

**Notions** : [front controller](03-php-et-http.md#31-le-front-controller), [gestion d'erreurs centralisée](03-php-et-http.md#37-gestion-derreurs-centralisée), [journaliser](03-php-et-http.md#journaliser), [callables `$obj->method(...)`](02-grammaire.md#callables).

Checklist de `public/index.php` :

- [ ] `Request::fromGlobals()` remplace `$_SERVER` lu à la main.
- [ ] On crée à la main la chaîne de dépendances : `Connection::fromEnvironment()` → `new TaskRepository($pdo)` → `new TaskController($repository)`. C'est un « conteneur d'injection » fait main, que Symfony automatisera (un peu comme `provide` / `inject` en Vue, mais résolu par le type).
- [ ] Les 6 routes du contrat sont déclarées : `/api/health` (une closure suffit) et les 5 routes tâches, en `$taskController->list(...)`, etc.
- [ ] `try { $response = $router->dispatch($request); }`
- [ ] `catch (HttpException $e)` → `$response = $e->toResponse();`
- [ ] `catch (\Throwable $e)` → `error_log((string) $e);` et une `JsonResponse` 500 (`title`, `status`, `detail`).
- [ ] Un seul `$response->send();` à la fin.
- [ ] Le code procédural des exercices 1 et 2 a disparu.

**Critères d'acceptation**

```bash
make php-db-reset
make php-check     # TOUT EST VERT
make use-php       # puis http://localhost:5173 : les 4 tâches de la base s'affichent
```

**Démo** (en fin d'intégration, devant le formateur) : chacun présente **une méthode de son lot** et un piège rencontré, puis l'équipe crée une tâche avec curl et la voit apparaître dans le front.

<details>
<summary>💡 Indice : la structure générale</summary>

```php
$request = Request::fromGlobals();

try {
    $taskController = new TaskController(new TaskRepository(Connection::fromEnvironment()));

    $router = new Router();
    $router->get('/api/tasks', $taskController->list(...));
    // ...

    $response = $router->dispatch($request);
} catch (HttpException $e) {
    // ...
} catch (\Throwable $e) {
    // ...
}

$response->send();
```

Pourquoi `HttpException` doit-il être attrapé **avant** `\Throwable` ?
</details>

**Pièges** : l'ordre des `catch` compte, car le premier qui correspond gagne. Si `make php-check` échoue sur les en-têtes (`Location`, `Content-Type`), cherchez un `echo` ou une ligne vide **avant** `<?php` dans l'un des fichiers.

---

## Bonus

Une fois votre lot terminé et compris, sans casser `make php-check` :

1. **405 propre** : ajoutez l'en-tête `Allow: GET, POST` aux réponses 405. C'est au Lot A de faire évoluer le `Router`, et à l'équipe de décider comment transmettre l'information.
2. **Filtre `?status=`** : `GET /api/tasks?status=todo` (voir la [partie 2C du contrat](../../api-contract.md#filtres--pagination-sur-get-apitasks)). Il faut une nouvelle méthode dans `TaskRepositoryInterface`, puis faire évoluer à trois l'implémentation PDO, le faux repository et le contrôleur. Un statut invalide → 422.
3. **Avant-goût de PHPUnit** : `make php-composer c="require --dev phpunit/phpunit"`, puis réécrivez trois vérifications de votre lot sous forme de tests PHPUnit (`tests/`). Comparez avec Vitest : `expect(x).toBe(y)` devient `self::assertSame($y, $x)`. Vous retrouverez PHPUnit avec Symfony.

---

## IA : comment s'en servir ici

L'IA est autorisée, comme elle le sera dans votre vrai projet. Ce qui compte : **tout ce que vous commitez, vous l'avez lu,
exécuté et compris.** Les lots sont des exercices d'apprentissage, donc la façon de s'en servir change ce que vous retenez :

- **Essayez d'abord** par vous-même, puis demandez à l'IA de débloquer, d'expliquer ou de relire.
  Les prompts qui font apprendre : « explique-moi ce que fait `array_filter` avec `ARRAY_FILTER_USE_KEY` »,
  « quel est l'équivalent JavaScript de… », « relis ma méthode et dis-moi ce qui ne respecte pas le docblock, sans la réécrire ».
- **Si vous faites générer une méthode**, relisez-la ligne par ligne, lancez la vérification du lot, et réécrivez avec vos mots
  ce que vous ne comprenez pas. Une vérification verte ne prouve pas que vous savez ce que fait le code.
- **Méfiez-vous** de ce que l'IA propose souvent en PHP : code sans `declare(strict_types=1)`, `==` au lieu de `===`,
  `strlen()` au lieu de `mb_strlen()`, requête SQL concaténée au lieu de préparée, `$_POST` pour lire du JSON,
  réflexes JavaScript transposés (`+` pour concaténer, attente d'un `await`, tableau modifié « par référence »),
  changement d'une signature fournie (interdit par le contrat entre lots, pas par la règle IA).

Après l'intégration, chaque dev passe une **revue de lot** avec le formateur : il choisit une méthode,
puis le formateur en choisit une autre, et chacune doit être expliquée ligne par ligne, avec ses propres mots.
Une méthode qu'on ne sait pas expliquer est à reprendre, même si elle passe les vérifications.

Les règles complètes sont dans la [charte IA](../../00-charte-ia.md).

---

> 🧑‍🏫 Une solution de référence complète existe chez le formateur. Elle passe les trois lots et `make php-check`. Demandez-lui un indice plutôt que de la chercher : c'est le chemin qui fait apprendre.
