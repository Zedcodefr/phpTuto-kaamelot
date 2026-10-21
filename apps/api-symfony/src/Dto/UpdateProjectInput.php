<?php

declare(strict_types=1);

namespace App\Dto;

use App\Validator\UniqueProjectName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de PATCH /api/projects/{id} : tout est facultatif (null = absent du JSON).
 * Pour "description": null explicite, le contrôleur regarde les clés du JSON brut.
 */
final readonly class UpdateProjectInput
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(min: 2, max: 100)]
        #[UniqueProjectName]
        public ?string $name = null,

        #[Assert\Length(max: 2000)]
        public ?string $description = null,
    ) {
    }
}
