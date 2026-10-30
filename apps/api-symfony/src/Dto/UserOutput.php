<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;

/**
 * Représentation JSON d'un User dans l'API : le seul endroit qui décide quels champs sortent.
 * Le mot de passe (hash) et les rôles n'en font volontairement pas partie.
 */
final readonly class UserOutput
{
    public function __construct(
        public ?int $id,
        public string $email,
        public string $displayName,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self($user->getId(), $user->getEmail(), $user->getDisplayName());
    }
}
