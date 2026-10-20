# 1. Principes de PHP

> Objectif : comprendre **comment PHP fonctionne** avant d'écrire la moindre ligne.
> La plupart des incompréhensions d'un développeur .NET viennent du modèle d'exécution, pas de la syntaxe.

## 1.1 PHP en 2026 : oubliez vos préjugés

Si votre dernière image de PHP date de PHP 5 (`mysql_query`, HTML et SQL mélangés), elle est obsolète.

| | PHP 5 (2004-2015) | PHP 8.4 (celui du projet) |
|---|---|---|
| Typage | Aucun ou presque | Types scalaires, nullables, unions, intersections, `never`, `mixed`... + mode strict |
| POO | Basique | Classes, interfaces, traits, enums, `readonly`, property hooks, visibilité asymétrique |
| Performances | Lent | ~3 à 4× plus rapide que PHP 5.6 (moteur réécrit en PHP 7) + OPcache + JIT |
| Outillage | Peu | Composer, PHPStan, PHPUnit, Xdebug, PhpStorm... |
| Frameworks | Code « maison » | Symfony, Laravel : matures, comparables à ASP.NET Core |

Le PHP moderne, écrit proprement, **ressemble beaucoup à du C# sans génériques**. Comparez :

```csharp
public sealed class Task
{
    public Task(string title, TaskStatus status = TaskStatus.Todo) { Title = title; Status = status; }
    public string Title { get; }
    public TaskStatus Status { get; private set; }
}
```

```php
final class Task
{
    public function __construct(
        public readonly string $title,
        public private(set) TaskStatus $status = TaskStatus::Todo,
    ) {}
}
```

## 1.2 Le modèle d'exécution « share nothing »

C'est **LA** différence fondamentale avec .NET.

### En ASP.NET Core

Kestrel est un **processus qui vit longtemps**. Au démarrage, `Program.cs` s'exécute une fois : le conteneur DI est construit, les singletons créés, les caches remplis. Ensuite, chaque requête est traitée par un thread du pool, **dans le même processus** : les objets `static` et les singletons sont partagés entre toutes les requêtes.

### En PHP

```
Navigateur ──HTTP──▶ nginx ──FastCGI──▶ PHP-FPM (pool de workers)
                                          │
                                          ▼  pour CHAQUE requête :
                                  1. un worker libre prend la requête
                                  2. exécute public/index.php DEPUIS ZÉRO
                                     (autoload, création des objets, connexion BDD...)
                                  3. produit la réponse (echo, header())
                                  4. TOUT est détruit : variables, objets, connexions
                                  5. le worker attend la requête suivante
```

- **PHP-FPM** (FastCGI Process Manager) maintient un pool de processus workers. Un worker traite **une requête à la fois**.
- Le serveur web (nginx, Apache) sert les fichiers statiques et transmet le reste à FPM. C'est le couple « IIS / reverse proxy + Kestrel ».
- Dans ce projet : l'API PHP pur utilise le **serveur intégré** `php -S` (pratique pour apprendre, jamais en production), l'API Symfony utilise **nginx + PHP-FPM** (architecture réelle). Voir [compose.yaml](../../compose.yaml).

### Conséquences pratiques

| Situation | .NET | PHP |
|---|---|---|
| Variable `static` | Partagée par toutes les requêtes (attention à la concurrence) | Vit le temps d'**une** requête |
| Singleton DI | Un seul pour toute l'application | Un par requête (le conteneur est reconstruit à chaque requête) |
| Cache en mémoire | `IMemoryCache` | Impossible en mémoire PHP → APCu, Redis, fichiers |
| Fuite mémoire | Dangereuse (le processus vit des jours) | Quasi sans conséquence (tout est libéré en fin de requête) |
| Concurrence / verrous | `lock`, `ConcurrentDictionary`... | Inutile dans le code : les requêtes sont isolées (la concurrence est gérée par la BDD) |
| `async`/`await` | Partout | N'existe pas dans le langage. Une requête = un fil d'exécution synchrone. On scale en multipliant les workers |
| Plantage | Une exception non gérée peut tuer le processus | Ne touche que la requête en cours |
| Démarrage | Coûteux mais une seule fois | « Coûteux » à chaque requête → d'où OPcache et le cache compilé de Symfony |

