<?php

declare(strict_types=1);

namespace App\Validator;

use App\Repository\ProjectRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ExistingProjectValidator extends ConstraintValidator
{
    public function __construct(private readonly ProjectRepository $projects)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ExistingProject) {
            throw new UnexpectedTypeException($constraint, ExistingProject::class);
        }
        if (null === $value) {
            return;
        }

        // find() passe par l'Identity Map : le contrôleur qui relit ensuite ce projet ne refait pas de requête
        if (!\is_int($value) || null === $this->projects->find($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ id }}', (string) $value)
                ->addViolation();
        }
    }
}
