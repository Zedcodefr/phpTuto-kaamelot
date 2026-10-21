<?php

declare(strict_types=1);

namespace App\Dto;

use App\Validator\UniqueProjectName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de POST /api/projects (validé par #[MapRequestPayload] → 422 automatique).
 */
final readonly class CreateProjectInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 100)]
        #[UniqueProjectName]
        public string $name = '',

        #[Assert\Length(max: 2000)]
        public ?string $description = null,
    ) {
    }
}
