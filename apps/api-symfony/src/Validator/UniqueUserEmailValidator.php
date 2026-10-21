<?php

declare(strict_types=1);

namespace App\Validator;

use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validateur = service (autowiring) : on peut y injecter le repository.
 */
final class UniqueUserEmailValidator extends ConstraintValidator
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueUserEmail) {
            throw new UnexpectedTypeException($constraint, UniqueUserEmail::class);
        }
        if (!\is_string($value) || '' === $value) {
            return; // NotBlank / Email s'en chargent
        }

        if (null !== $this->users->findOneByEmail($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
