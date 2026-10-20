<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO d'entrée de PATCH /api/tasks/{id} : tous les champs sont facultatifs.
 * null = "champ absent du JSON, on ne touche pas".
 *
 * Limite assumée : on ne peut donc pas remettre description à null via PATCH
 * (on envoie "" à la place). Voir docs/02-symfony pour des solutions plus fines.
 */
final readonly class UpdateTaskInput
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(min: 3, max: 255)]
        public ?string $title = null,

        #[Assert\Length(max: 2000)]
        public ?string $description = null,

        public ?TaskStatus $status = null,
    ) {
    }
}
