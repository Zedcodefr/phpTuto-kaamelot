<?php

declare(strict_types=1);

namespace App\Kata\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * KATA DOCTRINE — entité fournie, à LIRE (mapping) mais pas à modifier.
 */
#[ORM\Entity]
#[ORM\Table(name: 'kata_tag')]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, Book> */
    #[ORM\ManyToMany(targetEntity: Book::class, mappedBy: 'tags')]
    private Collection $books;

    public function __construct(
        #[ORM\Column(length: 50, unique: true)]
        private string $name,
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

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return Collection<int, Book>
     */
    public function getBooks(): Collection
    {
        return $this->books;
    }

    /** @internal appelé par Book::addTag() */
    public function syncBook(Book $book, bool $add): void
    {
        if ($add && !$this->books->contains($book)) {
            $this->books->add($book);
        }
    }
}
