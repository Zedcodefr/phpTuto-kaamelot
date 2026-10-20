<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;

/**
 * FOURNI. Contrat entre le Lot B (qui l'implémente avec PDO) et le Lot C (qui l'utilise dans le contrôleur).
 * ≈ une interface ITaskRepository injectée en C#.
 */
interface TaskRepositoryInterface
{
    /**
     * @return list<Task> triées par id croissant
     */
    public function findAll(): array;

    public function find(int $id): ?Task;

    /**
     * Insère la tâche si elle n'a pas d'id, la met à jour sinon.
     *
     * @return Task la tâche telle qu'enregistrée (avec id et createdAt renseignés)
     */
    public function save(Task $task): Task;

    /**
     * @return bool false si aucune tâche ne porte cet id
     */
    public function delete(int $id): bool;
}
