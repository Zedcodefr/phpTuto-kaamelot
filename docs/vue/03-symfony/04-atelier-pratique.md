# Atelier pratique Symfony

> Après le cours et la visite du projet, on passe au clavier : cinq petits exercices sur l'API Symfony existante,
> chacun sur une notion du cours. Pas de nouvelle table, pas de migration : Doctrine viendra à l'étape suivante.
> Chaque exercice a ses **tests fournis** : vous savez exactement quand c'est terminé.

## Règles du jeu

- **Seul ou en binôme**, sur votre branche `atelier/<prénom>`. **Elle n'est jamais mergée** : ces endpoints ne font pas
  partie du [contrat d'API](../../api-contract.md).
- Dans l'ordre : A0 → A5. A5 réutilise le service de A1.
- **Lisez le test avant d'écrire le code** (`apps/api-symfony/tests/Atelier/`) : c'est la spécification.
- Vérification : `make sf-atelier` (tout) ou `make sf-atelier c="--filter=A1"` (un exercice).
  Au départ, la plupart des tests sont rouges : c'est normal, c'est votre travail. Les quelques tests déjà verts
  vérifient que vous ne cassez rien.
- À la fin de chaque exercice : `make sf-test` et `make sf-check` doivent rester verts.
- L'IA est autorisée ([charte](../../00-charte-ia.md)). Chaque exercice se termine par des questions **à savoir expliquer
  sans elle** : c'est ce qui sera demandé au débrief.

```bash
git switch -c atelier/<prénom>
make up && make sf-atelier      # 16 tests, presque tous rouges
```

### Rappel : déboguer avec Xdebug

Un test reste rouge et vous ne comprenez pas pourquoi ? Posez un point d'arrêt plutôt que des `dump()`.

```bash
XDEBUG_MODE=debug make up   # active Xdebug (recrée les conteneurs PHP)
make up                     # le désactive : à faire dès que vous avez fini, Xdebug ralentit chaque requête
```

- **VS Code** (ou le devcontainer) : extension *PHP Debug*, configuration **« Xdebug - API Symfony »** (fournie dans
  `.vscode/launch.json`), F5, un point d'arrêt, puis `curl http://localhost:8082/api/tasks/stats`.
- **PhpStorm** : *Settings › PHP › Servers*, un serveur nommé **`taskboard-symfony`** (le nom compte : c'est celui
  qu'annonce le conteneur), host `localhost`, port `8082`, mapping `apps/api-symfony` → `/app`. Puis l'icône
  *Start Listening for PHP Debug Connections*.
- Les tests aussi s'arrêtent sur vos points d'arrêt quand l'éditeur écoute : `make sf-atelier c="--filter=A4"`.
- Une requête ne répond plus (`curl` bloqué, `make sf-check` affiche « Impossible de joindre ») ? L'éditeur est
  presque toujours arrêté sur un point d'arrêt : continuez l'exécution, ou repassez en `make up` sans Xdebug.

---

## A0 — Échauffement : faire parler Symfony

Avant d'écrire du code, trouvez ces réponses **avec les outils**, pas en lisant les fichiers.

| Question | Outil |
|---|---|
| Quelles routes existent, avec quelles méthodes HTTP ? Quelle est la contrainte sur `{id}` ? | `make sf-routes` |
| Quelle classe est injectée si je demande un `LoggerInterface` ? Un `EntityManagerInterface` ? | `make sf c="debug:autowiring Logger"` |
| Quels écouteurs tournent sur `kernel.request`, et dans quel ordre ? Où est le routing ? | `make sf c="debug:event-dispatcher kernel.request"` |
| Combien de requêtes SQL pour `GET /api/tasks` ? Et pour un `POST` invalide (titre vide) ? | Profiler : en-tête `X-Debug-Token-Link` de la réponse (`curl -i`) |
| Qui renvoie le 422 d'un `POST` invalide, avant même votre contrôleur ? | Profiler, onglets *Events* et *Validator* |

Rappel : [outils de diagnostic](02-particularites.md#11-outils-de-diagnostic).

---

## A1 — Une route, un service : `GET /api/tasks/stats`

**Notions** : routing par attribut, service et injection de dépendances (autowiring), injection dans une action.

```http
GET /api/tasks/stats
200 {"total": 4, "todo": 2, "in_progress": 1, "done": 1}
```

1. Créez `src/Service/TaskStats.php` avec une méthode `compute(): array` qui renvoie ce tableau (clés dans cet ordre).
   Elle reçoit `TaskRepository` dans son constructeur. Indices : `$repository->count([...])` est hérité ;
   `TaskStatus::cases()` liste les statuts.
2. **N'enregistrez le service nulle part.** Vérifiez qu'il est injectable : `make sf c="debug:autowiring TaskStats"`.
3. Dans `TaskController`, ajoutez l'action `stats()` sur la route `/stats`. Demandez `TaskStats` **en paramètre de
   l'action**, pas dans le constructeur.
4. `make sf-atelier c="--filter=A1"`.

**À savoir expliquer** : pourquoi `/api/tasks/stats` n'est-il pas pris pour `/api/tasks/{id}` ? Que se passerait-il
sans `requirements` sur `{id}` ? Où est enregistré `TaskStats` ? Combien de requêtes SQL fait votre `compute()`
(regardez le profiler) ?

---

## A2 — Une contrainte de validation maison : `#[NotShouting]`

**Notions** : Validator, contrainte + validateur, autoconfiguration, 422 automatique de `#[MapRequestPayload]`.

Règle : un titre d'**au moins 5 lettres** sans **aucune minuscule** est refusé, en `POST` comme en `PATCH`,
avec le message « Le titre ne doit pas être écrit entièrement en majuscules. ».
`URGENT URGENT` est refusé ; `API`, `Réunion IMPORTANTE` et `2026 : bilan` passent.

1. `src/Validator/NotShouting.php` : la contrainte, une classe qui étend `Constraint`, marquée `#[\Attribute]`,
   avec une propriété publique `$message`.
2. `src/Validator/NotShoutingValidator.php` : la logique, une classe qui étend `ConstraintValidator`. Symfony la
   trouve tout seul grâce au nom (`NotShouting` + `Validator`). Laissez passer `null` et `""` : `NotBlank` s'en charge.
   Indice : en regex Unicode, `\p{L}` = une lettre, `\p{Ll}` = une lettre minuscule (pensez aux accents).
3. Ajoutez `#[NotShouting]` sur `title` dans `CreateTaskInput` et `UpdateTaskInput`.
4. `make sf-atelier c="--filter=A2"`, puis essayez à la main avec `curl` et regardez le JSON des `violations`.

Rappel : [Validator](02-particularites.md#7-validator).

**À savoir expliquer** : pourquoi deux classes et pas une ? Qui appelle votre validateur, et à quel moment du cycle
de la requête ? Pourquoi votre contrôleur n'a-t-il pas changé ? Où auriez-vous mis ce code en PHP pur ?

---

## A3 — Un écouteur d'événements : `X-Response-Time`

**Notions** : événements du kernel, `#[AsEventListener]`, priorités, requête principale.

Toute réponse dont le chemin commence par `/api` porte l'en-tête `X-Response-Time` : la durée de traitement en
millisecondes, nombre entier. **Y compris les erreurs** (404, 422). Rien en dehors de `/api`.

1. `src/EventListener/ResponseTimeListener.php` : une classe, deux méthodes marquées `#[AsEventListener]`.
   - sur `kernel.request` : notez l'heure de départ (`hrtime(true)`, en nanosecondes) dans les attributs de la requête ;
   - sur `kernel.response` : calculez la durée et posez l'en-tête.
2. Choisissez la **priorité** de l'écouteur `kernel.request` : il doit passer **avant le routing**. Sinon, que se
   passe-t-il pour une 404 ? Comparez avec `make sf c="debug:event-dispatcher kernel.request"`.
3. Ignorez les sous-requêtes (`$event->isMainRequest()`).
4. `make sf-atelier c="--filter=A3"`, puis `curl -i http://localhost:8082/api/tasks`.

Rappel : [le cycle de vie d'une requête](01-principes.md#3-le-cycle-de-vie-dune-requête).

**À savoir expliquer** : avec un serveur Node / Express, à quoi ressemblerait ce code (indice : un middleware) ? Pourquoi l'en-tête est-il présent sur une 404
alors que votre contrôleur n'a jamais été appelé ? Que veut dire « priorité 4096 » ?

---

## A4 — Une règle métier et son code HTTP : `POST /api/tasks/{id}/complete`

**Notions** : méthode métier dans l'entité, exception métier, `#[WithHttpStatus]`, `EntityValueResolver`.

```http
POST /api/tasks/5/complete   → 200 + la tâche, "status": "done"
POST /api/tasks/5/complete   → 409 (déjà terminée), JSON d'erreur habituel
POST /api/tasks/999/complete → 404
GET  /api/tasks/5/complete   → 405
```

1. `src/Exception/TaskAlreadyDone.php` : une exception métier (étend `\DomainException`). Elle ne connaît pas HTTP.
2. Dans l'entité `Task`, une méthode `complete()` qui lève cette exception si la tâche est déjà `done`.
   La règle est dans l'entité, pas dans le contrôleur.
3. Dans `TaskController`, l'action `complete(Task $task)` : appel de la méthode, `flush()`, la tâche en JSON.
4. Lancez le test : vous obtenez une **500**. Faites-en une **409** sans `try/catch` dans le contrôleur.
   Indice : un attribut sur la classe d'exception (`Symfony\Component\HttpKernel\Attribute\WithHttpStatus`).
5. `make sf-atelier c="--filter=A4"`.

**À savoir expliquer** : pourquoi la règle dans l'entité plutôt que dans le contrôleur ? Pourquoi pas de `persist()`
avant `flush()` ? Pourquoi `POST .../complete` et pas `PATCH {"status": "done"}` ? D'où vient la 404 ?

---

## A5 — Une commande console : `app:tasks:report`

**Notions** : `#[AsCommand]`, commande invokable, options, codes de sortie, réutiliser un service.

```bash
make sf c="app:tasks:report"                  # tableau : statut / nombre
make sf c="app:tasks:report --format=json"    # {"total":4,"todo":2,"in_progress":1,"done":1}
make sf c="app:tasks:report --format=xml"     # erreur, code de sortie 2
```

1. `src/Command/TaskReportCommand.php` : une classe marquée `#[AsCommand(name: 'app:tasks:report')]` avec une méthode
   `__invoke()`. Elle reçoit `TaskStats` (A1) dans son constructeur.
2. L'option : un paramètre `#[Option] string $format = 'table'` de `__invoke()`. Pour l'affichage : `SymfonyStyle`
   (`$io->table()`, `$io->error()`), à demander aussi en paramètre de `__invoke()`.
3. Codes de sortie : `Command::SUCCESS`, et `Command::INVALID` pour un format inconnu.
4. `make sf-atelier`. Tout est vert ? Bravo.

**À savoir expliquer** : la commande et le contrôleur appellent le même service : qu'est-ce qui change entre les deux ?
Où est enregistrée la commande ? Quel équivalent côté front (indice : les scripts de `package.json`) ?

---

## Bonus (si vous avez fini)

- **B1 — Une seule requête** : réécrivez `TaskStats::compute()` avec un seul `SELECT ... GROUP BY` (QueryBuilder,
  `COUNT`). Vérifiez dans le profiler. Les tests A1 et A5 doivent rester verts.
- **B2 — Requêtes lentes** : dans `ResponseTimeListener`, écrivez un avertissement dans les logs
  (`LoggerInterface`) quand une requête dépasse un seuil lu dans `.env` (`SLOW_REQUEST_MS=500`), injecté avec
  `#[Autowire('%env(int:SLOW_REQUEST_MS)%')]`. Où apparaît le message ? (`var/log/dev.log`)
- **B3 — Un test unitaire** : testez `NotShoutingValidator` sans HTTP (`ConstraintValidatorTestCase`), ou
  `TaskStats` avec `KernelTestCase`. Lequel est le plus rapide, et pourquoi ?

## Débrief

Préparez une réponse courte à chacune, sur **votre** code :

1. Sur les cinq exercices, combien de lignes de configuration avez-vous écrites ? Pourquoi ?
2. Retracez le chemin d'un `POST /api/tasks` avec un titre en majuscules : quels événements, quels objets, qui
   renvoie la 422 ?
3. Un même besoin (les statistiques) est exposé en HTTP et en CLI : où est la logique, et pourquoi pas ailleurs ?
4. Qu'est-ce qui, dans cet atelier, aurait demandé le plus de code dans votre API PHP pur ?
