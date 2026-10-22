<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NotShoutingValidator extends ConstraintValidator
{
    private const MIN_LETTERS = 5;

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotShouting) {
            throw new UnexpectedTypeException($constraint, NotShouting::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $letters = preg_match_all('/\p{L}/u', $value);
        $hasLowercase = 1 === preg_match('/\p{Ll}/u', $value);

        if ($letters >= self::MIN_LETTERS && !$hasLowercase) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
