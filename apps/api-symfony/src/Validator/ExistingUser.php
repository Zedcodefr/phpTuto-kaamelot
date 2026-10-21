<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * L'id doit désigner un utilisateur existant (null est accepté : "personne"). Sur le modèle d'ExistingProject (B1).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class ExistingUser extends Constraint
{
    public string $message = 'User {{ id }} does not exist.';
}
