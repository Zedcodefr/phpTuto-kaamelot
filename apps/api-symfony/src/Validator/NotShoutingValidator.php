<?php
declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class NotShoutingValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotShouting) {
            throw new UnexpectedTypeException($constraint, NotShouting::class);
        }

        // Vide ou absent : pas mon rôle, NotBlank s'en charge
        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $letters = preg_match_all('/\p{L}/u', $value);
        $hasLowercase = 1 === preg_match('/\p{Ll}/u', $value);

        if ($letters >= 5 && !$hasLowercase) {

            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
