<?php

/**
 * 🅲 Vérifications du LOT C — Métier & validation.  make php-check-c
 * Indépendant des lots A et B : le contrôleur reçoit un faux repository en mémoire,
 * et on inspecte directement les JsonResponse renvoyées (sans les envoyer).
 */

declare(strict_types=1);

require __DIR__ . '/harness.php';

use App\Controller\TaskController;
use App\Entity\Task;
use App\Enum\TaskStatus;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\ValidationException;
use App\Tests\Fake\InMemoryTaskRepository;
use App\Validation\TaskValidator;

Checks::title('🅲  Lot C — Métier & validation');

Checks::section('Request::json()');
$json = fn (string $body) => (new Request('POST', '/api/tasks', body: $body))->json();
Checks::check('objet JSON → tableau associatif', fn () => $json('{"title":"Été","n":1}') === ['title' => 'Été', 'n' => 1]);
Checks::check('"{}" → []', fn () => $json('{}') === []);
Checks::check('JSON invalide → HttpException 400', fn () => Checks::catch(HttpException::class, fn () => $json('{title:'))?->status === 400);
Checks::check('corps vide → HttpException 400', fn () => Checks::catch(HttpException::class, fn () => $json(''))?->status === 400);
Checks::check('tableau JSON [1,2] → HttpException 400', fn () => Checks::catch(HttpException::class, fn () => $json('[1,2]'))?->status === 400);
Checks::check('tableau JSON vide [] → HttpException 400 (ce n\'est pas {})', fn () => Checks::catch(HttpException::class, fn () => $json('[]'))?->status === 400);
Checks::check('objet à clé numérique {"0":"a"} → accepté', fn () => $json('{"0":"a"}') === [0 => 'a']);
Checks::check('scalaire JSON 42 → HttpException 400', fn () => Checks::catch(HttpException::class, fn () => $json('42'))?->status === 400);

Checks::section('TaskValidator');
$validator = new TaskValidator();
$errors = fn (array $data, bool $partial = false): array => Checks::catch(ValidationException::class, fn () => $validator->validate($data, $partial))?->errors ?? [];
Checks::check('données valides → aucune exception', fn () => $errors(['title' => 'Apprendre PHP', 'description' => null, 'status' => 'done']) === []);
Checks::check('title absent → erreur sur title', fn () => array_keys($errors([])) === ['title']);
Checks::check('title "   " → "This value should not be blank."', fn () => ($errors(['title' => '   '])['title'] ?? null) === 'This value should not be blank.');
Checks::check('title de 2 caractères → erreur de longueur', fn () => ($errors(['title' => 'ab'])['title'] ?? null) === 'This value should have between 3 and 255 characters.');
Checks::check('title "Été" (3 caractères, 5 octets) → valide', fn () => $errors(['title' => 'Été']) === []);
Checks::check('title de 256 caractères → erreur', fn () => isset($errors(['title' => str_repeat('a', 256)])['title']));
Checks::check('title non string (123) → erreur', fn () => isset($errors(['title' => 123])['title']));
Checks::check('description de 2001 caractères → erreur', fn () => isset($errors(['title' => 'abc', 'description' => str_repeat('é', 2001)])['description']));
Checks::check('description non string → erreur', fn () => isset($errors(['title' => 'abc', 'description' => ['x']])['description']));
Checks::check('status inconnu → erreur sur status', fn () => ($errors(['title' => 'abc', 'status' => 'nope'])['status'] ?? null) === 'The value should be one of: todo, in_progress, done.');
Checks::check('toutes les erreurs d\'un coup', fn () => array_keys($errors(['title' => '', 'status' => 'nope'])) === ['title', 'status']);
Checks::check('partial : title absent accepté', fn () => $errors(['status' => 'done'], partial: true) === []);
Checks::check('partial : title présent mais invalide → erreur', fn () => isset($errors(['title' => 'a'], partial: true)['title']));

Checks::section('TaskController (avec InMemoryTaskRepository)');
$repository = new InMemoryTaskRepository();
$repository->save(new Task('Existante', 'Sa description', TaskStatus::InProgress));
$controller = new TaskController($repository);
$request = fn (string $method, string $body = '') => new Request($method, '/api/tasks', body: $body);

Checks::check('list() → 200 + list<Task>', function () use ($controller, $request) {
    $response = $controller->list($request('GET'));

    return $response->status === 200 && is_array($response->data) && array_is_list($response->data)
        && ($response->data[0] ?? null) instanceof Task;
});
Checks::check('show("1") → 200 + Task', fn () => $controller->show($request('GET'), '1')->data?->title === 'Existante');
Checks::check('show("999") → HttpException 404', fn () => Checks::catch(HttpException::class, fn () => $controller->show($request('GET'), '999'))?->status === 404);

$createdId = null;
Checks::check('create() → 201 + Task enregistrée, status "todo" par défaut', function () use ($controller, $request, &$createdId) {
    $response = $controller->create($request('POST', '{"title":"Nouvelle tâche"}'));
    $createdId = $response->data?->id;

    return $response->status === 201 && $response->data instanceof Task
        && $response->data->status === TaskStatus::Todo && is_int($createdId);
});
Checks::check('create() → en-tête Location: /api/tasks/{id}', fn () => $controller->create($request('POST', '{"title":"Autre"}'))->headers === ['Location' => '/api/tasks/3']);
Checks::check('create() avec status et description', function () use ($controller, $request) {
    $task = $controller->create($request('POST', '{"title":"Complète","description":"d","status":"done"}'))->data;

    return $task->status === TaskStatus::Done && $task->description === 'd';
});
Checks::check('create() invalide → ValidationException', fn () => Checks::catch(ValidationException::class, fn () => $controller->create($request('POST', '{"title":""}'))) !== null);
Checks::check('create() JSON invalide → HttpException 400', fn () => Checks::catch(HttpException::class, fn () => $controller->create($request('POST', 'pas du json')))?->status === 400);

Checks::check('update() ne modifie que les champs envoyés', function () use ($controller, $request) {
    $response = $controller->update($request('PATCH', '{"status":"done"}'), '1');
    $task = $response->data;

    return $response->status === 200 && $task->status === TaskStatus::Done
        && $task->title === 'Existante' && $task->description === 'Sa description';
});
Checks::check('update() persiste la modification', fn () => $controller->show($request('GET'), '1')->data->status === TaskStatus::Done);
Checks::check('update() description "" → null', fn () => $controller->update($request('PATCH', '{"description":""}'), '1')->data->description === null);
Checks::check('update() invalide → ValidationException', fn () => Checks::catch(ValidationException::class, fn () => $controller->update($request('PATCH', '{"title":"a"}'), '1')) !== null);
Checks::check('update() id inexistant → 404', fn () => Checks::catch(HttpException::class, fn () => $controller->update($request('PATCH', '{"status":"done"}'), '999'))?->status === 404);

Checks::check('delete() → 204', fn () => $controller->delete($request('DELETE'), (string) $createdId)->status === 204);
Checks::check('delete() → la tâche n\'existe plus', fn () => Checks::catch(HttpException::class, fn () => $controller->show($request('GET'), (string) $createdId))?->status === 404);
Checks::check('delete() id inexistant → 404', fn () => Checks::catch(HttpException::class, fn () => $controller->delete($request('DELETE'), '999'))?->status === 404);

Checks::end();
