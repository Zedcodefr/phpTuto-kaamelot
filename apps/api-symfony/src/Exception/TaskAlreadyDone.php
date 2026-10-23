<?php

declare(strict_types=1);

namespace App\Exception;

use DomainException;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
class TaskAlreadyDone extends DomainException {}
