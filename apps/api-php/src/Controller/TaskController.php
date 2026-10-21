<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Task;
use App\Enum\TaskStatus;
use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Repository\TaskRepositoryInterface;
use App\Validation\TaskValidator;

final class TaskController
{
    public function __construct(
        private readonly TaskRepositoryInterface $repository,
        private readonly TaskValidator $validator = new TaskValidator(), // "new" en valeur par défaut : PHP 8.1
    ) {
    }

    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->repository->findAll());
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return new JsonResponse($this->findOr404((int) $id));
    }

    public function create(Request $request): JsonResponse
    {
        $data = $request->json();
        $this->validator->validate($data);

        $task = $this->repository->save(new Task(
            title: $data['title'],
            description: ($data['description'] ?? '') === '' ? null : $data['description'],
            status: TaskStatus::from($data['status'] ?? TaskStatus::Todo->value),
        ));

        return new JsonResponse($task, 201, ['Location' => "/api/tasks/$task->id"]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $task = $this->findOr404((int) $id);
        $data = $request->json();
        $this->validator->validate($data, partial: true);

        if (array_key_exists('title', $data)) {
            $task->title = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $task->description = $data['description'] === '' ? null : $data['description'];
        }
        if (array_key_exists('status', $data)) {
            $task->status = TaskStatus::from($data['status']);
        }

        return new JsonResponse($this->repository->save($task));
    }

    public function delete(Request $request, string $id): JsonResponse
    {
        if (!$this->repository->delete((int) $id)) {
            throw new HttpException(404, "Task $id not found");
        }

        return new JsonResponse(status: 204);
    }

    private function findOr404(int $id): Task
    {
        return $this->repository->find($id) ?? throw new HttpException(404, "Task $id not found");
    }
}
