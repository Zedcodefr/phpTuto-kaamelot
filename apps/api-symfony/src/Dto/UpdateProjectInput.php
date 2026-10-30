<?php

declare(strict_types=1);

namespace App\Dto;

use App\Validator\UniqueName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO d'entrée de PATCH /api/projects/{id} : tous les champs sont facultatifs.
 * null = "champ absent du JSON, on ne touche pas" (envoyer "" pour vider description).
 */
final readonly class UpdateProjectInput
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(min: 3, max: 100)]
        #[UniqueName]
        public ?string $name = null,

        #[Assert\Length(max: 2000)]
        public ?string $description = null,
    ) {
    }
}
