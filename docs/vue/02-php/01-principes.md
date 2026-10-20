# 1. Principes de PHP — pour un dev Vue / JS

> Objectif : comprendre **comment PHP s'exécute** avant d'écrire la moindre ligne.
> La plupart des surprises d'un dev JS en PHP viennent de là, pas de la syntaxe.

## Sommaire

- [1.1 PHP en 2026](#11-php-en-2026)
- [1.2 Le grand changement : share-nothing](#12-le-grand-changement--share-nothing)
- [1.3 Pas d'async, pas de Promise : et pourtant ça tient](#13-pas-dasync-pas-de-promise--et-pourtant-ça-tient)
- [1.4 Pas de bundler : interprété + OPcache](#14-pas-de-bundler--interprété--opcache)
- [1.5 Les types : vérifiés à l'exécution](#15-les-types--vérifiés-à-lexécution)
- [1.6 Composer, le npm de PHP](#16-composer-le-npm-de-php)
- [1.7 On n'importe pas des fichiers : namespaces et autoload](#17-on-nimporte-pas-des-fichiers--namespaces-et-autoload)
- [1.8 Les PSR](#18-les-psr)
- [1.9 L'écosystème](#19-lécosystème)
- [1.10 L'outillage du projet](#110-loutillage-du-projet)
- [À retenir](#à-retenir)

---

## 1.1 PHP en 2026

Oubliez l'image du PHP des années 2000, mélangé au HTML avec des `mysql_query()` concaténés. Le PHP que vous allez écrire
(version **8.4**) ressemble beaucoup plus à du TypeScript strict qu'à du JavaScript de 2010 :

| Ce que vous connaissez en TS | En PHP 8.4 |
|---|---|
| `type Status = 'todo' \| 'done'` | `enum TaskStatus: string { case Todo = 'todo'; ... }` |
| `readonly` sur une propriété | `readonly`, et même `readonly class` |
| `constructor(private repo: Repo) {}` | `public function __construct(private Repo $repo) {}` |
| `a ?? b`, `a?.b` | `$a ?? $b`, `$a?->b` |
| Décorateurs `@Component` | Attributs `#[Route('/api/tasks')]` |
| `strict: true` | `declare(strict_types=1);` |

PHP fait tourner une grande partie du web (WordPress, Wikipédia, de nombreux back-offices et API métiers). Avec **Symfony**
et **Laravel**, c'est un écosystème back-end mûr, outillé, avec ORM, tests, analyse statique et injection de dépendances.

---

## 1.2 Le grand changement : share-nothing

C'est **la** différence fondamentale avec Node.

### Comment s'exécute une requête

**Avec Node / Express**, vous lancez **un** process qui vit longtemps :

```js
// server.js — lancé une fois avec `node server.js`, puis il tourne en continu
let counter = 0                      // vit aussi longtemps que le process
const app = express()
app.get('/count', (req, res) => res.json({ count: ++counter }))   // 1, 2, 3, 4...
app.listen(3000)
```

Toutes les requêtes passent dans **le même process**, la même mémoire, la même boucle d'événements.
`counter` est partagé entre tous les utilisateurs.

**Avec PHP**, c'est l'inverse : chaque requête **exécute le script de zéro**, puis tout est détruit.

```
Navigateur ──HTTP──▶ nginx ──FastCGI──▶ PHP-FPM ──▶ un worker exécute public/index.php
                                                    │  1. charge le code
                                                    │  2. crée les objets, se connecte à la base
                                                    │  3. calcule la réponse, l'envoie
                                                    └─ 4. TOUT est libéré : variables, objets, connexions
```

```php
<?php
$counter = 0;          // recréée à CHAQUE requête
$counter++;
echo json_encode(['count' => $counter]);   // toujours 1
```

- **PHP-FPM** (*FastCGI Process Manager*) garde un **pool de workers** prêts. Chaque worker traite une requête à la fois,
  du début à la fin.
- On parle de modèle **share-nothing** : deux requêtes ne partagent **rien** en mémoire.
- Dans ce projet, l'API PHP pur tourne avec le **serveur intégré** `php -S` (pratique pour apprendre, jamais en production).
  L'API Symfony tourne derrière **nginx + PHP-FPM**, comme en production.

### Conséquences concrètes

| En Node, vous feriez... | En PHP |
|---|---|
| Un cache en mémoire (`const cache = new Map()`) | Il disparaît à la fin de la requête : il faut Redis, APCu ou le cache de Symfony |
| Un singleton partagé (`export const db = new Pool()`) | Un « singleton » ne vit que le temps d'**une** requête |
| `setInterval()` pour une tâche de fond | Pas de process qui tourne : on utilise `cron`, une commande console ou une file de messages (Symfony Messenger) |
| WebSocket dans le même serveur | Un service à part (Mercure, un serveur Node, etc.) |
| Une fuite mémoire qui s'accumule | Impossible de fuir longtemps : la mémoire est libérée à chaque requête |
| Une exception non attrapée qui fait tomber le serveur | Seule **cette** requête échoue (erreur 500), les autres continuent |

> Le share-nothing est une **force** : pas d'état partagé, donc pas de bug de concurrence entre utilisateurs,
> pas de fuite mémoire qui s'accumule, et on ajoute des serveurs sans se poser de question.
> Son prix : tout ce qui doit durer (session, cache, données) vit **hors** du process, dans une base, Redis, un fichier.

---

## 1.3 Pas d'async, pas de Promise : et pourtant ça tient

En JavaScript, tout ce qui touche au réseau ou au disque est **asynchrone** :

```js
const res = await fetch('/api/tasks')          // ne bloque pas le thread
const rows = await pool.query('SELECT ...')    // idem
```

En PHP classique, **tout est synchrone et bloquant** :

```php
$rows = $pdo->query('SELECT * FROM task')->fetchAll();   // le worker attend la réponse de la base
$json = file_get_contents('https://exemple.fr/api');     // il attend aussi
```

Pas de `async`, pas de `await`, pas de `Promise`, pas de callback de fin. Le code se lit de haut en bas, dans l'ordre d'exécution.

**Pourquoi ça marche quand même ?** Parce que le parallélisme est assuré par les **workers**, pas par le code :

| Node | PHP-FPM |
|---|---|
| **1** thread, une boucle d'événements | **N** workers (ex. 20), un par requête en cours |
| Pendant qu'une requête attend la base, la boucle traite les autres | Pendant qu'un worker attend la base, les 19 autres traitent d'autres requêtes |
| Un calcul lourd **bloque tout le monde** | Un calcul lourd bloque **un** worker |
| Il faut penser `await` partout | On écrit du code séquentiel, sans y penser |

Pour une API métier (lire en base, valider, écrire, répondre), ce modèle est simple et robuste.
Il existe des runtimes PHP asynchrones ou « persistants » (FrankenPHP en mode worker, Swoole, ReactPHP), mais ce n'est
**pas** le modèle de ce projet ni celui de la plupart des projets Symfony.

> Conséquence pour vous : si vous vous surprenez à chercher comment « attendre » un appel en PHP, il n'y a rien à faire.
> La ligne suivante ne s'exécute qu'une fois la précédente terminée.

---

## 1.4 Pas de bundler : interprété + OPcache

En front, vous avez l'habitude d'une chaîne de build : Vite transpile le TS, compile les `.vue`, regroupe les modules.

En PHP, **il n'y a pas d'étape de build** :

1. PHP lit le fichier `.php` ;
2. il le compile en **opcodes** (un bytecode, comme le bytecode de V8) ;
3. **OPcache** garde ces opcodes en mémoire partagée, pour ne pas recompiler le fichier à chaque requête.

| Front Vue | PHP |
|---|---|
| `npm run build` produit un bundle | Rien à builder : on déploie les fichiers `.php` |
| HMR de Vite | Enregistrez le fichier, rafraîchissez : la requête suivante exécute le nouveau code |
| `tsc` vérifie les types avant l'exécution | Les types sont vérifiés **pendant** l'exécution (voir ci-dessous) ; PHPStan fait l'analyse statique |

En développement, ce projet configure OPcache pour **revalider à chaque requête** : vos modifications sont visibles immédiatement.

---

## 1.5 Les types : vérifiés à l'exécution

C'est une différence subtile mais importante avec TypeScript :

| | JavaScript | TypeScript | PHP |
|---|---|---|---|
| Quand les types sont-ils vérifiés ? | jamais | à la **compilation** (`tsc`), puis effacés | à l'**exécution**, à chaque appel |
| Un mauvais type passe-t-il en production ? | oui | oui, si une donnée externe ment (JSON, `as`) | **non** : `TypeError` immédiate |

```ts
// TypeScript : compile, puis plante plus loin (ou pas du tout)
function double(n: number): number { return n * 2 }
double(JSON.parse('"5"'))   // tsc ne voit rien : "5" * 2 = 10 à l'exécution
```

```php
<?php
declare(strict_types=1);

function double(int $n): int { return $n * 2; }

double(json_decode('"5"'));   // TypeError : Argument #1 ($n) must be of type int, string given
```

### `declare(strict_types=1)`

Sans cette ligne, PHP **convertit** silencieusement les scalaires : `double("5")` renverrait `10`, comme JavaScript.
Avec elle, toute conversion implicite est refusée. **Mettez-la en tête de chaque fichier** : c'est déjà le cas
dans tous les squelettes du projet.

> Pour un dev TS, PHP strict est un bon compromis : les types ne sont pas que de la documentation, ils sont **garantis**
> à l'exécution. En contrepartie, il n'y a pas d'inférence aussi riche ni de types structurels (voir la grammaire).

---

## 1.6 Composer, le npm de PHP

| npm | Composer |
|---|---|
| `package.json` | `composer.json` |
| `package-lock.json` | `composer.lock` (à commiter, lui aussi) |
| `node_modules/` | `vendor/` (jamais commité) |
| registre npm | [Packagist](https://packagist.org) |
| `npm install` | `composer install` |
| `npm install axios` | `composer require guzzlehttp/guzzle` |
| `npm install -D vitest` | `composer require --dev phpunit/phpunit` |
| `"scripts": {...}` | `"scripts": {...}` (même idée) |
| `npx` | `vendor/bin/...` |

Dans le projet, Composer tourne dans Docker : `make php-composer c="require ..."`.

Le `composer.json` de l'API PHP pur n'a **aucune dépendance** : c'est voulu, tout est écrit à la main.
Il ne sert qu'à une chose : l'**autoload**.

---

## 1.7 On n'importe pas des fichiers : namespaces et autoload

En JS, on importe un **fichier** :

```ts
import { TaskRepository } from './repository/TaskRepository'
```

En PHP, on référence une **classe** par son nom complet (son *namespace*), et l'autoloader trouve le fichier tout seul :

```php
<?php
declare(strict_types=1);

namespace App\Controller;              // ≈ « ce fichier appartient au module App/Controller »

use App\Repository\TaskRepository;     // ≈ import… mais d'un NOM de classe, pas d'un fichier

final class TaskController
{
    public function __construct(private TaskRepository $repository) {}
}
```

La règle **PSR-4**, déclarée dans `composer.json`, fait le lien entre nom et fichier :

```json
"autoload": { "psr-4": { "App\\": "src/" } }
```

| Nom de classe | Fichier chargé |
|---|---|
| `App\Controller\TaskController` | `src/Controller/TaskController.php` |
| `App\Repository\TaskRepository` | `src/Repository/TaskRepository.php` |

Conséquences :

- **une classe par fichier**, et le nom du fichier = le nom de la classe (casse comprise) ;
- le dossier doit correspondre au namespace ;
- `use` n'exécute rien et ne charge rien : le fichier n'est chargé qu'au **premier usage** de la classe ;
- pas d'`export` : toute classe d'un namespace est accessible depuis partout.

Le seul `require` du projet est celui de l'autoloader, en haut de `public/index.php` :
`require dirname(__DIR__) . '/vendor/autoload.php';`

---

## 1.8 Les PSR

Les **PSR** (*PHP Standards Recommendations*) sont des conventions communes à tout l'écosystème, un peu comme si les
grandes bibliothèques JS s'étaient mises d'accord sur un style et des interfaces :

| PSR | Sujet | Équivalent côté JS |
|---|---|---|
| PSR-1 / PSR-12 (PER Coding Style) | Style de code | une config Prettier / ESLint commune |
| PSR-4 | Autoload : namespace ↔ dossier | la résolution de modules |
| PSR-3 | Interface de logger | une API de logger commune |
| PSR-7 / PSR-15 | Requête / réponse HTTP, middlewares | les objets `Request` / `Response` de l'API Fetch |
| PSR-11 | Conteneur d'injection de dépendances | — (pas de standard en JS) |

Le code des squelettes suit PSR-12 : accolades des classes et méthodes à la ligne, 4 espaces, `declare(strict_types=1);` en tête.

---

## 1.9 L'écosystème

| Besoin | JS / TS | PHP |
|---|---|---|
| Framework web complet | Nuxt, NestJS, AdonisJS | **Symfony** (ce projet), Laravel |
| Micro-framework | Express, Fastify | Slim |
| ORM | Prisma, TypeORM | **Doctrine** (Symfony), Eloquent (Laravel) |
| Tests | Vitest, Jest | **PHPUnit**, Pest |
| Analyse statique | `tsc --noEmit`, ESLint | **PHPStan**, Psalm |
| Formatage | Prettier | PHP-CS-Fixer |
| Débogueur | DevTools du navigateur, `node --inspect` | **Xdebug** |
| Gestionnaire de paquets | npm, pnpm | Composer |
| Console interactive | `node` | `php -a` |

---

## 1.10 L'outillage du projet

| Commande | Ce qu'elle fait |
|---|---|
| `make up` / `make down` | Démarre / arrête tous les conteneurs |
| `make php-sh` | Shell dans le conteneur de l'API PHP pur |
| `make logs s=api-php` | Logs du serveur PHP (erreurs, `error_log()`) |
| `make php-composer c="dump-autoload"` | Composer, dans le conteneur |
| `make php-check` | Vérifie l'API contre le contrat |

Pour tester un bout de code PHP rapidement :

```bash
make php-sh
php -r 'var_dump(mb_strlen("Été"));'   # une ligne
php -a                                  # console interactive (Ctrl+D pour sortir)
```

### Déboguer avec Xdebug

En front, vous posez un point d'arrêt dans les DevTools. En PHP, le code s'exécute **dans le conteneur** :
c'est **Xdebug** qui se connecte à votre éditeur.

1. Démarrez avec Xdebug activé : `XDEBUG_MODE=debug make up`.
2. **VS Code** : installez l'extension *PHP Debug* (xdebug.php-debug). La configuration est fournie dans `.vscode/launch.json` :
   choisissez « Xdebug - API PHP pur » et lancez le débogage (F5).
3. Posez un point d'arrêt dans `apps/api-php/public/index.php`, puis appelez l'API (`curl` ou le front).
4. L'éditeur s'arrête sur la ligne : variables, pile d'appels, pas à pas, comme dans les DevTools.

Le lien entre les chemins est déjà configuré : `/app` dans le conteneur correspond à `apps/api-php` sur votre poste.
Revenez à `make up` (sans Xdebug) quand vous n'en avez plus besoin : Xdebug ralentit chaque requête.

> Sans débogueur, les réflexes `console.log` existent aussi : `error_log(print_r($variable, true));` puis `make logs s=api-php`.
> Évitez `var_dump()` dans une API : il écrit dans la réponse HTTP et casse le JSON.

---

## À retenir

1. **Une requête = un script exécuté de zéro.** Rien ne survit en mémoire entre deux requêtes.
2. **Pas d'`await`** : le code est synchrone, le parallélisme vient des workers PHP-FPM.
3. **Pas de build** : on modifie un fichier, la requête suivante l'exécute.
4. **Les types sont vérifiés à l'exécution**, et `declare(strict_types=1)` interdit les conversions implicites.
5. **Composer ≈ npm**, et l'autoload PSR-4 remplace les `import` de fichiers : namespace = dossier, une classe par fichier.
