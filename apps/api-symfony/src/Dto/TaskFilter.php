<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TaskFilter
{
    public function __construct(
        public ?TaskStatus $status = null,
        //public ?int $projectId = null,
        public ?string $search = null,
        #[Assert\Positive] public int $page = 1,
        #[Assert\Range(min: 1, max: 100)] public int $limit = 50,
    ) {}
}
