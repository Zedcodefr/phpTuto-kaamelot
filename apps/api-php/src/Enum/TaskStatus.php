<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * FOURNI (utilisé par les trois lots). Enum "backed" : chaque cas porte une valeur string,
 * c'est elle qui est stockée en base et exposée en JSON.
 *
 *   TaskStatus::from('done')     → TaskStatus::Done (exception si valeur inconnue)
 *   TaskStatus::tryFrom('nope')  → null
 *   TaskStatus::Done->value      → 'done'
 */
enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    /**
     * @return list<string> ['todo', 'in_progress', 'done']
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
