<?php

declare(strict_types=1);

namespace App\Kata\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * KATA DOCTRINE — entité fournie, à LIRE (mapping) mais pas à modifier.
 */
#[ORM\Entity]
#[ORM\Table(name: 'kata_review')]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Le constructeur ajoute la critique à la collection du livre : les deux côtés sont cohérents.
     */
    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'reviews')]
        #[ORM\JoinColumn(nullable: false)]
        private Book $book,

        #[ORM\Column(type: Types::SMALLINT)]
        private int $rating,

        #[ORM\Column(type: Types::TEXT)]
        private string $comment,

        #[ORM\Column]
        private \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
        $book->addReview($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): Book
    {
        return $this->book;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
