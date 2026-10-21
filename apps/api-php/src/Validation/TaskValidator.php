<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enum\TaskStatus;
use App\Http\ValidationException;

/**
 * Valide les données reçues en JSON. Messages identiques à ceux de Symfony
 * pour que le front se comporte pareil avec les deux API.
 */
final class TaskValidator
{
    /**
     * @param array<string, mixed> $data
     * @param bool $partial true pour un PATCH : seuls les champs présents sont validés
     *
     * @throws ValidationException
     */
    public function validate(array $data, bool $partial = false): void
    {
        $errors = [];

        if (!$partial || array_key_exists('title', $data)) {
            $title = $data['title'] ?? null;
            if (!is_string($title) || trim($title) === '') {
                $errors['title'] = 'This value should not be blank.';
            } elseif (mb_strlen($title) < 3 || mb_strlen($title) > 255) {
                $errors['title'] = 'This value should have between 3 and 255 characters.';
            }
        }

        if (isset($data['description'])) {
            if (!is_string($data['description'])) {
                $errors['description'] = 'This value should be of type string.';
            } elseif (mb_strlen($data['description']) > 2000) {
                $errors['description'] = 'This value is too long. It should have 2000 characters or less.';
            }
        }

        if (array_key_exists('status', $data) && TaskStatus::tryFrom((string) $data['status']) === null) {
            $errors['status'] = 'The value should be one of: ' . implode(', ', TaskStatus::values()) . '.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
