<?php

declare(strict_types=1);

namespace App\Kata\Entity;

use App\Kata\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * KATA DOCTRINE — entité fournie, à LIRE (mapping) mais pas à modifier.
 */
#[ORM\Entity(repositoryClass: BookRepository::class)]
#[ORM\Table(name: 'kata_book')]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Côté PROPRIÉTAIRE : la colonne author_id est dans la table kata_book. */
    #[ORM\ManyToOne(inversedBy: 'books')]
    #[ORM\JoinColumn(nullable: false)]
    private Author $author;

    #[ORM\Column]
    private bool $outOfPrint = false;

    /**
     * cascade persist : persister le livre persiste aussi les nouvelles critiques ajoutées.
     * orphanRemoval : une critique retirée de la collection est supprimée en base.
     *
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'book', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $reviews;

    /**
     * ManyToMany : table de jointure kata_book_tag générée automatiquement.
     * Book est le côté propriétaire (pas de mappedBy).
     *
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'books')]
    #[ORM\JoinTable(name: 'kata_book_tag')]
    private Collection $tags;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $title,

        Author $author,

        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $publishedAt,

        #[ORM\Column]
        private int $pages,
    ) {
        $this->reviews = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->setAuthor($author);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthor(): Author
    {
        return $this->author;
    }

    /** Met à jour les DEUX côtés de la relation (sinon $author->getBooks() serait faux en mémoire). */
    public function setAuthor(Author $author): void
    {
        if (isset($this->author)) {
            $this->author->syncBook($this, false);
        }
        $this->author = $author;
        $author->syncBook($this, true);
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getPages(): int
    {
        return $this->pages;
    }

    public function isOutOfPrint(): bool
    {
        return $this->outOfPrint;
    }

    public function setOutOfPrint(bool $outOfPrint): void
    {
        $this->outOfPrint = $outOfPrint;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): void
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
        }
    }

    public function removeReview(Review $review): void
    {
        $this->reviews->removeElement($review);
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): void
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->syncBook($this, true);
        }
    }
}
