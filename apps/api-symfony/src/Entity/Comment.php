<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * Commentaire d'une tâche (contrat, partie 2C).
 *
 * Relation UNIDIRECTIONNELLE Comment → Task (côté propriétaire, colonne task_id) : pas de Task::$comments,
 * donc Task.php (fichier partagé) n'est pas touché. Les commentaires d'une tâche se retrouvent par requête
 * (CommentRepository). Supprimer une tâche supprime ses commentaires côté BASE (ON DELETE CASCADE).
 *
 * Sérialisation : groupe "comment:read" (décision d'équipe : groupes) → {id, content, createdAt, author}.
 */
#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(name: 'comment')]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Nullable : renseigné quand l'utilisateur est authentifié (B3-4) ; ON DELETE SET NULL si l'utilisateur disparaît. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Task $task,

        #[ORM\Column(type: Types::TEXT)]
        private string $content,
        ?User $author = null,
    ) {
        $this->author = $author;
        $this->createdAt = new \DateTimeImmutable();
    }

    #[Groups(['comment:read'])]
    public function getId(): ?int
    {
        return $this->id;
    }

    #[Groups(['comment:read'])]
    public function getContent(): string
    {
        return $this->content;
    }

    #[Groups(['comment:read'])]
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[Groups(['comment:read'])]
    public function getAuthor(): ?User
    {
        return $this->author;
    }

    /** Pas dans le JSON du contrat (et éviterait de sérialiser toute la tâche). */
    #[Ignore]
    public function getTask(): Task
    {
        return $this->task;
    }
}
