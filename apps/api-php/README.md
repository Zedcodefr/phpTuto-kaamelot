# API TaskBoard — PHP pur (partie 1)

Le point de départ comprend :
- `public/index.php`, qui répond à `GET /api/health` (exercices 1 et 2, en mob programming) ;
- des **squelettes de classes** dans `src/`, répartis en **3 lots** que les trois devs back développent en parallèle.

Suivez [les exercices](../../docs/01-php/04-exercices-api-php-pur.md) (à votre rythme : si vous avancez vite, passez aux bonus).
L'IA est autorisée ; l'exigence est de pouvoir expliquer chaque ligne commitée en revue de lot ([charte IA](../../docs/00-charte-ia.md)).

## Les 3 lots

| Lot | Dev | Méthodes à écrire | Vérification |
|---|---|---|---|
| 🅰️ HTTP & routage | Dev Back 1 | `Request::fromGlobals()`, `JsonResponse::send()`, `HttpException::toResponse()`/`toArray()`, `ValidationException::toArray()`, `Router`, puis l'intégration dans `public/index.php` | `make php-check-a` |
| 🅱️ Données & PDO | Dev Back 2 | `Task::fromRow()`/`jsonSerialize()`, `Connection::fromEnvironment()`, `TaskRepository` | `make php-check-b` |
| 🅲 Métier & validation | Dev Back 3 | `Request::json()`, `TaskValidator`, `TaskController` | `make php-check-c` |

Règle d'or : on remplace les `throw new \LogicException('TODO ...')`, et **on ne modifie jamais** une signature, un constructeur ou `TaskRepositoryInterface`. Ce sont eux le contrat entre les lots.
Chaque vérification fonctionne **sans le code des deux autres lots** : le lot 🅲 utilise un faux repository en mémoire, les réponses du lot 🅲 sont inspectées sans être envoyées, et le lot 🅱️ s'exécute dans une transaction annulée.

## Commandes

| | |
|---|---|
| URL | http://localhost:8081 (ou via le front : http://localhost:5173/api/... avec `make use-php`) |
| Contrat à respecter | [docs/api-contract.md](../../docs/api-contract.md), partie 1 |
| Vérifier un lot | `make php-check-a` · `make php-check-b` · `make php-check-c` · `make php-check-lots` |
| Vérifier toute l'API (après intégration) | `make php-check` |
| Logs (erreurs PHP, `error_log()`) | `make logs s=api-php` |
| Shell dans le conteneur | `make php-sh` |
| Syntaxe | `make php-lint` |
| Réinitialiser la base | `make php-db-reset` |
| Nouveau dossier ou namespace | rien à faire : l'autoload PSR-4 `App\` → `src/` couvre tout |

## Arborescence

```
public/index.php                         front controller (ex. 1-2, puis intégration 🅰️)
src/
├── Http/
│   ├── Request.php                      fromGlobals() 🅰️ · json() 🅲
│   ├── JsonResponse.php                 send() 🅰️
│   ├── HttpException.php                toResponse() / toArray() 🅰️
│   ├── ValidationException.php          toArray() 🅰️
│   └── Router.php                       🅰️
├── Enum/TaskStatus.php                  fourni
├── Entity/Task.php                      fromRow() / jsonSerialize() 🅱️
├── Database/Connection.php              🅱️
├── Repository/
│   ├── TaskRepositoryInterface.php      fourni : le contrat entre 🅱️ et 🅲
│   └── TaskRepository.php               🅱️
├── Validation/TaskValidator.php         🅲
└── Controller/TaskController.php        🅲
database/schema.sql                      table et données de départ (fourni)
tools/
├── contract-check.php                   vérification HTTP complète (make php-check)
└── checks/
    ├── lot-a.php · lot-b.php · lot-c.php   vérifications par lot
    ├── harness.php                         mini-framework de vérification
    └── Fake/InMemoryTaskRepository.php     faux repository utilisé par le lot 🅲
```
