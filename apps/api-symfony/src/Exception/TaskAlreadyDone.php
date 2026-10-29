<?php

declare(strict_types=1);

namespace App\Exception;

use DomainException;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class TaskAlreadyDone extends DomainException
{
    public function __construct(string $message = "Task is already done.", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
