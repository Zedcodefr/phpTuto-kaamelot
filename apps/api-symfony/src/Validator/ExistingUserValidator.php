<?php

declare(strict_types=1);

namespace App\Validator;

use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ExistingUserValidator extends ConstraintValidator
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * @param ExistingUser $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        if (null === $this->users->find($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
