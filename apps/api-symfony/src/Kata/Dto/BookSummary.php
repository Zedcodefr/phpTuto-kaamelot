<?php

declare(strict_types=1);

namespace App\Kata\Dto;

/**
 * DTO de lecture, construit directement par Doctrine avec la syntaxe DQL "SELECT NEW" (kata K11).
 */
final readonly class BookSummary
{
    public function __construct(
        public string $title,
        public string $authorName,
        public int $reviewCount,
    ) {
    }
}
