<?php
declare(strict_types=1);

namespace App\Service;

use App\Enum\TaskStatus;
use App\Repository\TaskRepository;

class TaskStats
{

    public function __construct(private readonly TaskRepository $repository)
    {
    }

    /**
     * @return array<string, int> statut => nombre de tâches (0 si aucune)
     */
    public function compute(): array
    {
        $stats = ['total' => $this->repository->count([])];

        foreach (TaskStatus::cases() as $status) {
            $stats[$status->value] = $this->repository->count(['status' => $status]);
        }
        return $stats;
    }
}
