<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TaskStatus;

/**
 * JsonSerializable : json_encode() appellera jsonSerialize() automatiquement.
 * C'est ici qu'on décide de la forme JSON (camelCase, format des dates...).
 */
final class Task implements \JsonSerializable
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public TaskStatus $status = TaskStatus::Todo,
        public ?\DateTimeImmutable $createdAt = null,
        public ?int $id = null,
    ) {
    }

    /**
     * Construit une Task à partir d'une ligne SQL (tableau associatif renvoyé par PDO).
     *
     * @param array{id: int, title: string, description: ?string, status: string, created_at: string} $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            title: $row['title'],
            description: $row['description'],
            status: TaskStatus::from($row['status']),
            createdAt: new \DateTimeImmutable($row['created_at']),
            id: (int) $row['id'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'createdAt' => $this->createdAt?->format(DATE_ATOM),
        ];
    }
}
