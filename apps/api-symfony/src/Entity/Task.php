<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Entité Doctrine ≈ entité Entity Framework.
 * Le mapping se fait par attributs PHP 8 (≈ DataAnnotations / Fluent API).
 *
 * Pas de setters publics "bêtes" : on expose des méthodes métier,
 * l'objet garantit lui-même sa cohérence.
 *
 * Sérialisation : groupe "task:read" (décision d'équipe B1-3). Tout getter exposé dans le JSON
 * d'une tâche doit porter #[Groups(['task:read'])], y compris sur les objets imbriqués (Project, User).
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

    /** B1-3 — Côté PROPRIÉTAIRE de la relation : la colonne project_id est dans la table task. */
    #[ORM\ManyToOne(inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Project $project = null;

    /**
     * B2-4a — Auteur (renseigné à la création) et assigné : deux ManyToOne UNIDIRECTIONNELS vers User
     * (pas de collections inverses createdTasks / assignedTasks : inutiles ici).
     * null = tâche créée avant l'authentification / non assignée. Supprimer un utilisateur ne supprime pas ses tâches.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignee = null;

    public function __construct(string $title, ?string $description = null, TaskStatus $status = TaskStatus::Todo, ?Project $project = null, ?User $author = null)
    {
        $this->author = $author;
        $this->title = $title;
        $this->description = $description;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
        $this->moveToProject($project);
    }

    #[Groups(['task:read'])]
    public function getId(): ?int
    {
        return $this->id;
    }

    #[Groups(['task:read'])]
    public function getTitle(): string
    {
        return $this->title;
    }

    public function rename(string $title): void
    {
        $this->title = $title;
    }

    #[Groups(['task:read'])]
    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function describe(?string $description): void
    {
        $this->description = $description;
    }

    #[Groups(['task:read'])]
    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function changeStatus(TaskStatus $status): void
    {
        $this->status = $status;
    }

    #[Groups(['task:read'])]
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Dans le JSON d'une tâche, seuls id et name du projet sont exposés (groupe task:read). */
    #[Groups(['task:read'])]
    public function getProject(): ?Project
    {
        return $this->project;
    }

    /** B2-4a — Forme User {id, email, displayName} grâce au groupe task:read posé sur User. */
    #[Groups(['task:read'])]
    public function getAuthor(): ?User
    {
        return $this->author;
    }

    #[Groups(['task:read'])]
    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    /** Assigne la tâche (ou la désassigne avec null). */
    public function assignTo(?User $assignee): void
    {
        $this->assignee = $assignee;
    }

    /**
     * Rattache la tâche à un projet (ou la détache avec null).
     * Met à jour le côté propriétaire (écrit en base) ET le côté inverse en mémoire (Project::$tasks).
     */
    public function moveToProject(?Project $project): void
    {
        if ($this->project === $project) {
            return;
        }
        $this->project?->syncTask($this, false);
        $this->project = $project;
        $project?->syncTask($this, true);
    }
}
