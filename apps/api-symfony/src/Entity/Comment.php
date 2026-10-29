<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * Relation ManyToOne unidirectionnelle vers Task : pas de Task::$comments.
 * Les commentaires d'une tâche se lisent par une requête dans CommentRepository.
 */
#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(name: 'comment')]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    // onDelete: 'CASCADE' => c'est la base qui supprime les commentaires d'une tâche supprimée
    // (orphanRemoval exigerait le côté inverse Task::$comments)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Task $task;

    public function __construct(Task $task, string $content)
    {
        $this->task = $task;
        $this->content = $content;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // Pas dans le JSON : le contrat ne renvoie pas la tâche dans un Comment
    #[Ignore]
    public function getTask(): Task
    {
        return $this->task;
    }
}
