<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\TaskRepository;
use App\Enum\TaskStatus;

class TaskStats
{
    public function __construct(private readonly TaskRepository $repository){}

    public function compute(): array
    {
        $taskStats = ['total' => $this->repository->count([])];

        foreach (TaskStatus::cases() as $status){
            $taskStats[$status->value] = $this->repository->count(['status' => $status]);
        }

        return $taskStats;
    }
}
