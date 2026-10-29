<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO d'entrée de POST /api/auth/login.
 * Volontairement sans Length ni Email : un login ne doit rien révéler des règles d'inscription.
 */
final readonly class LoginInput
{
    public function __construct(
        #[Assert\NotBlank]
        public string $email = '',

        #[Assert\NotBlank]
        public string $password = '',
    ) {
    }
}
