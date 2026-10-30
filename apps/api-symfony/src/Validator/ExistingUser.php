<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * La valeur (un id) doit correspondre à un User existant. null est accepté : « pas d'utilisateur ».
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ExistingUser extends Constraint
{
    public string $message = 'This user does not exist.';
}
