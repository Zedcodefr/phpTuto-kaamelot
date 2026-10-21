<?php

declare(strict_types=1);

namespace App\Kata;

use App\Kata\Entity\Author;
use App\Kata\Entity\Book;
use App\Kata\Entity\Review;
use App\Kata\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;

/**
 * KATA DOCTRINE — Unit of Work (persist / flush / remove / change tracking) — SOLUTION.
 */
final class Library
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function k17AddBook(string $title, Author $author, \DateTimeImmutable $publishedAt, int $pages, string ...$tagNames): Book
    {
        $book = new Book($title, $author, $publishedAt, $pages);

        foreach ($tagNames as $name) {
            $tag = $this->em->getRepository(Tag::class)->findOneBy(['name' => $name]);
            if (null === $tag) {
                $tag = new Tag($name);
                $this->em->persist($tag);
            }
            $book->addTag($tag);
        }

        $this->em->persist($book);
        $this->em->flush();

        return $book;
    }

    public function k18AddReview(Book $book, int $rating, string $comment): Review
    {
        $review = new Review($book, $rating, $comment);
        $this->em->flush(); // cascade persist depuis Book (déjà géré) : pas besoin de persist($review)

        return $review;
    }

    public function k19RenameTag(string $oldName, string $newName): void
    {
        $tag = $this->em->getRepository(Tag::class)->findOneBy(['name' => $oldName])
            ?? throw new \InvalidArgumentException("Tag \"$oldName\" introuvable");

        $tag->rename($newName);
        $this->em->flush(); // pas de persist : l'entité est déjà "managed", le changement est détecté
    }

    public function k20TransferBooks(Author $from, Author $to): int
    {
        $books = $from->getBooks()->toArray();
        foreach ($books as $book) {
            $book->setAuthor($to); // côté propriétaire
        }
        $this->em->flush();

        return count($books);
    }

    public function k21DeleteReviewsOfBook(Book $book): int
    {
        $count = $book->getReviews()->count();
        foreach ($book->getReviews()->toArray() as $review) {
            $book->removeReview($review); // orphanRemoval => DELETE au flush
        }
        $this->em->flush();

        return $count;
    }
}
