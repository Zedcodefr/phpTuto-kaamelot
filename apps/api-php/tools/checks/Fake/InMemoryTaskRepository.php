<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Entity\Task;
use App\Repository\TaskRepositoryInterface;

/**
 * Faux repository en mémoire ("test double", ≈ un mock / un InMemory DbContext en C#).
 * Permet au Lot C de tester son contrôleur sans base de données ni code du Lot B.
 */
final class InMemoryTaskRepository implements TaskRepositoryInterface
{
    /** @var array<int, Task> */
    private array $tasks = [];
    private int $nextId = 1;

    public function findAll(): array
    {
        ksort($this->tasks);

        return array_values(array_map(fn (Task $t): Task => clone $t, $this->tasks));
    }

    public function find(int $id): ?Task
    {
        return isset($this->tasks[$id]) ? clone $this->tasks[$id] : null;
    }

    public function save(Task $task): Task
    {
        $task->id ??= $this->nextId++;
        $task->createdAt ??= new \DateTimeImmutable();
        $this->tasks[$task->id] = clone $task;

        return clone $task;
    }

    public function delete(int $id): bool
    {
        if (!isset($this->tasks[$id])) {
            return false;
        }
        unset($this->tasks[$id]);

        return true;
    }
}
