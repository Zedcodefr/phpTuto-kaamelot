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
        throw new \LogicException('TODO K17');
    }

    /**
     * K18 — Ajoute une critique à un livre déjà en base ; retourne la critique (avec son id).
     * Notions : cascade: ['persist'] sur Book::$reviews — quel appel devient inutile ?
     */
    public function k18AddReview(Book $book, int $rating, string $comment): Review
    {
        throw new \LogicException('TODO K18');
    }

    /**
     * K19 — Renomme l'étiquette $oldName en $newName (InvalidArgumentException si elle n'existe pas).
     * Notions : une entité chargée est "managed" : il suffit de la modifier puis flush(). persist() est inutile ici.
     */
    public function k19RenameTag(string $oldName, string $newName): void
    {
        throw new \LogicException('TODO K19');
    }

    /**
     * K20 — Transfère tous les livres de $from vers $to ; retourne le nombre de livres transférés.
     * Notions : côté PROPRIÉTAIRE vs côté INVERSE. Modifier $from->getBooks() ne change RIEN en base :
     * seul Book::$author (le côté qui porte la clé étrangère) est écrit par Doctrine.
     */
    public function k20TransferBooks(Author $from, Author $to): int
    {
        throw new \LogicException('TODO K20');
    }

    /**
     * K21 — Supprime toutes les critiques d'un livre en les RETIRANT de sa collection ; retourne leur nombre.
     * Notions : orphanRemoval: true sur Book::$reviews → retirer de la collection = DELETE au flush.
     * Interdit pour ce kata : remove() et les requêtes DELETE.
     */
    public function k21DeleteReviewsOfBook(Book $book): int
    {
        throw new \LogicException('TODO K21');
    }
}
