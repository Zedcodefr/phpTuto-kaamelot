<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Un projet regroupe des tâches (contrat d'API, partie 2A).
 *
 * Sérialisation par groupes :
 *   - project:read → l'objet Project complet (GET /api/projects...)
 *   - task:read    → seulement {id, name}, quand le projet est imbriqué dans une Task
 */
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'project')]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Côté INVERSE (mappedBy) : Doctrine l'ignore au flush, c'est Task::$project qui compte.
     * EXTRA_LAZY : $this->tasks->count() fait un SELECT COUNT(*) sans charger les tâches.
     * Pas de groupe de sérialisation : jamais exporté (sinon task → project → tasks → … boucle).
     *
     * @var Collection<int, Task>
     */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'project', fetch: 'EXTRA_LAZY')]
    private Collection $tasks;

    /** Non mappé : nombre de tâches pré-calculé par une requête groupée (évite un COUNT par projet). */
    private ?int $knownTaskCount = null;

    public function __construct(string $name, ?string $description = null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->createdAt = new \DateTimeImmutable();
        $this->tasks = new ArrayCollection();
    }

    #[Groups(['project:read', 'task:read'])]
    public function getId(): ?int
    {
        return $this->id;
    }

    #[Groups(['project:read', 'task:read'])]
    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    #[Groups(['project:read'])]
    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function describe(?string $description): void
    {
        $this->description = $description;
    }

    #[Groups(['project:read'])]
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    /** Calculé (contrat) : nombre de tâches du projet. */
    #[Groups(['project:read'])]
    public function getTaskCount(): int
    {
        return $this->knownTaskCount ?? $this->tasks->count();
    }

    /** @internal renseigné par ProjectRepository::findAllOrderedByName() (COUNT groupé, une seule requête) */
    public function setKnownTaskCount(int $count): void
    {
        $this->knownTaskCount = $count;
    }

    /** @internal appelé par Task::moveToProject() pour garder le côté inverse cohérent en mémoire */
    public function syncTask(Task $task, bool $add): void
    {
        $this->knownTaskCount = null;
        if ($add && !$this->tasks->contains($task)) {
            $this->tasks->add($task);
        } elseif (!$add) {
            $this->tasks->removeElement($task);
        }
    }
}
