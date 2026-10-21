<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Le nom de projet ne doit pas être déjà pris (UniqueEntity ne s'applique qu'aux entités, pas aux DTO).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class UniqueProjectName extends Constraint
{
    public string $message = 'This project name is already used.';
}
