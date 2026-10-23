<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\TaskRepository;
use App\Enum\TaskStatus;
use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;

final class TaskStats
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        // private readonly EntityManagerInterface $em
    )
    {
    }

    public function compute(): array
    {
        // $task1 = new Task('Tâche 1', 'La tâche 1', TaskStatus::Todo);
        // $task2 = new Task('Tâche 2', 'La tâche 2', TaskStatus::Todo);
        // $task3 = new Task('Tâche 3', 'La tâche 3', TaskStatus::InProgress);
        // $task4 = new Task('Tâche 4', 'La tâche 4', TaskStatus::Done);

        // $this->em->persist($task1);

        $count = $this->taskRepository->count();

        $cases = TaskStatus::cases();
        $list = [];

        array_push($list, ['total' => $count]);

        foreach($cases as $case)
        {
            $count = $this->taskRepository->count(['status' => $case]);
            array_push($list, [$case->value => $count]);
        }
        return $list;
    }
}
