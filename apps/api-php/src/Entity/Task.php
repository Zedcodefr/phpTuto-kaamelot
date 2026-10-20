<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TaskStatus;

/**
 * Une tâche. Propriétaire : 🅱️ Lot B. Le constructeur est FOURNI (contrat entre les lots).
 *
 * JsonSerializable : json_encode($task) appellera automatiquement jsonSerialize().
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
     * 🅱️ LOT B — Construit une Task à partir d'une ligne SQL (tableau associatif renvoyé par PDO).
     * Colonnes : id, title, description, status, created_at.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        throw new \LogicException('TODO Lot B : Task::fromRow()');
    }

    /**
     * 🅱️ LOT B — Forme JSON définie par le contrat d'API :
     * id, title, description, status (valeur string), createdAt (format DATE_ATOM, ex. 2026-10-15T09:30:00+02:00)
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        throw new \LogicException('TODO Lot B : Task::jsonSerialize()');
    }
}