> 💡 Il existe des runtimes PHP « long-running » (FrankenPHP en mode worker, RoadRunner, Swoole) qui gardent l'application en mémoire, comme Kestrel. C'est un sujet avancé, hors de cette formation.

## 1.3 Interprété ? Compilé ? Les deux, à la volée

| Étape | .NET | PHP |
|---|---|---|
| Compilation | `dotnet build` → IL dans une DLL, **avant** l'exécution | Aucune étape de build : le fichier `.php` est la source déployée |
| Code intermédiaire | IL (CIL) | Opcodes (bytecode de la Zend VM), produits à la volée |
| Cache du code compilé | La DLL elle-même | **OPcache** : garde les opcodes en mémoire partagée entre les workers |
| JIT | RyuJIT, systématique | JIT optionnel (PHP 8+), utile surtout pour du calcul ; peu d'impact sur une API web |
| Erreurs de typage | À la compilation | À l'exécution (ou avant, avec un **analyseur statique** : PHPStan) |

Conséquence : **une erreur de type dans une branche jamais exécutée ne sera jamais détectée** sans outil d'analyse statique. C'est pour cela que PHPStan (≈ les analyseurs Roslyn, en plus strict) est indispensable dans un vrai projet.

`make php-lint` vérifie seulement la **syntaxe** (`php -l`), pas les types.

## 1.4 Typage : dynamique par défaut, strict par choix

PHP est historiquement **dynamiquement et faiblement typé** : une variable n'a pas de type, sa valeur en a un, et PHP convertit à la volée (« type juggling »).

```php
function double(int $n): int { return $n * 2; }

echo double("21");   // sans strict_types : 42 (la chaîne "21" est convertie en int)
```

Avec `declare(strict_types=1);` en **première instruction du fichier** :

```php
<?php
declare(strict_types=1);

echo double("21");   // TypeError: double(): Argument #1 ($n) must be of type int, string given
```

| | C# | PHP sans strict_types | PHP avec strict_types |
|---|---|---|---|
| Vérification | Compilation | Exécution, avec conversions | Exécution, sans conversion (sauf int → float) |
| Variables locales | Typées (`var` inféré) | Jamais typées | Jamais typées |
| Paramètres, retours, propriétés | Typés | Typables | Typables |

**Règle du projet** : `declare(strict_types=1);` en tête de **tous** les fichiers, et tout ce qui peut être typé l'est.

⚠️ `strict_types` s'applique aux appels faits **depuis** le fichier qui le déclare. C'est par fichier, pas global.

## 1.5 Composer ≈ NuGet (+ l'autoload)

