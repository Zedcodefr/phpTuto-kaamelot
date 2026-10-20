<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Enum "backed" : chaque cas porte une valeur scalaire (ici une string),
 * c'est elle qui est stockée en base et exposée en JSON.
 * Équivalent C# : enum TaskStatus + [JsonStringEnumConverter].
 */
enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';
}
