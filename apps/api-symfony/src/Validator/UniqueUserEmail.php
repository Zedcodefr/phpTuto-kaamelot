<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * L'email ne doit pas déjà être utilisé. Contrainte sur le DTO (UniqueEntity ne marche que sur une entité).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class UniqueUserEmail extends Constraint
{
    public string $message = 'This email is already used.';
}
