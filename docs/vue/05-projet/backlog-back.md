# Étape 5 — Projet : le backlog back (Symfony)

> **La source de vérité est [docs/api-contract.md](../../api-contract.md)** (parties 2A, 2B, 2C). Ce backlog découpe
> ce contrat en tickets et vous guide techniquement. Si un ticket et le contrat se contredisent, le contrat gagne
> (et signalez-le au formateur).

| Dev | Périmètre | Notions dominantes |
|---|---|---|
| **Dev Back 1** | 2A — Projets | Relations Doctrine, QueryBuilder, sérialisation de relations, 409, UniqueEntity |
| **Dev Back 2** | 2B — Utilisateurs & authentification | SecurityBundle, password hasher, authenticator `access_token`, voters, fixtures |
| **Dev Back 3** | 2C — Commentaires, filtres, pagination, suivi | `MapQueryString`, Paginator, listeners Doctrine, relations |

> **Prérequis : avoir terminé le [kata Doctrine & DQL](../04-doctrine/04-kata-doctrine.md)** (`make sf-kata` vert, oral passé).
> Presque chaque ticket réutilise une notion du kata : elle est indiquée dans le ticket (« ↩︎ Kata K06 »…).
> Les trois devs travaillent **en parallèle** : mise en place (dès la fin du kata), réalisation, puis intégration
> et démo / oraux / rétro.
>
> **À votre rythme** : le backlog avance au rythme de votre compréhension. Si vous avancez vite, enchaînez sur les bonus.
> Ce qui compte, c'est de **comprendre** ce que vous livrez.
>
> Chaque ticket est étiqueté **🟢 Socle** (obligatoire, à livrer pendant la formation) ou **🔵 Bonus** (une fois le
> socle terminé). Le socle est pensé pour être réalisé **avec l'IA**. Le [contrat d'API](../../api-contract.md) ne change pas :
> les bonus non réalisés restent des parties du contrat **non encore implémentées**, à terminer après la formation —
> c'est une bonne première tâche sur le « vrai » projet.

> **Repères pour un dev Vue** : vous avez sûrement déjà consommé des API comme celle-ci. Ce backlog, c'est l'autre côté.
> Chaque ticket se termine par des `curl` : ce sont exactement les appels que ferait un front Vue
> (`fetch('/api/projects', { method: 'POST', body: JSON.stringify(...) })`). Pensez à ce que ce front devrait
> afficher pour chaque code HTTP que vous renvoyez : c'est votre meilleur test de qualité de l'API.
> Pour vivre les deux côtés, voir le [bonus front Vue](bonus-front-vue.md).

Sommaire

