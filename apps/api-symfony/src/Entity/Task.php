<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité Doctrine ≈ entité Entity Framework.
 * Le mapping se fait par attributs PHP 8 (≈ DataAnnotations / Fluent API).
 *
 * Pas de setters publics "bêtes" : on expose des méthodes métier,
 * l'objet garantit lui-même sa cohérence.
 */
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task')]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(length: 20, enumType: TaskStatus::class)]
    private TaskStatus $status;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $title, ?string $description = null, TaskStatus $status = TaskStatus::Todo)
    {
        $this->title = $title;
        $this->description = $description;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function rename(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function describe(?string $description): void
    {
        $this->description = $description;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function changeStatus(TaskStatus $status): void
    {
        $this->status = $status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
