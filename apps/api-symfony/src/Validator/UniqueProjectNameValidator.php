<?php

declare(strict_types=1);

namespace App\Validator;

use App\Repository\ProjectRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Service autowiré (≈ un ValidationAttribute qui aurait accès au DbContext).
 *
 * En PATCH /api/projects/{id}, garder son propre nom est autorisé : on ignore le projet dont l'id
 * est dans la route courante.
 */
final class UniqueProjectNameValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueProjectName) {
            throw new UnexpectedTypeException($constraint, UniqueProjectName::class);
        }
        if (null === $value || '' === $value) {
            return; // NotBlank s'en charge
        }

        $existing = $this->projects->findOneBy(['name' => $value]);
        if (null === $existing) {
            return;
        }

        $currentId = $this->requestStack->getCurrentRequest()?->attributes->get('id');
        if (null !== $currentId && (int) $currentId === $existing->getId()) {
            return; // c'est le projet en cours de modification
        }

        $this->context->buildViolation($constraint->message)->addViolation();
    }
}
