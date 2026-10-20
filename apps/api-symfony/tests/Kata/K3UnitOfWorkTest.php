<?php

declare(strict_types=1);

namespace App\Tests\Kata;

use App\Kata\Entity\Review;
use Doctrine\ORM\PersistentCollection;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Niveau 3 — Performance (fetch join, requêtes de masse) et Unit of Work (persist, flush, relations, orphanRemoval).
 * Fichiers à compléter : src/Kata/Repository/BookRepository.php et src/Kata/Library.php
 *
 * Chaque test appelle forgetEverything() avant de vérifier : on relit la BASE, pas les objets en mémoire.
 */
final class K3UnitOfWorkTest extends KataTestCase
{
    #[TestDox('K06 : charger un livre ET ses critiques en une seule requête (fetch join, anti N+1)')]
    public function testK06(): void
    {
        $id = $this->book('Les Misérables')->getId();
        $this->forgetEverything();

        $book = $this->books->k06FindOneWithReviews($id);

        self::assertNotNull($book);
        $reviews = $book->getReviews();
        self::assertInstanceOf(PersistentCollection::class, $reviews);
        self::assertTrue($reviews->isInitialized(), 'Les critiques doivent être chargées par la même requête (addSelect), pas en lazy loading.');
        self::assertSame([5, 4, 5], $reviews->map(fn (Review $r) => $r->getRating())->getValues());

        self::assertNull($this->books->k06FindOneWithReviews(999999));
    }

    #[TestDox('K12 : mise à jour de masse en DQL (UPDATE) sans charger les entités')]
    public function testK12(): void
    {
        self::assertSame(4, $this->books->k12MarkOutOfPrintPublishedBefore(new \DateTimeImmutable('1900-01-01')));

        $this->forgetEverything();
        self::assertTrue($this->book('Les Misérables')->isOutOfPrint());
        self::assertFalse($this->book('Mort sur le Nil')->isOutOfPrint());
        self::assertSame(0, $this->books->k12MarkOutOfPrintPublishedBefore(new \DateTimeImmutable('1900-01-01')), 'Les livres déjà épuisés ne comptent pas.');
    }

    #[TestDox('K17 : créer un livre avec des étiquettes existantes ou nouvelles (persist + flush)')]
    public function testK17(): void
    {
        $book = $this->library()->k17AddBook('La Servante écarlate', $this->author('Margaret Atwood'), new \DateTimeImmutable('1985-01-01'), 400, 'contemporain', 'dystopie');
        self::assertNotNull($book->getId(), 'Après flush, l\'id doit être renseigné.');

        $this->forgetEverything();
        $reloaded = $this->book('La Servante écarlate');
        self::assertSame('Margaret Atwood', $reloaded->getAuthor()->getName());
        self::assertEqualsCanonicalizing(['contemporain', 'dystopie'], $reloaded->getTags()->map(fn ($t) => $t->getName())->getValues());
        self::assertCount(3, $this->tag('contemporain')->getBooks(), 'L\'étiquette existante doit être réutilisée, pas dupliquée.');
    }

    #[TestDox('K18 : ajouter une critique à un livre (cascade persist)')]
    public function testK18(): void
    {
        $review = $this->library()->k18AddReview($this->book('Les Misérables'), 3, 'Un peu long');
        self::assertNotNull($review->getId());

        $this->forgetEverything();
        self::assertCount(4, $this->book('Les Misérables')->getReviews());
    }

    #[TestDox('K19 : renommer une étiquette (change tracking : pas besoin de persist)')]
    public function testK19(): void
    {
        $this->library()->k19RenameTag('policier', 'polar');

        $this->forgetEverything();
        self::assertNull($this->tag('policier'));
        self::assertCount(2, $this->tag('polar')->getBooks());
    }

    #[TestDox('K20 : transférer les livres d\'un auteur à un autre (côté propriétaire de la relation)')]
    public function testK20(): void
    {
        self::assertSame(2, $this->library()->k20TransferBooks($this->author('Haruki Murakami'), $this->author('Margaret Atwood')));

        $this->forgetEverything();
        self::assertSame(['1Q84', 'Kafka sur le rivage'], self::titles($this->author('Margaret Atwood')->getBooks()));
        self::assertCount(0, $this->author('Haruki Murakami')->getBooks());
    }

    #[TestDox('K21 : supprimer les critiques d\'un livre via la collection (orphanRemoval)')]
    public function testK21(): void
    {
        $before = $this->em->getRepository(Review::class)->count([]);

        self::assertSame(3, $this->library()->k21DeleteReviewsOfBook($this->book('Les Misérables')));

        $this->forgetEverything();
        self::assertCount(0, $this->book('Les Misérables')->getReviews());
        self::assertSame($before - 3, $this->em->getRepository(Review::class)->count([]));
    }
}
