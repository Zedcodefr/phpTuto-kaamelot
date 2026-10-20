<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;

/**
 * 🅱️ LOT B — Accès aux tâches en base avec PDO.
 * Toujours des requêtes PRÉPARÉES : jamais de valeur concaténée dans le SQL.
 * Astuce PostgreSQL : "INSERT ... RETURNING *" renvoie la ligne insérée.
 */
final class TaskRepository implements TaskRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function findAll(): array
    {
        throw new \LogicException('TODO Lot B : TaskRepository::findAll()');
    }

    public function find(int $id): ?Task
    {
        throw new \LogicException('TODO Lot B : TaskRepository::find()');
    }

    public function save(Task $task): Task
    {
        throw new \LogicException('TODO Lot B : TaskRepository::save()');
    }

    public function delete(int $id): bool
    {
        throw new \LogicException('TODO Lot B : TaskRepository::delete()');
    }
}
