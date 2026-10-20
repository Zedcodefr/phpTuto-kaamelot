<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Repository\TaskRepositoryInterface;
use App\Validation\TaskValidator;

/**
 * 🅲 LOT C — Les actions de l'API Tâches.
 *
 * Le contrôleur ne connaît que l'INTERFACE du repository : il peut être développé et testé
 * avec un faux repository en mémoire (tools/checks/Fake/InMemoryTaskRepository.php)
 * avant que le Lot B n'ait fini l'implémentation PDO.
 *
 * Erreurs : lancer une HttpException (404...) ou laisser TaskValidator lancer une ValidationException.
 * Le paramètre de route s'appelle $id et arrive en string (voir Router).
 */
final class TaskController
{
    public function __construct(
        private readonly TaskRepositoryInterface $repository,
        private readonly TaskValidator $validator = new TaskValidator(),
    ) {
    }

    /** GET /api/tasks → 200 + list<Task> */
    public function list(Request $request): JsonResponse
    {
        throw new \LogicException('TODO Lot C : TaskController::list()');
    }

    /** GET /api/tasks/{id} → 200 + Task, 404 sinon */
    public function show(Request $request, string $id): JsonResponse
    {
        throw new \LogicException('TODO Lot C : TaskController::show()');
    }

    /** POST /api/tasks → 201 + Task + en-tête Location, 400/422 sinon */
    public function create(Request $request): JsonResponse
    {
        throw new \LogicException('TODO Lot C : TaskController::create()');
    }

    /** PATCH /api/tasks/{id} → 200 + Task (seuls les champs envoyés changent), 404/400/422 sinon */
    public function update(Request $request, string $id): JsonResponse
    {
        throw new \LogicException('TODO Lot C : TaskController::update()');
    }

    /** DELETE /api/tasks/{id} → 204 sans corps, 404 sinon */
    public function delete(Request $request, string $id): JsonResponse
    {
        throw new \LogicException('TODO Lot C : TaskController::delete()');
    }
}
