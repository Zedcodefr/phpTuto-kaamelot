<?php

declare(strict_types=1);

namespace App\Kata\Entity;

use App\Kata\Repository\AuthorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * KATA DOCTRINE — entité fournie, à LIRE (mapping) mais pas à modifier.
 */
#[ORM\Entity(repositoryClass: AuthorRepository::class)]
#[ORM\Table(name: 'kata_author')]
class Author
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Côté INVERSE de la relation (mappedBy) : Doctrine ignore cette collection
     * quand il écrit en base. C'est Book::$author (côté PROPRIÉTAIRE) qui compte.
     *
     * @var Collection<int, Book>
     */
    #[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'author')]
    #[ORM\OrderBy(['title' => 'ASC'])]
    private Collection $books;

    public function __construct(
        #[ORM\Column(length: 100)]
        private string $name,

        #[ORM\Column(length: 50)]
        private string $country,
    ) {
        $this->books = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * @return Collection<int, Book>
     */
    public function getBooks(): Collection
    {
        return $this->books;
    }

    /**
     * @internal appelé par Book::setAuthor() pour garder les deux côtés synchronisés en mémoire
     */
    public function syncBook(Book $book, bool $add): void
    {
        if ($add && !$this->books->contains($book)) {
            $this->books->add($book);
        } elseif (!$add) {
            $this->books->removeElement($book);
        }
    }
}
