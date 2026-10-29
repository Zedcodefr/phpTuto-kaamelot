<?php

declare(strict_types=1);

namespace App\Dto;

use App\Validator\UniqueName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO d'entrée de POST /api/projects.
 */
final readonly class CreateProjectInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 100)]
        #[UniqueName]
        public string $name = '',

        #[Assert\Length(max: 2000)]
        public ?string $description = null,
    ) {
    }
}
