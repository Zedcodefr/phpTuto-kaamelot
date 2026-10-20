<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO d'entrée de POST /api/tasks.
 * Rempli automatiquement depuis le JSON par #[MapRequestPayload] (≈ [FromBody] + model binding)
 * puis validé (≈ DataAnnotations + ModelState) : en cas d'erreur → 422 automatique.
 */
final readonly class CreateTaskInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 255)]
        public string $title = '',

        #[Assert\Length(max: 2000)]
        public ?string $description = null,

        public TaskStatus $status = TaskStatus::Todo,
    ) {
    }
}
