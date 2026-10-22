<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\TaskStatus;
use App\Repository\TaskRepository;

/**
 * A1 — Un service : aucune déclaration, l'autowiring le rend injectable partout (contrôleur, commande...).
 * Réutilisé par A5 : la logique n'est écrite qu'une fois, le contrôleur et la commande ne font que l'exposer.
 */
final readonly class TaskStats
{
    public function __construct(private TaskRepository $tasks)
    {
    }

    /**
     * Quatre COUNT simples (un par statut + le total). Une seule requête GROUP BY ferait mieux : voir le bonus.
     *
     * @return array{total: int, todo: int, in_progress: int, done: int}
     */
    public function compute(): array
    {
        $stats = ['total' => $this->tasks->count([])];
        foreach (TaskStatus::cases() as $status) {
            $stats[$status->value] = $this->tasks->count(['status' => $status]);
        }

        return $stats;
    }
}
