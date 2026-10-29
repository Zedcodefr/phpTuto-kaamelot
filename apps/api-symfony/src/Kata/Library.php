<?php

declare(strict_types=1);

namespace App\Kata;

use App\Kata\Entity\Author;
use App\Kata\Entity\Book;
use App\Kata\Entity\Review;
use App\Kata\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;

/**
 * KATA DOCTRINE — Unit of Work (persist / flush / remove / change tracking) — À COMPLÉTER.
 * Lancez `make sf-kata` (ou `make sf-kata c="--filter=K05"`) et faites passer les tests un par un.
 * Expérimentez vos requêtes avec `make dql q="SELECT ..."` et regardez le SQL généré dans le profiler.
 */
final class Library
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * K17 — Crée et enregistre un livre avec ses étiquettes ; retourne le livre (avec son id).
     * Une étiquette qui existe déjà (même nom) est RÉUTILISÉE, sinon elle est créée.
     * Notions : persist() ("suivre cet objet") puis flush() ("exécuter le SQL"), getRepository()->findOneBy().
     * Question : faut-il persist() les Tag ? Pourquoi ? (regarder les options cascade du mapping de Book::$tags)
     */
    public function k17AddBook(string $title, Author $author, \DateTimeImmutable $publishedAt, int $pages, string ...$tagNames): Book
    {
        $book = new Book($title, $author, $publishedAt, $pages);

        foreach (array_unique($tagNames) as $name) {
            $tag = $this->em->getRepository(Tag::class)->findOneBy(['name' => $name]);
            if (null === $tag) {
                $tag = new Tag($name);
                // Pas de cascade sur Book::$tags : un nouveau Tag doit être persisté explicitement.
                $this->em->persist($tag);
            }
            $book->addTag($tag);
        }

        $this->em->persist($book);
        $this->em->flush();

        return $book;
    }

    /**
     * K18 — Ajoute une critique à un livre déjà en base ; retourne la critique (avec son id).
     * Notions : cascade: ['persist'] sur Book::$reviews — quel appel devient inutile ?
     */
    public function k18AddReview(Book $book, int $rating, string $comment): Review
    {
        $review = new Review($book, $rating, $comment);
        // cascade: ['persist'] sur Book::$reviews : le persist($review) est inutile, le livre est déjà managed.
        $book->addReview($review);
        $this->em->flush();

        return $review;
    }

    /**
     * K19 — Renomme l'étiquette $oldName en $newName (InvalidArgumentException si elle n'existe pas).
     * Notions : une entité chargée est "managed" : il suffit de la modifier puis flush(). persist() est inutile ici.
     */
    public function k19RenameTag(string $oldName, string $newName): void
    {
        $tag = $this->em->getRepository(Tag::class)->findOneBy(['name' => $oldName]);
        if (null === $tag) {
            throw new \InvalidArgumentException(sprintf('Étiquette "%s" introuvable.', $oldName));
        }

        // Entité "managed" : Doctrine détecte la modification, pas de persist() nécessaire.
        $tag->rename($newName);
        $this->em->flush();
    }

    /**
     * K20 — Transfère tous les livres de $from vers $to ; retourne le nombre de livres transférés.
     * Notions : côté PROPRIÉTAIRE vs côté INVERSE. Modifier $from->getBooks() ne change RIEN en base :
     * seul Book::$author (le côté qui porte la clé étrangère) est écrit par Doctrine.
     */
    public function k20TransferBooks(Author $from, Author $to): int
    {
        // Copie : setAuthor() retire le livre de $from->getBooks() pendant la boucle.
        $books = $from->getBooks()->toArray();

        foreach ($books as $book) {
            // Book::$author est le côté propriétaire : c'est lui qui est écrit en base.
            $book->setAuthor($to);
        }

        $this->em->flush();

        return count($books);
    }

    /**
     * K21 — Supprime toutes les critiques d'un livre en les RETIRANT de sa collection ; retourne leur nombre.
     * Notions : orphanRemoval: true sur Book::$reviews → retirer de la collection = DELETE au flush.
     * Interdit pour ce kata : remove() et les requêtes DELETE.
     */
    public function k21DeleteReviewsOfBook(Book $book): int
    {
        // Copie : removeReview() modifie la collection pendant la boucle.
        $reviews = $book->getReviews()->toArray();

        foreach ($reviews as $review) {
            // orphanRemoval: true : une critique retirée de la collection est supprimée au flush.
            $book->removeReview($review);
        }

        $this->em->flush();

        return count($reviews);
    }
}
