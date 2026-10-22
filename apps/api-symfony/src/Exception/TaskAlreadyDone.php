<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * A4 — Une exception MÉTIER : elle ne connaît pas HTTP (l'entité peut la lever depuis une commande, un test...).
 * L'attribut dit seulement à Symfony quel code renvoyer si elle remonte jusqu'au kernel : 409 au lieu de 500.
 */
#[WithHttpStatus(Response::HTTP_CONFLICT)]
final class TaskAlreadyDone extends \DomainException
{
    public function __construct(?int $taskId)
    {
        parent::__construct(\sprintf('Task %d is already done', $taskId));
    }
}
