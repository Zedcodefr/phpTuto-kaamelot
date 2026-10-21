<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;

/**
 * Accès aux données des tâches. Toujours des requêtes PRÉPARÉES
 * (paramètres :xxx) : jamais de concaténation de valeurs dans le SQL (injection SQL !).
 */
final class TaskRepository implements TaskRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return list<Task>
     */
    public function findAll(): array
    {
        $rows = $this->pdo->query('SELECT * FROM task ORDER BY id')->fetchAll();

        return array_map(Task::fromRow(...), $rows);
    }

    public function find(int $id): ?Task
    {
        $statement = $this->pdo->prepare('SELECT * FROM task WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : Task::fromRow($row);
    }

    /**
     * Insère ou met à jour selon que la tâche a déjà un id.
     */
    public function save(Task $task): Task
    {
        $params = [
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status->value,
        ];

        if ($task->id === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO task (title, description, status) VALUES (:title, :description, :status) RETURNING *'
            );
        } else {
            $statement = $this->pdo->prepare(
                'UPDATE task SET title = :title, description = :description, status = :status WHERE id = :id RETURNING *'
            );
            $params['id'] = $task->id;
        }

        $statement->execute($params);

        return Task::fromRow($statement->fetch());
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM task WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }
}
