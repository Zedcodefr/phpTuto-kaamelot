<?php

namespace App\Validator;

use App\Repository\ProjectRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class UniqueNameValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly RequestStack $requestStack)
    {

    }
    /**
     * @param UniqueName $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }
        $existing = $this->projectRepository->findOneBy(['name' => $value]);
        $currentId = $this->requestStack->getCurrentRequest()?->attributes->get('id'); // null en POST, "3" (string) en PATCH /api/projects/3
        if (null === $existing || (int) $currentId === $existing->getId()) {
            return; // nom libre, ou c'est le projet en cours de modification
        }
        $this->context->buildViolation($constraint->message)->addViolation();
    }
}
