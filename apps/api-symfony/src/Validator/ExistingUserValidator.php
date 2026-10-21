<?php

declare(strict_types=1);

namespace App\Validator;

use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ExistingUserValidator extends ConstraintValidator
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ExistingUser) {
            throw new UnexpectedTypeException($constraint, ExistingUser::class);
        }
        if (null === $value) {
            return;
        }
        // find() alimente l'Identity Map : le find() du contrôleur juste après ne refait pas de requête.
        if (!\is_int($value) || null === $this->users->find($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ id }}', (string) $value)
                ->addViolation();
        }
    }
}
