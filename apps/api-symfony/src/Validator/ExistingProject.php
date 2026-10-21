<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * L'id doit désigner un projet existant (null est accepté : "pas de projet").
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class ExistingProject extends Constraint
{
    public string $message = 'Project {{ id }} does not exist.';
}