- [Travailler à 3 sans se marcher dessus](#travailler-à-3-sans-se-marcher-dessus) — propriété des fichiers, fichiers partagés, ordre de passage
- [Socle, bonus et déroulé](#socle-bonus-et-déroulé) — ce qui est obligatoire, déroulé ordonné par dev
- [Règles communes](#règles-communes) — workflow git, migrations, fixtures, IA, définition of done
- [Dev Back 1 — Projets](#dev-back-1--projets)
- [Dev Back 2 — Utilisateurs & authentification](#dev-back-2--utilisateurs--authentification)
- [Dev Back 3 — Commentaires, filtres & pagination](#dev-back-3--commentaires-filtres--pagination)
- [Tickets transverses](#tickets-transverses)
- [Carte des dépendances](#carte-des-dépendances)

---

## Travailler à 3 sans se marcher dessus

Le principe : **chaque dev possède ses fichiers**. On peut lire ceux des autres, on ne les modifie pas sans le dire.
Les quelques fichiers partagés suivent des règles strictes (plus bas). Résultat attendu : quasiment aucun conflit git.

### Propriété des fichiers

| | Dev Back 1 — Projets | Dev Back 2 — Utilisateurs & auth | Dev Back 3 — Commentaires & filtres |
|---|---|---|---|
| Entités | `src/Entity/Project.php` | `src/Entity/User.php`, `src/Entity/ApiToken.php` | `src/Entity/Comment.php` |
| Repositories | `ProjectRepository.php` | `UserRepository.php`, `ApiTokenRepository.php` | `CommentRepository.php` |
| Contrôleurs | `ProjectController.php` | `AuthController.php`, `UserController.php` | `CommentController.php` |
| DTO | `Dto/*Project*.php` | `Dto/RegisterInput.php`, `Dto/LoginInput.php` | `Dto/TaskFilter.php`, `Dto/*Comment*.php` |
| Validation | `Validator/UniqueProjectName*`, `Validator/ExistingProject*` | `Validator/ExistingUser*` | — |
| Autres | — | `src/Security/*` (handler, `TaskVoter`), `config/packages/security.yaml` | `src/EventListener/*`, `src/Security/CommentVoter.php` |
| Fixtures | `DataFixtures/ProjectFixtures.php` | `DataFixtures/UserFixtures.php` | `DataFixtures/CommentFixtures.php` |
| Tests | `tests/Controller/ProjectControllerTest.php` | `tests/Controller/AuthControllerTest.php`, `UserControllerTest.php`, `tests/Security/*` | `tests/Controller/CommentControllerTest.php`, `TaskFilterTest.php` |

Chacun lance d'abord **ses** tests, plus rapides : `make sf-test c="tests/Controller/ProjectControllerTest.php"`,
puis toute la suite (`make sf-test`) avant d'ouvrir la PR.

### Fichiers partagés : règles

Fichiers concernés : `src/Entity/Task.php`, `src/Controller/TaskController.php`, `src/Dto/CreateTaskInput.php`,
`src/Dto/UpdateTaskInput.php`, `src/Repository/TaskRepository.php`, `src/DataFixtures/AppFixtures.php`, `migrations/`.

1. **Modifications additives et petites** : on ajoute un champ, une méthode, un paramètre. On ne reformate pas, on ne réordonne pas, on ne renomme pas ce qui existe.
2. **Une PR courte et dédiée** pour la partie qui touche un fichier partagé (ex. `B1-3a — Task.project`), séparée du reste du ticket, revue et mergée vite pour ne bloquer personne.
3. **Ordre de passage sur `Task`** (une seule migration de `task` à la fois) :

   | Ordre | PR | Ce qui change sur `Task` | Quand |
   |---|---|---|---|
   | 1 | B1-3 🟢 (Dev Back 1) | `project` (ManyToOne) + `projectId` dans les DTO + décision de sérialisation | dès que B1-2 est fini (milieu de la réalisation) |
   | 2 | B2-4a 🔵 (Dev Back 2) | `author`, `assignee` (ManyToOne vers User) + `assigneeId` | si le socle de B2 est fini, après rebase sur 1 |
   | 3 | B3-3 🔵 (Dev Back 3) | `updatedAt`, `completedAt` (+ `commentCount`, bonus de B3-2 : valeur calculée, sans migration sur `task`) | bonus, après rebase sur 2 |
   | 4 | B2-4b 🟢 (Dev Back 2) | `access_control` : écritures authentifiées + adaptation de **tous** les tests et de `contract-check` | **en dernier**, au début de l'intégration |
   | 5 | B2-5 🔵 (Dev Back 2) | aucune colonne : voter sur `DELETE /api/tasks/{id}` + adaptation des tests qui suppriment une tâche | bonus, **après** B2-4b |

   B2-4 est donc coupé en deux PR : les relations (B2-4a) passent tôt, la protection des écritures (B2-4b) passe
   en dernier, car elle casse les tests des autres tant qu'ils n'envoient pas de token (trait `LogsInUsers`, voir B2-4 étape 5).

   > ⚠️ **B2-5 (voter) casse lui aussi les tests des autres**, même une fois B2-4b passé : une tâche **sans auteur**
   > ne peut être supprimée que par un admin. Tout test qui supprime, connecté en non-admin, une tâche créée directement
   > en base (donc sans auteur) passe de 204 à 403 : `testDeleteTask` de la partie 1, test de suppression en cascade des
   > commentaires de B3… Dans la PR de B2-5, Dev Back 2 adapte ces tests en prévenant leurs propriétaires : se connecter
   > en `ROLE_ADMIN`, ou créer la tâche par un `POST` authentifié pour qu'elle ait un auteur.
4. **Décision de sérialisation** (groupes ou DTO de sortie) prise ensemble au point d'équipe de fin de mise en place, proposée par Dev Back 1. Tout le monde s'y tient.

### Fixtures

Une classe par domaine (`ProjectFixtures`, `UserFixtures`, `CommentFixtures`) ; `AppFixtures` (les tâches) n'est modifiée
que pour rattacher les tâches à un projet / un auteur, via les références :

```php
// ProjectFixtures
$this->addReference('project-php', $project);

// AppFixtures implements DependentFixtureInterface
public function getDependencies(): array { return [ProjectFixtures::class, UserFixtures::class]; }
$task->moveToProject($this->getReference('project-php', Project::class)); // méthode métier créée en B1-3
```

Ne touchez jamais à `KataFixtures` : les tests du kata en dépendent.

### Migrations

- **Une migration par ticket**, avec une `getDescription()` explicite.
- Toujours : `git pull --rebase` → `make sf-db-reset` → **puis** `make sf-migration`. Sinon votre migration contient (ou annule) celles des autres.
- **Ne jamais éditer une migration mergée.** Une erreur ? Nouvelle migration corrective.
- **Conflit ?** Supprimez votre migration locale, rebasez, `make sf-db-reset`, régénérez-la. Ça prend 30 secondes.

### Avancer en parallèle malgré les dépendances

- **Pendant la mise en place, chacun crée ses entités et ses migrations** (`Project`, `User`, `Comment`) : aucune ne touche `Task`, donc aucun conflit. Les migrations sont mergées dans l'ordre B1 → B2 → B3 au point d'équipe.
- **Dev Back 3 enchaîne ensuite par B3-1** (filtres et pagination sur `GET /api/tasks`), qui ne dépend de personne ; le filtre `projectId` s'ajoute après B1-3.
- **Dev Back 3 crée `Comment.author` nullable** dès la mise en place : il sera renseigné quand B2 aura livré l'authentification (bonus B3-4).
- **Dev Back 2 démarre par l'entité `User`** et le login, qui ne touchent pas `Task`.
- **Le front fourni** (`make use-symfony`, http://localhost:5173) ne connaît que la partie Tâches du contrat : vérifiez vos endpoints avec `curl`, vos tests et le profiler. Ceux qui font le [bonus front Vue](bonus-front-vue.md) consomment vos endpoints au fil des merges.

---

## Socle, bonus et déroulé

Le socle est pensé pour être réalisé **avec l'IA**, revues de code comprises. Un dev qui a terminé aide en revue
ou attaque un bonus ; un dev bloqué le dit tout de suite : on réduit le périmètre plutôt que de s'enliser.

### Ce qui est obligatoire

| Dev | 🟢 Socle | 🔵 Bonus |
|---|---|---|
| **Dev Back 1 — Projets** | B1-1 entité `Project` + fixtures · B1-2 CRUD `/api/projects` · B1-3 relation `Task.project` + `projectId` (étapes 1-4 et 7) · 2-3 tests | B1-3 étapes 5-6 (PATCH `projectId: null`, `taskCount`) · B1-4 (`/projects/{id}/tasks`, 409) · T-1 |
| **Dev Back 2 — Utilisateurs & auth** | B2-1 entité `User` + hash + fixtures · B2-2 `register` · B2-3 `login`, tokens, `/api/me` · B2-4b protection des écritures + adaptation des tests et de `make sf-check` | `GET /api/users` (B2-2) · B2-4a `author`/`assignee` · B2-5 voter · T-1 |
| **Dev Back 3 — Commentaires & filtres** | B3-2 entité `Comment` (mise en place) · B3-1 filtres + pagination `X-Total-Count` · B3-2 `GET`/`POST /api/tasks/{id}/comments` · 2-3 tests | `commentCount`, `DELETE /api/comments/{id}` (B3-2) · B3-3 `updatedAt`/`completedAt` · B3-4 droits (403) · T-1 |

### Déroulé ordonné

| Ordre | Dev Back 1 — Projets | Dev Back 2 — Utilisateurs & auth | Dev Back 3 — Commentaires & filtres |
|---|---|---|---|
| **1. Mise en place** | B1-1 entité `Project`, migration, fixtures | B2-1 entité `User`, hash, fixtures | B3-2 (étapes 1-2) entité `Comment`, migration |
| **2. Point d'équipe** | Merge des 3 migrations (B1 → B2 → B3), décision de sérialisation, relecture de l'ordre de passage sur `Task` | | |
| **3. Premiers endpoints** | B1-2 CRUD projets | B2-2 `register` · début B2-3 | B3-1 filtres + pagination |
| **4. Relations** | B1-3 relation `Task.project` — **PR partagée n°1** | B2-3 `login`, tokens, `/api/me` | B3-1 (fin) · début B3-2 endpoints |
| **5. Finalisation** | B1-3 (fin, merge) · tests · bonus | B2-4b protection des écritures, adaptation des tests et de `sf-check` | B3-2 `GET`/`POST` commentaires · filtre `projectId` une fois B1-3 mergé |
| **6. Avant intégration** | Bonus ou revue des PR des autres | B2-4b prête, **mergée en dernier** | Tests · bonus |
| **7. Intégration** | `make sf-db-reset`, `make sf-test`, `make sf-check`, front fourni branché (`make use-symfony`) | | |

Un bloc terminé et compris ? Passez au suivant ou à un bonus.

| Ticket | Statut | ↩︎ Kata / cours Doctrine |
|---|---|---|
| B1-1 | 🟢 Socle | Mapping, migrations : [ORM et mapping](../04-doctrine/01-orm-et-mapping.md) |
| B1-2 | 🟢 Socle | `findBy`, tri ; K01 |
| B1-3 | 🟢 Socle (étapes 1-4, 7) · 🔵 étapes 5-6 | Owning / inverse side (K20), fetch join anti N+1 (K06), `COUNT` (K05, K11) : [Relations](../04-doctrine/02-relations.md) |
| B1-4 | 🔵 Bonus | Jointure + paramètre (K02, K08) |
| B2-1 | 🟢 Socle | Mapping, fixtures avec dépendances |
| B2-2 | 🟢 Socle (`register`) · 🔵 `GET /api/users` | Unicité, `findOneBy` |
| B2-3 | 🟢 Socle | Relation `ApiToken` → `User`, requête avec date (K03) |
| B2-4b | 🟢 Socle | — (Security, tests) |
| B2-4a | 🔵 Bonus | ManyToOne nullable, persist/flush (K17, K18) |
| B2-5 | 🔵 Bonus | — (Security) |
| B3-1 | 🟢 Socle | Requête dynamique (K09), Paginator (K10) : [DQL et QueryBuilder](../04-doctrine/03-dql-et-query-builder.md) |
| B3-2 | 🟢 Socle (entité, `GET`, `POST`) · 🔵 `DELETE`, `commentCount` | OneToMany, `orphanRemoval` (K21), `COUNT` (K05) |
| B3-3 | 🔵 Bonus | Lifecycle callbacks / listeners : [ORM et mapping](../04-doctrine/01-orm-et-mapping.md) |
| B3-4 | 🔵 Bonus | — (Security, après B2-5) |
| T-1 | 🔵 Bonus | — |

---

## Règles communes

### Workflow git

- Une branche par ticket : `feature/B1-1-project-entity`, `feature/B2-3-login`, `feature/B3-2-comment-endpoints`…
- Petites PR (un ticket = une PR), titre `B1-1 — Entité Project`, description : ce qui change dans le contrat, comment tester (les `curl`).
- **Revue croisée obligatoire** : B1 relit B2, B2 relit B3, B3 relit B1. Une revue de PR de socle se fait sans attendre : c'est ce qui évite de bloquer les autres. Le relecteur lance réellement `make sf-test` et au moins un `curl` sur la branche.
- `git pull --rebase` (ou rebase sur `main`) avant d'ouvrir la PR. Merge quand : CI locale verte (voir DoD) + 1 approbation.
- Indiquez dans la PR les `curl` qui prouvent que l'endpoint est utilisable : c'est ce que liront les autres (et votre futur front).

### Conventions migrations

`Task` est modifiée par les trois devs (`project`, `author`/`assignee`, `updatedAt`/`completedAt`). `commentCount` n'en fait pas partie :
c'est une valeur calculée, pas une colonne (aucune migration). Les migrations vont donc se croiser. Règles :

1. **Une migration par ticket**, générée par `make sf-migration`, relue, renommée dans `getDescription()`.
2. **Rebase sur `main` + `make sf-db-reset` AVANT de générer** sa migration : sinon le diff contient les changements des autres (ou les annule).
3. **Ne jamais modifier une migration déjà mergée** sur `main`. Une erreur ? Nouvelle migration corrective.
4. Si deux PR mergées génèrent des migrations en conflit, la seconde à merger régénère la sienne (supprimer la sienne → rebase → `make sf-db-reset` → `make sf-migration`).
5. Toute colonne `NOT NULL` ajoutée sur une table existante doit avoir une valeur par défaut ou être `nullable` (voir l'exercice `priority`).

> ⚠️ **Pas de snapshot comme en EF Core** : `make sf-migration` (`make:migration`) compare votre mapping (toutes les
> entités présentes dans `src/`) à la **base réelle**, pas à la dernière migration. La migration générée embarque donc
> **tout ce qui n'est pas encore migré** : une entité d'un autre ticket commencée sur votre branche, une colonne bricolée
> à la main, et, sur une base partagée, le travail en cours des collègues. Relisez-la ligne par ligne et retirez ce qui
> n'appartient pas au ticket (dans `up()` **et** dans `down()`), ou régénérez-la après `make sf-db-reset`.
> Alternative pour ne générer que certaines tables : `make sf c="doctrine:migrations:diff --filter-expression=/^comment/"`.
> Le filtre porte sur les **noms de tables** : il n'isole pas deux changements différents sur la même table `task`.

### Fixtures

(Voir aussi [les conventions détaillées](#fixtures) plus haut.) Ne mettez pas tout dans `AppFixtures.php` (conflits garantis). Chaque dev crée sa classe : `ProjectFixtures`, `UserFixtures`, `CommentFixtures`, avec `DependentFixtureInterface` et les références (`$this->addReference('user-alice', $alice)` / `$this->getReference('user-alice', User::class)`) pour lier les objets entre classes. `make sf-db-reset` doit toujours fonctionner sur `main`.

### L'IA sur le backlog

C'est la partie qui ressemble le plus à votre futur projet : utilisez l'IA comme vous le ferez au quotidien
(générer une entité, un DTO, un test, un `security.yaml`, expliquer une erreur du profiler). Ce qui est attendu,
c'est que **chaque ligne de la PR ait été lue, comprise et exécutée** par son auteur. Avant de pousser :

- lisez le diff complet comme si un collègue l'avait écrit, et supprimez ce que vous ne savez pas justifier ;
- exécutez : `make sf-test`, un `curl` par code HTTP du contrat, l'onglet Doctrine du profiler (pas de N+1) ;
- relisez la migration générée ligne par ligne.

Pièges fréquents des réponses d'IA sur Symfony / Doctrine : annotations `@ORM\Column` au lieu des attributs `#[ORM\Column]`,
`sensio/framework-extra-bundle` et `@ParamConverter` (Symfony < 6), `anonymous: true` ou `guard` dans `security.yaml`,
proposition d'installer `lexik/jwt-authentication-bundle` ou API Platform (hors périmètre ici), alias Doctrine `App:Task`
(supprimés en ORM 3), setters publics partout, `flush()` dans une boucle. Voir la [charte IA](../../00-charte-ia.md).

### Définition of done (pour chaque ticket)

- [ ] Le comportement correspond **exactement** au contrat (noms de champs, codes HTTP, tri).
- [ ] Tests fonctionnels `WebTestCase` : au minimum le cas nominal et un cas d'erreur pour le socle (idéalement un test par code d'erreur du contrat). `make sf-test` vert.
- [ ] `make sf-check` toujours vert (non-régression de la partie 1 du contrat : les tâches).
- [ ] `make sf c="doctrine:schema:validate"` OK (mapping et base synchronisés).
- [ ] `make sf-db-reset` fonctionne, fixtures à jour.
- [ ] Nouveaux contrôleurs : `format: 'json'`, `declare(strict_types=1)`, classes `final`, DTO en entrée.
- [ ] Pas de N+1 visible dans le profiler sur les listes.
- [ ] PR relue par un autre dev back.
- [ ] Description de PR avec la section « Ce que l'IA a produit / ce que j'ai vérifié » ([charte IA](../../00-charte-ia.md)).
- [ ] Capable d'expliquer son code en revue orale (en clôture).

---

## Dev Back 1 — Projets

### B1-1 — Entité `Project` et migration · 🟢 Socle

**Objectif** : l'entité `Project` existe en base, avec des données de démo.
**Notions** : `make:entity`, attributs ORM, migrations, fixtures.

Étapes :
1. `make sf-entity` → `Project` : `name` (string 100), `description` (text, nullable), `createdAt` (`datetime_immutable`).
2. Retravaillez la classe générée comme `Task` : constructeur `__construct(string $name, ?string $description = null)` qui initialise `createdAt`, méthodes métier `rename()`, `describe()` au lieu des setters.
3. `#[ORM\Column(length: 100, unique: true)]` sur `name`.
4. `make sf-migration` → relire → `make sf-migrate`.
5. `src/DataFixtures/ProjectFixtures.php` : 2 projets (« Formation PHP », « Formation React »), `addReference()` pour chacun.

**Acceptation** : `make sf c="doctrine:schema:validate"` OK ; `make sf-db-reset` puis `make sf-psql` → `SELECT * FROM project;` affiche 2 lignes.
**Pièges** : `make:entity` génère des setters publics et des types nullables partout — c'est un point de départ.
**Doc** : <https://symfony.com/doc/7.4/doctrine.html>

### B1-2 — CRUD `/api/projects` · 🟢 Socle

**Objectif** : `GET /api/projects` (trié par `name`), `GET /api/projects/{id}`, `POST`, `PATCH`, `DELETE` (sans le 409, voir B1-4).
**Notions** : contrôleur, `#[MapRequestPayload]`, DTO, `UniqueEntity` / contrainte personnalisée, `EntityValueResolver`.

Étapes :
1. `src/Controller/ProjectController.php` sur le modèle de `TaskController` (`#[Route('/api/projects', name: 'api_projects_', format: 'json')]`).
2. DTO `CreateProjectInput` / `UpdateProjectInput` : `NotBlank`, `Length(min: 2, max: 100)` sur `name`, `Length(max: 2000)` sur `description`.
3. Unicité de `name` → **422 sur `name`** (pas une 500 de PostgreSQL !). `UniqueEntity` ne s'applique qu'à une entité ; sur un DTO, écrivez une contrainte `UniqueProjectName` (`make sf c="make:validator"`) qui injecte `ProjectRepository`. Attention en PATCH : renommer un projet avec son propre nom ne doit pas être refusé.
   *Indice* : le validateur est un service, il ne reçoit que la valeur de `name`, pas le projet modifié. Le plus simple :
   injectez aussi `RequestStack` et ignorez le projet trouvé s'il porte l'id de la route courante.
   ```php
   $existing = $this->projects->findOneBy(['name' => $value]);
   $currentId = $this->requestStack->getCurrentRequest()?->attributes->get('id'); // null en POST, "3" (string) en PATCH /api/projects/3
   if (null === $existing || (int) $currentId === $existing->getId()) {
       return; // nom libre, ou c'est le projet en cours de modification
   }
   $this->context->buildViolation($constraint->message)->addViolation();
   ```
   Alternatives : vérifier dans le contrôleur et construire la violation à la main, ou `#[UniqueEntity('name')]` sur
   l'entité `Project` en validant l'entité modifiée (`UniqueEntity` ignore l'entité elle-même), mais il faut alors
   produire soi-même le 422 au format `violations[]`.
4. `taskCount` : pour l'instant `0` (méthode `getTaskCount()` qui renvoie… la taille de la relation, créée en B1-3).
5. `ProjectRepository::findAllOrderedByName()`.

**Acceptation** :
```bash
curl -s localhost:8082/api/projects                       # tableau trié par name
curl -si -X POST localhost:8082/api/projects -H 'Content-Type: application/json' -d '{"name":"Formation PHP"}'   # 422 sur name (doublon)
curl -si -X POST localhost:8082/api/projects -H 'Content-Type: application/json' -d '{"name":"X"}'               # 422 (trop court)
curl -si localhost:8082/api/projects/999                  # 404
```
Tests : `tests/Controller/ProjectControllerTest.php` couvrant 200, 201 (+ `Location`), 404, 422 (doublon, trop court), 204.
**Doc** : <https://symfony.com/doc/7.4/validation/custom_constraint.html>

### B1-3 — Relation `Task` → `Project` · 🟢 Socle (étapes 1-4, 7) · 🔵 étapes 5-6

**Objectif** : une tâche peut appartenir à un projet ; `Task` expose `project: {id, name} | null` ; POST/PATCH acceptent `projectId`.
**Notions** : `ManyToOne`/`OneToMany`, owning side, `ArrayCollection`, références circulaires de sérialisation, `#[Groups]` ou DTO de sortie, PATCH et `null` explicite.
↩︎ Kata : K20 (côté propriétaire), K06 (fetch join), K05 / K11 (compter sans charger). Cours : [Relations](../04-doctrine/02-relations.md).

Étapes :
1. `make sf-entity` → `Task` → champ `project`, type `relation`, `ManyToOne`, nullable, avec le côté inverse `Project::$tasks`. Lisez [owning vs inverse side](../04-doctrine/02-relations.md).
2. Migration (règles ci-dessus : rebase + reset avant !).
3. **Sérialisation** : `$this->json($task)` va maintenant exploser (task → project → tasks → project…). Choisissez en équipe (B1 propose, B2 et B3 valident, tout le monde s'y tient) :
   - groupes `task:read` / `project:read` + `context: ['groups' => [...]]` dans **tous** les `json()` ;
   - ou DTO de sortie (`TaskOutput`, `ProjectOutput`, `ProjectSummary {id, name}`).
   Dans `Task`, `project` ne doit contenir que `{id, name}`.
4. `projectId` dans `CreateTaskInput` (`?int`) et `UpdateTaskInput`. Projet inexistant → **422 sur `projectId`** (contrainte personnalisée `ExistingProject` ou violation construite à la main).
5. 🔵 *(bonus)* **PATCH `"projectId": null` doit détacher** — impossible avec le DTO actuel : implémentez une des solutions de [la limite du PATCH](../03-symfony/02-particularites.md#10-la-limite-du-patch-avec-un-dto-nullable) et appliquez-la aussi à `description`.
6. 🔵 *(bonus)* `taskCount` réel, sans charger les tâches **et sans N+1** :
   - **liste** `GET /api/projects` : **une seule requête** qui compte pour tous les projets, en
     [résultat mixte](../04-doctrine/03-dql-et-query-builder.md#résultats-mixtes--entité--valeurs-calculées) (K05, K11) :
     `SELECT p, COUNT(t.id) AS taskCount FROM App\Entity\Project p LEFT JOIN p.tasks t GROUP BY p.id ORDER BY p.name`,
     puis reportez chaque compte sur son projet (propriété non mappée de `Project`, ou DTO de sortie) ;
   - **détail** `GET /api/projects/{id}` : `fetch: 'EXTRA_LAZY'` sur `Project::$tasks` suffit, `$project->getTasks()->count()`
     fait un `SELECT COUNT(*)` sans charger les tâches.

   > ⚠️ `EXTRA_LAZY` seul sur la **liste** = un `COUNT` **par projet** : c'est un N+1 (visible dans le profiler), contraire
   > à la Definition of Done.
7. Fixtures : rattachez les tâches de démo aux projets.

**Acceptation** :
```bash
curl -s -X PATCH localhost:8082/api/tasks/1 -H 'Content-Type: application/json' -d '{"projectId": 1}'     # project: {id:1, name:...}
curl -s -X PATCH localhost:8082/api/tasks/1 -H 'Content-Type: application/json' -d '{"projectId": null}'  # project: null  (bonus, étape 5)
curl -si -X POST localhost:8082/api/tasks -H 'Content-Type: application/json' -d '{"title":"abc","projectId":999}'  # 422 projectId
```
Profiler sur `GET /api/tasks` : nombre de requêtes SQL constant quel que soit le nombre de tâches, grâce au fetch join
dans la méthode de repository qu'appelle `list()` : `findAllOrdered()` tant que B3-1 n'est pas mergé,
`TaskRepository::search()` ensuite (vérifiez avec Dev Back 3 que le fetch join y est). Bonus étape 6 : sur
`GET /api/projects`, le nombre de requêtes est constant lui aussi.
**Doc** : <https://symfony.com/doc/7.4/doctrine/associations.html>, <https://symfony.com/doc/7.4/serializer.html#using-serialization-groups-attributes>

### B1-4 — `GET /api/projects/{id}/tasks` et suppression protégée (409) · 🔵 Bonus

**Objectif** : liste des tâches d'un projet ; `DELETE` d'un projet non vide → 409.
**Notions** : route imbriquée, `ConflictHttpException`, requête de repository.

Étapes :
1. Action `tasks(Project $project)` dans `ProjectController`, route `/{id}/tasks` → `TaskRepository::findByProject($project)` trié par id (même forme de `Task` que `/api/tasks`).
2. Dans `delete()` : si le projet a des tâches → `throw new ConflictHttpException('Project 3 still has 2 tasks')`.
3. Vérifiez aussi la contrainte côté base : `onDelete` de la clé étrangère (que se passerait-il sans votre contrôle ?).

**Acceptation** : 404 sur projet inexistant ; 409 sur projet avec tâches ; 204 sur projet vide ; tests correspondants.
**Doc** : <https://symfony.com/doc/7.4/controller.html#managing-errors-and-404-pages>

---

## Dev Back 2 — Utilisateurs & authentification

> ⚠️ Votre ticket B2-4 rend toutes les écritures authentifiées : **c'est le ticket le plus impactant pour les autres**
> (tests, `make sf-check`, front). Annoncez-le, et livrez-le avec les adaptations décrites.

### B2-1 — Entité `User`, hash des mots de passe, fixtures · 🟢 Socle

**Objectif** : table `user` avec `email` (unique), `displayName`, `password` (hashé), `roles`.
**Notions** : SecurityBundle, `UserInterface`, `PasswordAuthenticatedUserInterface`, password hasher, `#[Ignore]`.

Étapes :
1. `make sf-composer c="require symfony/security-bundle"` → lisez le diff de la recette (`config/packages/security.yaml`).
   Le diff touche aussi `config/bundles.php`, `config/routes/security.yaml` et surtout `config/reference.php` (plusieurs
   centaines de lignes) : ce fichier est **généré** par Symfony 7.4 et décrit les options de config des bundles installés.
   On le commite tel quel ; signalez-le dans la PR pour que le relecteur ne s'en étonne pas.
2. `make sf c="make:user"` (email comme identifiant, mot de passe hashé : oui) puis ajoutez `displayName`.
   `make:user` génère un `eraseCredentials()` vide marqué `#[\Deprecated]` : la méthode est dépréciée depuis Symfony 7.3
   mais encore exigée par `UserInterface` jusqu'à Symfony 8. Laissez-la vide (on ne garde jamais de mot de passe en
   clair dans l'entité) ; ne suivez pas une IA qui y ajoute de la logique.
3. Mot de passe **jamais** sérialisé : `#[Ignore]` sur `getPassword()` (ou groupes / DTO de sortie selon la décision d'équipe de B1-3). Ne pas exposer `roles` ni `userIdentifier` non plus : la forme JSON de `User` est exactement `{id, email, displayName}`.
4. Migration. Attention : `user` est un mot réservé PostgreSQL → `#[ORM\Table(name: '`user`')]` (généré par `make:user`) ou nommez la table `app_user`.
5. `UserFixtures` : `alice@taskboard.local` (`ROLE_ADMIN`) et `bob@taskboard.local`, mot de passe `password`, hashés avec `UserPasswordHasherInterface` injecté dans la fixture. Références `user-alice`, `user-bob`.

**Acceptation** : `make sf-db-reset` OK ; en base, les mots de passe commencent par `$2y$` ou `$argon2`.
**Doc** : <https://symfony.com/doc/7.4/security.html>, <https://symfony.com/doc/7.4/security/passwords.html>

### B2-2 — `POST /api/auth/register` (🟢 Socle) et `GET /api/users` (🔵 Bonus)

**Objectif** : inscription et liste des utilisateurs (triés par `displayName`).
**Notions** : DTO + contraintes (`Email`, `Length`, unicité), hash à la création, 201.

Étapes :
1. `RegisterInput` : `#[Assert\Email]`, `#[Assert\Length(min: 8)]` sur `password`, `Length(min: 2, max: 50)` sur `displayName`. Email déjà pris → 422 sur `email` (même technique que l'unicité du nom de projet en B1-2 : mettez-vous d'accord avec B1 pour partager le code).
2. `AuthController::register()` : hash + `persist` + `flush` → 201 + `User`, **sans** en-tête `Location` : il n'existe pas de `GET /api/users/{id}` vers lequel pointer (voir la règle « Création » du contrat).
3. 🔵 *(bonus)* `UserController::list()` → `GET /api/users`.

**Acceptation (socle)** : 201 et au moins deux cas 422 (email pris, mot de passe trop court) testés ; les autres cas 422 sont un plus.

### B2-3 — `POST /api/auth/login`, tokens, `GET /api/me` · 🟢 Socle

**Objectif** : obtenir un token valable 24 h et s'en servir.
**Notions** : entité `ApiToken`, authenticator **`access_token` natif** (pas de bundle JWT : on veut comprendre le mécanisme), `AccessTokenHandlerInterface`, firewall stateless, `#[CurrentUser]`.

Étapes :
1. Entité `ApiToken` : `token` (string 64, unique, généré par `bin2hex(random_bytes(32))`), `user` (`ManyToOne`), `expiresAt` (`now + 24h`). Migration.
2. `AuthController::login()` : charger l'utilisateur par email, `UserPasswordHasherInterface::isPasswordValid()`. Échec → **401** (même message que l'email existe ou non). Succès → créer un `ApiToken`, répondre `{ "token": "...", "user": {...} }`.
   Corps incomplet ou mal typé (champ absent, vide ou qui n'est pas une chaîne) → **422** comme partout ailleurs :
   `#[Assert\NotBlank]` sur les deux champs de `LoginInput`, rien de plus (pas de `Length` : un login ne doit rien révéler
   des règles d'inscription).
3. `src/Security/ApiTokenHandler.php implements AccessTokenHandlerInterface` : trouve le token, vérifie l'expiration, renvoie `new UserBadge($user->getUserIdentifier())`. Token inconnu/expiré → `throw new BadCredentialsException()`.
4. `security.yaml` :
   ```yaml
   security:
       firewalls:
           api:
               pattern: ^/api
               stateless: true
               access_token:
                   token_handler: App\Security\ApiTokenHandler
                   failure_handler: App\Security\ApiTokenFailureHandler
   ```
   Sans `failure_handler`, un token invalide ou expiré donne un 401 au **corps vide** (seulement l'en-tête
   `WWW-Authenticate`), hors du format d'erreur du contrat. `ApiTokenFailureHandler implements AuthenticationFailureHandlerInterface`
   renvoie une `JsonResponse` `{"title": "Unauthorized", "status": 401, "detail": "Invalid or expired token."}` (gardez
   l'en-tête `WWW-Authenticate: Bearer`).
5. `GET /api/me` : `public function me(#[CurrentUser] ?User $user)` → 401 si `null` (ou `#[IsGranted('IS_AUTHENTICATED')]`).

**Acceptation** :
```bash
TOKEN=$(curl -s -X POST localhost:8082/api/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"alice@taskboard.local","password":"password"}' | sed 's/.*"token":"\([^"]*\)".*/\1/')
curl -s localhost:8082/api/me -H "Authorization: Bearer $TOKEN"     # 200 Alice
curl -si localhost:8082/api/me                                      # 401
curl -si localhost:8082/api/me -H "Authorization: Bearer faux"      # 401, corps JSON (failure_handler)
curl -si localhost:8082/api/tasks -H "Authorization: Bearer faux"   # 401 aussi : token présent mais invalide, même sur un GET public
curl -si -X POST localhost:8082/api/auth/login -H 'Content-Type: application/json' -d '{}'   # 422
```
**Pièges** : un firewall `stateless` n'utilise pas de session ; tester `/api/auth/login` lui-même ne doit pas exiger de token.
Un token **présent mais invalide** est refusé par l'authenticator **avant** `access_control` : 401 même sur une route
publique (c'est le comportement retenu par le contrat ; le client doit oublier ce token et rejouer sans).
**Doc** : <https://symfony.com/doc/7.4/security/access_token.html>

### B2-4 — Protéger les écritures (🟢 Socle, B2-4b), auteur et assigné des tâches (🔵 Bonus, B2-4a)

**Objectif** : POST/PATCH/DELETE → 401 sans token ; `Task.author` renseigné automatiquement ; `Task.assignee` via `assigneeId`.
**Livré en deux PR** (voir [l'ordre de passage](#fichiers-partagés--règles)) : **B2-4a** = étapes 2 à 4 (relations sur `Task`, mergée tôt) ;
**B2-4b** = étapes 1, 5 et 6 (protection des écritures + adaptation des tests et de `contract-check`, mergée **en dernier**).
**Notions** : `access_control`, `Security::getUser()`, relations `ManyToOne` vers `User`, adaptation des tests (vrai token `Bearer` en test).

Étapes :
1. `access_control` dans `security.yaml` :
   ```yaml
   access_control:
       - { path: ^/api/auth, roles: PUBLIC_ACCESS }
       - { path: ^/api, methods: [POST, PATCH, PUT, DELETE], roles: IS_AUTHENTICATED }
   ```
2. `Task` : `author` et `assignee` (`ManyToOne` vers `User`, nullable). Migration (règles de rebase !). `author` = `$this->getUser()` dans `create()`.
3. `assigneeId` dans les DTO de tâche ; utilisateur inexistant → 422 sur `assigneeId` ; `null` explicite → désassigner (réutiliser la solution PATCH de B1-3).
4. Sérialisation : `author` et `assignee` sous la forme `User` `{id, email, displayName}` ou `null`.
5. **Adapter l'existant** :
   - tous les tests `WebTestCase` qui écrivent doivent envoyer un **vrai token**. Fournissez aux autres, **avant** B2-4b,
     un trait de test, par exemple `tests/Security/LogsInUsers.php` (à adapter à vos constructeurs) :
     ```php
     trait LogsInUsers
     {
         private function logIn(KernelBrowser $client, string $email = 'tester@taskboard.local', array $roles = []): User
         {
             $em = $client->getContainer()->get(EntityManagerInterface::class);
             $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
             if (null === $user) {                 // la base de test n'a pas de fixtures
                 $user = new User($email, 'Tester', $roles);
                 $em->persist($user);
             }
             $token = new ApiToken($user);         // token aléatoire, expiration à +24 h (B2-3)
             $em->persist($token);
             $em->flush();

             // envoyé sur TOUTES les requêtes suivantes de ce client
             $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token->getToken());

             return $user;
         }
     }
     ```
     Dans chaque test : `use LogsInUsers;` puis `$this->logIn($this->client);` (ou `$this->logIn($this->client, 'admin@taskboard.local', ['ROLE_ADMIN'])`).

     > ⚠️ **Pas `$client->loginUser($user)`** avec notre firewall stateless : le token qu'il pose ne tient que pour **une
     > requête**. Le client redémarre le kernel entre deux requêtes et, sans session, rien ne restaure le token : la
     > 2ᵉ écriture du même test reçoit 401. En plus, son firewall par défaut est `main`, pas `api`. `$client->disableReboot()`
     > le fait tenir, mais garde alors le même conteneur et le même EntityManager d'une requête à l'autre (Identity Map
     > partagée, état qui fuit) : on ne teste plus l'application dans ses vraies conditions. Le vrai token, lui, teste
     > aussi le vrai chemin d'authentification.
   - `apps/api-php/tools/contract-check.php` : ajouter la prise en compte d'une variable d'environnement `API_TOKEN` (envoyer `Authorization: Bearer ...` si elle est définie) et faire évoluer la cible `sf-check` du Makefile pour faire d'abord un login. Vérifiez que `make php-check` reste vert (l'API PHP pur ignore l'en-tête).
6. Prévenez l'équipe : à partir de ce merge, tout client (curl, tests, [front Vue bonus](bonus-front-vue.md)) doit se connecter pour créer/modifier.

**Acceptation** : `POST /api/tasks` sans token → 401, avec token → 201 et `author` = l'utilisateur connecté ; `make sf-test` et `make sf-check` verts.
**Doc** : <https://symfony.com/doc/7.4/security.html#access-control-authorization>, <https://symfony.com/doc/7.4/testing.html#logging-in-users-authentication>

### B2-5 — Voter : suppression réservée à l'auteur ou à l'admin · 🔵 Bonus

**Objectif** : `DELETE /api/tasks/{id}` → 403 si l'utilisateur n'est ni l'auteur ni `ROLE_ADMIN`.
**Notions** : voters, `#[IsGranted(attribute, subject)]`, hiérarchie de rôles.

Étapes :
1. `make sf c="make:voter"` → `TaskVoter` avec l'attribut `TASK_DELETE`.
2. `voteOnAttribute()` : vrai si `$task->getAuthor() === $user` ou si l'utilisateur a `ROLE_ADMIN` (injectez `Security` / `AccessDecisionManagerInterface` pour tester un rôle). Tâche sans auteur (créée avant B2) : admin seulement.
3. Sur l'action : `#[IsGranted('TASK_DELETE', 'task')]`.
4. Proposez à B3 de réutiliser le même principe pour `COMMENT_DELETE`.
5. Adaptez les tests des autres qui suppriment une tâche sans auteur (voir l'encadré de [l'ordre de passage](#fichiers-partagés--règles)) : sans cela, ils passent à 403.

**Acceptation** : Bob ne peut pas supprimer une tâche d'Alice (403), Alice peut tout supprimer, Bob peut supprimer les siennes ; tests avec `LogsInUsers` ; `make sf-test` vert pour **toute** l'équipe.
**Doc** : <https://symfony.com/doc/7.4/security/voters.html>

---

## Dev Back 3 — Commentaires, filtres & pagination

### B3-1 — Filtres et pagination sur `GET /api/tasks` · 🟢 Socle

**Objectif** : `status`, `projectId`, `search`, `page`, `limit` + en-tête `X-Total-Count`, sans changer la forme de la réponse.
**Notions** : `#[MapQueryString]`, DTO + validation de query string, QueryBuilder dynamique, `Doctrine\ORM\Tools\Pagination\Paginator`.
↩︎ Kata : K09 (requête dynamique) et K10 (Paginator) — c'est presque le même code. Cours : [DQL et QueryBuilder](../04-doctrine/03-dql-et-query-builder.md).

Étapes :
1. DTO `src/Dto/TaskFilter.php` :
   ```php
   final readonly class TaskFilter
   {
       public function __construct(
           public ?TaskStatus $status = null,
           public ?int $projectId = null,
           public ?string $search = null,
           #[Assert\Positive] public int $page = 1,
           #[Assert\Range(min: 1, max: 100)] public int $limit = 50,
       ) {}
   }
   ```
2. `list(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] TaskFilter $filter = new TaskFilter())`
   — la valeur par défaut est indispensable quand il n'y a aucun paramètre.

   > ⚠️ **Piège** : `#[MapQueryString]` seul répond **404** quand la query string est invalide (statut inconnu, `limit`
   > hors bornes…) : son `validationFailedStatusCode` vaut `Response::HTTP_NOT_FOUND` par défaut, alors que celui de
   > `#[MapRequestPayload]` vaut 422. Le contrat veut 422 : précisez-le. Un test sur `?limit=500` le révèle tout de suite.
3. `TaskRepository::search(TaskFilter $filter): Paginator` : `andWhere` conditionnels, recherche `LOWER(t.title) LIKE :q OR LOWER(t.description) LIKE :q`, `setFirstResult` / `setMaxResults`, tri par id.
   C'est désormais `search()`, et non plus `findAllOrdered()`, qui alimente `list()` : reprenez-y le fetch join de B1-3
   (et de B2-4a s'il est mergé), sinon le N+1 revient.
4. Réponse : `$this->json(iterator_to_array($paginator), headers: ['X-Total-Count' => count($paginator)])`.
5. `projectId` dépend de B1-3 : commencez sans, ajoutez-le après le merge de B1-3 (ou filtrez sur `IDENTITY(t.project)` dès que la relation existe).

**Acceptation** :
```bash
curl -si 'localhost:8082/api/tasks?status=todo&limit=2' | grep -i x-total-count
curl -si 'localhost:8082/api/tasks?status=nimporte'          # 422
curl -si 'localhost:8082/api/tasks?limit=500'                # 422
curl -s  'localhost:8082/api/tasks?search=PHP'               # insensible à la casse
```
`make sf-check` toujours vert (sans paramètre = comportement de la partie 1 du contrat).
**Pièges** : avec un fetch join (ajouté en B1-3) + `setMaxResults`, sans `Paginator` la limite s'applique aux lignes SQL et non aux entités.
**Doc** : <https://symfony.com/doc/7.4/controller.html#mapping-query-string-parameters>, <https://www.doctrine-project.org/projects/doctrine-orm/en/current/tutorials/pagination.html>

### B3-2 — Entité `Comment` et endpoints · 🟢 Socle (entité, `GET`, `POST`) · 🔵 `DELETE`, `commentCount`

**Objectif** : `GET` / `POST /api/tasks/{id}/comments`, `DELETE /api/comments/{id}` ; `Task.commentCount`.
**Notions** : relation `ManyToOne` **unidirectionnelle** vers `Task` avec `onDelete: 'CASCADE'`, route imbriquée, tri.
↩︎ Kata : K21 (orphanRemoval), K18 (cascade persist), K05 (COUNT).

Étapes :
1. `make sf-entity` → `Comment` : `content` (text), `createdAt`, `task` (`ManyToOne`, non nullable, **unidirectionnel** : répondez « non » au côté inverse, pas de `Task::$comments`), **`author` : `ManyToOne` vers `User`, nullable** — si B2-1 n'est pas encore mergé, créez le champ plus tard dans une migration séparée, ne bloquez pas.
   Pourquoi unidirectionnel : `Task.php` est un fichier partagé que B3 ne touche qu'en B3-3 ([ordre de passage](#fichiers-partagés--règles)),
   et on n'a jamais besoin de `$task->getComments()` : les commentaires d'une tâche se lisent par une requête dans
   `CommentRepository` (voir [Relations, §2.1](../04-doctrine/02-relations.md#21-les-quatre-types-de-relations)).
2. Supprimer une tâche doit supprimer ses commentaires : `#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]` sur `Comment::$task`, c'est la base qui supprime (`orphanRemoval: true` exigerait le côté inverse `Task::$comments`). Vérifiez que le test `testDeleteTask` passe toujours.
3. `CommentController` : `GET /api/tasks/{id}/comments` (du plus ancien au plus récent), `POST` (DTO `content` : `#[Assert\NotBlank(normalizer: 'trim')]` + `#[Assert\Length(max: 1000)]` ; pas de `Length(min: 1)`, qui ajouterait une 2ᵉ violation pour un contenu vide, et sans `normalizer` un contenu fait d'espaces passerait) ; 🔵 *(bonus)* `DELETE /api/comments/{id}`.
4. 🔵 *(bonus)* `commentCount` dans `Task`. C'est un **bonus** : tant qu'il n'est pas livré, le champ est absent du JSON
   et le front le traite comme facultatif (il ne l'affiche que s'il est présent). Sans côté inverse, pas d'`EXTRA_LAZY` :
   pour la liste, **une seule requête pour toute la page**
   (`SELECT IDENTITY(c.task) AS taskId, COUNT(c.id) AS nb FROM App\Entity\Comment c WHERE c.task IN (:tasks) GROUP BY c.task`),
   puis reportez chaque compte sur sa tâche (propriété non mappée de `Task`, `0` par défaut) ; pour une seule tâche, un
   `COUNT` simple. Cette partie touche `Task.php` : elle passe avec B3-3 dans [l'ordre de passage](#fichiers-partagés--règles).
5. `CommentFixtures` dépendant de la fixture des tâches (et des utilisateurs après B2).

**Acceptation (socle)** : 200 / 201 (commentaire dans le corps, pas d'en-tête `Location` : il n'existe pas de `GET /api/comments/{id}`) / 404 (tâche inexistante) / 422 (contenu vide) ; tests. **Bonus** : 204 sur `DELETE`, `commentCount` correct.

### B3-3 — `updatedAt` et `completedAt` automatiques · 🔵 Bonus

**Objectif** : `updatedAt` mis à jour à chaque modification d'une tâche ; `completedAt` renseigné au passage à `done`, remis à `null` sinon.
**Notions** : lifecycle callbacks Doctrine (`#[ORM\HasLifecycleCallbacks]`, `#[ORM\PreUpdate]`) **ou** EntityListener (`#[AsEntityListener]`), logique métier dans l'entité.
↩︎ Cours : [ORM et mapping](../04-doctrine/01-orm-et-mapping.md) (cycle de vie des entités, Unit of Work).

Étapes :
1. Champs `updatedAt` et `completedAt` (`?DateTimeImmutable`), migration.
2. `completedAt` est une règle **métier** : mettez-la dans `Task::changeStatus()` (et dans le constructeur pour une tâche créée directement en `done`). Pas besoin de Doctrine pour ça.
3. `updatedAt` est une règle **technique** : `#[ORM\PreUpdate]` dans l'entité, ou un `TaskTimestampListener` avec `#[AsEntityListener(event: Events::preUpdate, entity: Task::class)]` (≈ un middleware Prisma `$extends` ou un subscriber TypeORM `beforeUpdate`). Comparez les deux approches dans la PR.
4. Bonus : utiliser `Symfony\Component\Clock\ClockInterface` plutôt que `new \DateTimeImmutable()` pour pouvoir figer le temps dans les tests (`MockClock`).

**Acceptation** : `PATCH {"status":"done"}` → `completedAt` non null ; `PATCH {"status":"todo"}` → `completedAt` null ; `updatedAt` change à chaque PATCH, reste `null` à la création.
**Doc** : <https://symfony.com/doc/7.4/doctrine/events.html>

### B3-4 — Droits sur les commentaires · 🔵 Bonus

**Dépend de B2-4 et B2-5.** `author` du commentaire = utilisateur connecté ; `DELETE /api/comments/{id}` → 403 si on n'est pas l'auteur (voter `CommentVoter`, sur le modèle de `TaskVoter`). Tests avec `LogsInUsers`.

---

## Tickets transverses

### T-1 (bonus) — Étendre la vérification de contrat (chacun pour sa partie)

`apps/api-php/tools/contract-check.php` ne couvre que la partie 1 du contrat (les tâches). Chaque dev ajoute une section pour sa partie (le script prend déjà l'URL en argument) **ou** des tests `WebTestCase` qui en couvrent l'intégralité — choix à faire en équipe. Objectif : un seul `make sf-check` ou `make sf-test` prouve que tout le contrat est respecté.

### T-2 (bonus) — CORS

Aujourd'hui, le front fourni passe par le proxy Vite (même origine) : pas de CORS — exactement comme un projet Vue avec `server.proxy`. Pour appeler l'API directement depuis `http://localhost:5173` (ou votre [front Vue](bonus-front-vue.md)) vers `http://localhost:8082` :
`make sf-composer c="require nelmio/cors-bundle"`, lire la recette, autoriser `http://localhost:5173` dans `config/packages/nelmio_cors.yaml`, exposer `X-Total-Count` (`expose_headers`) et accepter `Authorization`. Testez avec un `fetch('http://localhost:8082/api/tasks')` depuis la console du navigateur.

### T-3 (bonus) — Documentation OpenAPI

`nelmio/api-doc-bundle` : générer une page Swagger à partir des routes et DTO. Comparez avec `@nestjs/swagger` ou `swagger-jsdoc`. Bonus du bonus : générer les types TypeScript de votre front Vue depuis ce document (`openapi-typescript`).

---

## Carte des dépendances

```
B1-1 ─▶ B1-2 ─▶ B1-3 ─▶ B1-4
                  │  └──────────────▶ B3-1 (filtre projectId)
                  └─ décision sérialisation (groupes ou DTO) ─▶ B2-4, B3-2
B2-1 ─▶ B2-2 ─▶ B2-3 ─▶ B2-4 ─▶ B2-5
          │                │       └──▶ B3-4
          └────────────────┴──────────▶ B3-2 (author du commentaire, nullable en attendant)
B3-1 ─▶ B3-2 ─▶ B3-3 ─▶ B3-4
```

Démarrage en parallèle pendant la mise en place : **B1-1**, **B2-1**, entité de **B3-2** (aucune dépendance entre eux). Point d'équipe à la fin de la mise en place pour trancher la stratégie de sérialisation (B1-3) avant que les relations n'arrivent.
Sur `Task` : B1-3 → (B2-4a) → (B3-3) → B2-4b (voir [le déroulé](#déroulé-ordonné)).
