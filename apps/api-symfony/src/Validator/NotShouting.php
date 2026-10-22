<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
final class NotShouting extends Constraint
{
    public string $message = 'Le titre ne doit pas être écrit entièrement en majuscules.';
}
