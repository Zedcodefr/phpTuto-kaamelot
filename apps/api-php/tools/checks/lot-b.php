<?php

/**
 * 🅱️ Vérifications du LOT B — Données & PDO.  make php-check-b
 * Utilise la vraie base taskboard_php, dans une transaction ANNULÉE à la fin : aucune donnée n'est modifiée.
 */

declare(strict_types=1);

require __DIR__ . '/harness.php';

use App\Database\Connection;
use App\Entity\Task;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;

Checks::title('🅱️  Lot B — Données & PDO');

Checks::section('Task');
$row = ['id' => 7, 'title' => 'Titre', 'description' => null, 'status' => 'in_progress', 'created_at' => '2026-10-15 09:30:00+02'];
Checks::check('fromRow() : champs et types', function () use ($row) {
    $task = Task::fromRow($row);

    return $task->id === 7 && $task->title === 'Titre' && $task->description === null
        && $task->status === TaskStatus::InProgress && $task->createdAt instanceof \DateTimeImmutable;
});
Checks::check('fromRow() accepte un id renvoyé en string', fn () => Task::fromRow(['id' => '7'] + $row)->id === 7);
Checks::check('jsonSerialize() conforme au contrat', fn () => json_encode(Task::fromRow($row))
    === '{"id":7,"title":"Titre","description":null,"status":"in_progress","createdAt":"2026-10-15T09:30:00+02:00"}');

Checks::section('Connection');
$pdo = null;
Checks::check('fromEnvironment() renvoie un PDO', function () use (&$pdo) {
    $pdo = Connection::fromEnvironment();

    return $pdo instanceof \PDO;
});
if (!$pdo instanceof \PDO) {
    echo "\n  \033[2m(la suite nécessite la connexion)\033[0m\n";
    Checks::end();
}
Checks::check('erreurs SQL => exceptions', fn () => $pdo->getAttribute(\PDO::ATTR_ERRMODE) === \PDO::ERRMODE_EXCEPTION);
Checks::check('lignes en tableaux associatifs', fn () => $pdo->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE) === \PDO::FETCH_ASSOC);
Checks::check('vraies requêtes préparées (ATTR_EMULATE_PREPARES à false)', fn () => $pdo->getAttribute(\PDO::ATTR_EMULATE_PREPARES) === false);
Checks::check('fuseau SQL = fuseau PHP', fn () => $pdo->query('SHOW TIME ZONE')->fetchColumn() === date_default_timezone_get());

$pdo->beginTransaction();
$repository = new TaskRepository($pdo);

Checks::section('TaskRepository (dans une transaction annulée)');
Checks::check('findAll() : list<Task> triée par id', function () use ($repository) {
    $tasks = $repository->findAll();
    $ids = array_map(fn (Task $t) => $t->id, $tasks);
    $sorted = $ids;
    sort($sorted);

    return $tasks !== [] && array_is_list($tasks) && array_all($tasks, fn ($t) => $t instanceof Task) && $ids === $sorted;
});
Checks::check('find(id inexistant) → null', fn () => $repository->find(999999) === null);
$created = null;
Checks::check('save(nouvelle tâche) → id et createdAt renseignés', function () use ($repository, &$created) {
    $created = $repository->save(new Task('Créée par lot-b', 'desc', TaskStatus::Done));

    return is_int($created->id) && $created->createdAt instanceof \DateTimeImmutable && $created->status === TaskStatus::Done;
});
Checks::check('find(id) relit la tâche créée', fn () => $created !== null && $repository->find($created->id)?->title === 'Créée par lot-b');
Checks::check('save(tâche existante) → mise à jour', function () use ($repository, $created) {
    $created->title = 'Modifiée';
    $created->description = null;
    $repository->save($created);
    $reloaded = $repository->find($created->id);

    return $reloaded->title === 'Modifiée' && $reloaded->description === null && count($repository->findAll()) === count(array_unique(array_map(fn ($t) => $t->id, $repository->findAll())));
});
Checks::check('requêtes préparées : une apostrophe ne casse rien', fn () => $repository->save(new Task("L'injection ' OR 1=1 --"))->title === "L'injection ' OR 1=1 --");
Checks::check('delete(id) → true puis la tâche n\'existe plus', fn () => $repository->delete($created->id) === true && $repository->find($created->id) === null);
Checks::check('delete(id inexistant) → false', fn () => $repository->delete(999999) === false);

$pdo->rollBack();

Checks::end();
