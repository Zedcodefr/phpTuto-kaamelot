<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * A2 — Le validateur : un service comme un autre (autoconfiguré, il pourrait recevoir des dépendances).
 */
final class NotShoutingValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotShouting) {
            throw new UnexpectedTypeException($constraint, NotShouting::class);
        }
        // null et "" : ce n'est pas notre rôle, NotBlank s'en charge (une contrainte = une règle)
        if (null === $value || '' === $value) {
            return;
        }
        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        // \p{L} = n'importe quelle lettre Unicode (é, ç...), \p{Ll} = une lettre minuscule
        $letters = preg_match_all('/\p{L}/u', $value);
        if ($letters >= $constraint->minLetters && !preg_match('/\p{Ll}/u', $value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->addViolation();
        }
    }
}
