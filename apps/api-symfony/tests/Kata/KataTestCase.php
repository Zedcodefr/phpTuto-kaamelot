<?php

declare(strict_types=1);

namespace App\Tests\Kata;

use App\Kata\Entity\Author;
use App\Kata\Entity\Book;
use App\Kata\Entity\Tag;
use App\Kata\Library;
use App\Kata\Repository\AuthorRepository;
use App\Kata\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base des tests du kata. KernelTestCase démarre Symfony SANS requête HTTP :
 * on récupère directement les services dans le conteneur (≈ IServiceProvider.GetService en C#).
 *
 * Données : src/DataFixtures/KataFixtures.php (chargées par `make sf-kata`).
 * Chaque test tourne dans une transaction annulée (DAMA) : les katas qui écrivent ne cassent pas les autres.
 */
abstract class KataTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;
    protected BookRepository $books;
    protected AuthorRepository $authors;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->books = $container->get(BookRepository::class);
        $this->authors = $container->get(AuthorRepository::class);

        if (0 === $this->books->count([])) {
            self::markTestSkipped('Données du kata absentes : lancez `make sf-kata` (et non phpunit directement).');
        }
    }

    protected function library(): Library
    {
        return static::getContainer()->get(Library::class);
    }

    protected function book(string $title): Book
    {
        return $this->books->findOneBy(['title' => $title]) ?? throw new \LogicException("Livre $title absent des fixtures");
    }

    protected function author(string $name): Author
    {
        return $this->authors->findOneBy(['name' => $name]) ?? throw new \LogicException("Auteur $name absent des fixtures");
    }

    protected function tag(string $name): ?Tag
    {
        return $this->em->getRepository(Tag::class)->findOneBy(['name' => $name]);
    }

    /**
     * Vide l'Identity Map : les prochaines lectures repartent de la base
     * (indispensable pour vérifier qu'un flush a vraiment écrit quelque chose).
     */
    protected function forgetEverything(): void
    {
        $this->em->clear();
    }

    /**
     * @param iterable<Book> $books
     *
     * @return list<string>
     */
    protected static function titles(iterable $books): array
    {
        $titles = [];
        foreach ($books as $book) {
            self::assertInstanceOf(Book::class, $book, 'On attend des entités Book (pas des tableaux ni des scalaires).');
            $titles[] = $book->getTitle();
        }

        return $titles;
    }

    /**
     * @param iterable<Author> $authors
     *
     * @return list<string>
     */
    protected static function names(iterable $authors): array
    {
        $names = [];
        foreach ($authors as $author) {
            self::assertInstanceOf(Author::class, $author);
            $names[] = $author->getName();
        }

        return $names;
    }
}
