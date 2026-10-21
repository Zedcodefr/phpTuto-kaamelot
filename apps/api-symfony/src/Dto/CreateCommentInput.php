<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO d'entrée de POST /api/tasks/{id}/comments : { "content" } de 1 à 1000 caractères.
 */
final readonly class CreateCommentInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 1000)]
        public string $content = '',
    ) {
    }
}
