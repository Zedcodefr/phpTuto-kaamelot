<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class NotShoutingValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if(mb_strlen($value) > 5 && !preg_match('/\p{Ll}/u', $value))
        {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