| .NET | PHP / Composer |
|---|---|
| `MyApp.csproj` (`<PackageReference>`) | `composer.json` (`require`, `require-dev`) |
| `packages.lock.json` | `composer.lock` — **à committer** (versions exactes installées) |
| `dotnet restore` | `composer install` (installe depuis le `.lock`) |
| `dotnet add package X` | `composer require vendor/x` |
| Mise à jour | `composer update` (recalcule le `.lock`) |
| `~/.nuget/packages` | `vendor/` dans le projet — **jamais committé** |
| nuget.org | [packagist.org](https://packagist.org) |
| `dotnet tool` / scripts MSBuild | section `scripts` de `composer.json` |

### L'autoload, ou « comment PHP trouve les classes »

En C#, le compilateur connaît toutes les classes des assemblies référencées. En PHP, **il n'y a pas d'assembly** : quand le code utilise `App\Http\Router`, PHP doit trouver et inclure le fichier qui le définit. C'est le rôle de l'**autoloader**.

Le standard **PSR-4** fait correspondre namespace et dossier :

```json
// apps/api-php/composer.json
"autoload": {
    "psr-4": { "App\\": "src/" }
}
```

```
App\Http\Router          →  src/Http/Router.php
App\Repository\TaskRepository → src/Repository/TaskRepository.php
```

Il suffit d'inclure une fois `vendor/autoload.php` (généré par Composer) dans le front controller. Contrairement à C#, **le nom du fichier et l'arborescence sont obligatoirement alignés** sur le namespace et le nom de la classe (une classe par fichier).

Dans ce projet, vous n'appelez jamais `composer` directement : `make php-composer c="dump-autoload"`.

## 1.6 Les PSR : les conventions communes

Le [PHP-FIG](https://www.php-fig.org/psr/) publie des recommandations (PSR) que tout l'écosystème suit. Les plus utiles :

| PSR | Sujet | Équivalent .NET |
|---|---|---|
| PSR-1 / **PSR-12** (→ PER Coding Style) | Style de code : PascalCase pour les classes, camelCase pour méthodes et propriétés, accolades... | Conventions de nommage .NET / `.editorconfig` |
| **PSR-4** | Autoload namespace → dossier | Résolution des assemblies |
| PSR-3 | Interface de logger (`LoggerInterface`) | `ILogger` |
| PSR-7 / PSR-15 | Requête/réponse HTTP, middlewares | `HttpContext`, middlewares ASP.NET |
| PSR-11 | Interface de conteneur DI | `IServiceProvider` |
| PSR-14 | Événements | — |

Différence de style notable : en PHP, **les méthodes sont en camelCase** (`findAll()`, pas `FindAll()`).

## 1.7 L'écosystème en un tableau

| Besoin | .NET | PHP |
|---|---|---|
| Framework web complet | ASP.NET Core | **Symfony** (partie 2), Laravel |
| ORM | Entity Framework Core | **Doctrine** (avec Symfony), Eloquent (Laravel) |
| Tests | xUnit / NUnit | **PHPUnit**, Pest |
| Analyse statique | Analyseurs Roslyn, nullable reference types | **PHPStan**, Psalm |
| Formatage | `dotnet format` | PHP-CS-Fixer, PHP_CodeSniffer |
| Debugger | Visual Studio / Rider | **Xdebug** + PhpStorm / VS Code |
| IDE | Rider, Visual Studio | **PhpStorm** (même éditeur que Rider : JetBrains), VS Code + Intelephense |

## 1.8 L'outillage du projet

Personne n'installe PHP ni Composer sur son poste : tout tourne dans Docker, piloté par le `Makefile`.

```bash
make up              # démarre les conteneurs
make php-sh          # shell dans le conteneur : php -v, php -a (REPL interactif), composer...
make logs s=api-php  # logs du serveur PHP (les erreurs et vos error_log() y apparaissent)
```

Le dossier `apps/api-php` de votre poste est **monté** dans le conteneur en `/app` : vous éditez sur votre machine, c'est immédiatement pris en compte (pas de build, pas de redémarrage).

Tester un bout de code rapidement :

```bash
make php-sh
php -r 'var_dump(array_map(fn($x) => $x * 2, [1, 2, 3]));'
php -a     # REPL interactif (≈ C# Interactive / dotnet-script)
```

### Déboguer pas à pas avec Xdebug

Xdebug est installé mais désactivé par défaut (il ralentit tout). Pour l'activer :

```bash
XDEBUG_MODE=debug make up
```

Puis, dans l'IDE, écouter les connexions de débogage sur le **port 9003**, avec le **mapping de chemins** `apps/api-php` (votre poste) ↔ `/app` (conteneur).

**PhpStorm**

1. *Settings → PHP → Servers* : ajouter un serveur nommé `localhost`, port `8081`, cocher *Use path mappings*, associer `apps/api-php` à `/app`.
2. Cliquer sur l'icône téléphone *Start Listening for PHP Debug Connections*.
3. Poser un point d'arrêt, lancer `curl http://localhost:8081/api/tasks` : PhpStorm s'arrête.

**VS Code** (extension *PHP Debug* de Xdebug) — `.vscode/launch.json` :

```json
{
  "version": "0.2.0",
  "configurations": [
    {
      "name": "Xdebug (Docker)",
      "type": "php",
      "request": "launch",
      "port": 9003,
      "pathMappings": { "/app": "${workspaceFolder}/apps/api-php" }
    }
  ]
}
```

Pour revenir à la normale : `make up` (sans la variable).

### Le débogage « à l'ancienne », toujours utile

```php
var_dump($task);           // affiche type + valeur (≈ Console.WriteLine + débogueur)
error_log(print_r($data, true));  // écrit dans les logs (make logs s=api-php) sans casser le JSON
```

⚠️ Un `var_dump` dans une API JSON casse la réponse : préférez `error_log()`.

---

➡️ Suite : [2. Grammaire du langage](02-grammaire.md)
