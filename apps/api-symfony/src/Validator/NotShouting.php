<?php
declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class NotShouting extends Constraint
{
    public $message = 'Le titre ne doit pas être écrit entièrement en majuscule.';

}
