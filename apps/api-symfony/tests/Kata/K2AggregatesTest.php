<?php

declare(strict_types=1);

namespace App\Tests\Kata;

use App\Kata\Dto\BookSummary;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Niveau 2 — Agrégats (COUNT, AVG, GROUP BY, HAVING), résultats scalaires, pagination, projections, sous-requêtes.
 * Fichiers à compléter : src/Kata/Repository/BookRepository.php et AuthorRepository.php
 */
final class K2AggregatesTest extends KataTestCase
{
    #[TestDox('K05 : nombre de livres par auteur, y compris les auteurs sans livre (LEFT JOIN + GROUP BY)')]
    public function testK05(): void
    {
        self::assertSame([
            'Agatha Christie' => 2,
            'Haruki Murakami' => 2,
            'Jules Verne' => 3,
            'Margaret Atwood' => 0,
            'Victor Hugo' => 2,
        ], $this->books->k05CountBooksPerAuthor());
    }

    #[TestDox('K07 : note moyenne par livre, filtrée avec HAVING')]
    public function testK07(): void
    {
        $result = $this->books->k07FindAverageRatingsAtLeast(4.0);

        self::assertSame(
            ['Vingt mille lieues sous les mers', 'Les Misérables', 'Le Crime de l\'Orient-Express', 'Kafka sur le rivage'],
            array_column($result, 'title'),
        );
        self::assertEqualsWithDelta([5.0, 4.67, 4.33, 4.0], array_column($result, 'average'), 0.01);
        self::assertIsFloat($result[0]['average']);
    }

    #[TestDox('K10 : pagination avec le Paginator Doctrine (éléments de la page + total)')]
    public function testK10(): void
    {
        $page2 = $this->books->k10Paginate(page: 2, limit: 4);
        self::assertSame(9, $page2['total']);
        self::assertSame(
            ['Les Misérables', 'Mort sur le Nil', 'Notre-Dame de Paris', 'Vingt mille lieues sous les mers'],
            self::titles($page2['items']),
        );

        $page3 = $this->books->k10Paginate(page: 3, limit: 4);
        self::assertSame(['Voyage au centre de la Terre'], self::titles($page3['items']));
        self::assertTrue(array_is_list($page3['items']), 'items doit être une liste (clés 0, 1, 2...)');
    }

    #[TestDox('K11 : projection dans un DTO avec SELECT NEW')]
    public function testK11(): void
    {
        $summaries = $this->books->k11FindSummaries();

        self::assertContainsOnlyInstancesOf(BookSummary::class, $summaries);
        self::assertEquals(new BookSummary('1Q84', 'Haruki Murakami', 0), $summaries[0]);
        self::assertEquals(new BookSummary('Le Crime de l\'Orient-Express', 'Agatha Christie', 3), $summaries[2]);
        self::assertSame([0, 1, 3, 2, 3, 1, 2, 2, 0], array_map(fn (BookSummary $s) => $s->reviewCount, $summaries));
    }

    #[TestDox('K14 : auteurs ayant reçu au moins une critique avec une note donnée (DISTINCT, jointures en chaîne)')]
    public function testK14(): void
    {
        self::assertSame(['Agatha Christie', 'Jules Verne', 'Victor Hugo'], self::names($this->authors->k14FindAuthorsWithARatingOf(5)));
        self::assertSame(['Agatha Christie'], self::names($this->authors->k14FindAuthorsWithARatingOf(2)));
    }

    #[TestDox('K15 : auteurs sans livre (sous-requête NOT EXISTS)')]
    public function testK15(): void
    {
        self::assertSame(['Margaret Atwood'], self::names($this->authors->k15FindAuthorsWithoutBooks()));
    }

    #[TestDox('K16 : l\'auteur le plus critiqué (agrégat + tri + limite)')]
    public function testK16(): void
    {
        self::assertSame('Victor Hugo', $this->authors->k16FindMostReviewedAuthor()?->getName());
    }
}
