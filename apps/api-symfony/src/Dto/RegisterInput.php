<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO d'entrée de POST /api/auth/register.
 * #[UniqueEntity] interroge la base : si un User porte déjà cet email → 422 sur "email".
 */
#[UniqueEntity(fields: ['email'], entityClass: User::class)]
final readonly class RegisterInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 8)]
        public string $password = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 50)]
        public string $displayName = '',
    ) {
    }
}
