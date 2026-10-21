<?php

declare(strict_types=1);

namespace App\Dto;

use App\Validator\UniqueUserEmail;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de POST /api/auth/register.
 * Sequentially : on s'arrête à la première contrainte violée (pas de requête SQL d'unicité sur un email invalide,
 * et une seule violation par champ).
 */
final readonly class RegisterInput
{
    public function __construct(
        #[Assert\Sequentially([
            new Assert\NotBlank(),
            new Assert\Length(max: 180),
            new Assert\Email(),
            new UniqueUserEmail(),
        ])]
        public string $email = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 4096)]
        public string $password = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 50)]
        public string $displayName = '',
    ) {
    }
}
