<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Corps de POST /api/auth/login. Volontairement SANS contraintes : le contrat ne prévoit que 200 ou 401,
 * un champ manquant donne donc des identifiants vides... et un 401.
 */
final readonly class LoginInput
{
    public function __construct(
        public string $email = '',
        #[\SensitiveParameter]
        public string $password = '',
    ) {
    }
}
