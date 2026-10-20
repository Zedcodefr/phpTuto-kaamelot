<?php

declare(strict_types=1);

namespace App\Tests\Kata;

use PHPUnit\Framework\Attributes\TestDox;

/**
 * Niveau 1 — QueryBuilder et DQL : sélection, jointures, paramètres, tri.
 * Fichier à compléter : src/Kata/Repository/BookRepository.php
 */
final class K1QueryBuilderTest extends KataTestCase
{
    #[TestDox('K01 : tous les livres triés par titre')]
    public function testK01(): void
    {
        self::assertSame([
            '1Q84',
            'Kafka sur le rivage',
            'Le Crime de l\'Orient-Express',
            'Le Tour du monde en quatre-vingts jours',
            'Les Misérables',
            'Mort sur le Nil',
            'Notre-Dame de Paris',
            'Vingt mille lieues sous les mers',
            'Voyage au centre de la Terre',
        ], self::titles($this->books->k01FindAllOrderedByTitle()));
    }

    #[TestDox('K02 : livres d\'un auteur (jointure + paramètre)')]
    public function testK02(): void
    {
        self::assertSame(
            ['Le Tour du monde en quatre-vingts jours', 'Vingt mille lieues sous les mers', 'Voyage au centre de la Terre'],
            self::titles($this->books->k02FindByAuthorName('Jules Verne')),
        );
        self::assertSame([], $this->books->k02FindByAuthorName('Inconnu'));
    }

    #[TestDox('K03 : livres publiés entre deux dates, du plus ancien au plus récent')]
    public function testK03(): void
    {
        self::assertSame(
            ['Voyage au centre de la Terre', 'Vingt mille lieues sous les mers', 'Le Tour du monde en quatre-vingts jours'],
            self::titles($this->books->k03FindPublishedBetween(new \DateTimeImmutable('1864-01-01'), new \DateTimeImmutable('1880-12-31'))),
        );
    }

    #[TestDox('K04 : DQL "à la main" — livres d\'au moins N pages, les plus longs d\'abord')]
    public function testK04(): void
    {
        self::assertSame(
            ['Les Misérables', '1Q84', 'Notre-Dame de Paris'],
            self::titles($this->books->k04FindWithAtLeastPages(900)),
        );
    }

    #[TestDox('K08 : livres portant une étiquette (ManyToMany)')]
    public function testK08(): void
    {
        self::assertSame(
            ['1Q84', 'Vingt mille lieues sous les mers', 'Voyage au centre de la Terre'],
            self::titles($this->books->k08FindByTag('science-fiction')),
        );
    }

    #[TestDox('K09 : recherche à critères optionnels (requête construite dynamiquement)')]
    public function testK09(): void
    {
        self::assertCount(9, $this->books->k09Search());
        self::assertSame(['Le Crime de l\'Orient-Express', 'Mort sur le Nil'], self::titles($this->books->k09Search(authorCountry: 'Royaume-Uni')));
        self::assertSame(['Notre-Dame de Paris'], self::titles($this->books->k09Search(text: 'DE PARIS')), 'Recherche insensible à la casse');
        self::assertSame(['Voyage au centre de la Terre'], self::titles($this->books->k09Search(text: 'terre', authorCountry: 'France', outOfPrint: true)));
        self::assertSame([], $this->books->k09Search(text: 'terre', outOfPrint: false));
    }

    #[TestDox('K13 : livres sans aucune critique')]
    public function testK13(): void
    {
        self::assertSame(['1Q84', 'Voyage au centre de la Terre'], self::titles($this->books->k13FindNeverReviewed()));
    }
}
