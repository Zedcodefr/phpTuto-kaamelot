<?php

declare(strict_types=1);

namespace App\Http;

final class ValidationException extends HttpException
{
    /**
     * @param array<string, string> $errors propertyPath => message
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(422, 'Validation failed');
    }

    protected function toArray(): array
    {
        $violations = [];
        foreach ($this->errors as $propertyPath => $message) {
            $violations[] = ['propertyPath' => $propertyPath, 'title' => $message];
        }

        return [...parent::toArray(), 'violations' => $violations]; // spread sur un tableau associatif
    }
}
