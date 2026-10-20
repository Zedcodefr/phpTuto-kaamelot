<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Kata\Entity\Author;
use App\Kata\Entity\Book;
use App\Kata\Entity\Review;
use App\Kata\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Données du kata Doctrine & DQL. ⚠️ Les tests du kata dépendent de ces données exactes : ne pas modifier.
 *
 * Chargées avec les autres fixtures par `make sf-db-reset`, et seules (groupe "kata") par `make sf-kata`.
 */
final class KataFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['kata'];
    }

    public function load(ObjectManager $manager): void
    {
        $authors = [];
        foreach ([
            'hugo' => ['Victor Hugo', 'France'],
            'verne' => ['Jules Verne', 'France'],
            'christie' => ['Agatha Christie', 'Royaume-Uni'],
            'murakami' => ['Haruki Murakami', 'Japon'],
            'atwood' => ['Margaret Atwood', 'Canada'], // aucun livre
        ] as $key => [$name, $country]) {
            $manager->persist($authors[$key] = new Author($name, $country));
        }

        $tags = [];
        foreach (['classique', 'aventure', 'policier', 'science-fiction', 'contemporain'] as $name) {
            $manager->persist($tags[$name] = new Tag($name));
        }

        $books = [
            //            titre                                       auteur      publication     pages  épuisé  tags                                         notes
            ['Les Misérables', 'hugo', '1862-04-03', 1900, false, ['classique'], [5, 4, 5]],
            ['Notre-Dame de Paris', 'hugo', '1831-03-16', 940, false, ['classique'], [3, 4]],
            ['Vingt mille lieues sous les mers', 'verne', '1870-06-20', 480, false, ['classique', 'aventure', 'science-fiction'], [5, 5]],
            ['Le Tour du monde en quatre-vingts jours', 'verne', '1872-12-22', 320, false, ['classique', 'aventure'], [4, 3]],
            ['Voyage au centre de la Terre', 'verne', '1864-11-25', 350, true, ['classique', 'aventure', 'science-fiction'], []],
            ['Le Crime de l\'Orient-Express', 'christie', '1934-01-01', 256, false, ['classique', 'policier'], [5, 4, 4]],
            ['Mort sur le Nil', 'christie', '1937-11-01', 300, false, ['classique', 'policier'], [2]],
            ['Kafka sur le rivage', 'murakami', '2002-09-12', 640, false, ['contemporain'], [4]],
            ['1Q84', 'murakami', '2009-05-29', 1100, false, ['contemporain', 'science-fiction'], []],
        ];

        $day = 0;
        foreach ($books as [$title, $author, $publishedAt, $pages, $outOfPrint, $bookTags, $ratings]) {
            $book = new Book($title, $authors[$author], new \DateTimeImmutable($publishedAt), $pages);
            $book->setOutOfPrint($outOfPrint);
            foreach ($bookTags as $tag) {
                $book->addTag($tags[$tag]);
            }
            foreach ($ratings as $rating) {
                new Review($book, $rating, "Note de $rating/5", new \DateTimeImmutable('2026-01-01 +' . $day++ . ' days'));
            }
            $manager->persist($book); // cascade persist => les critiques suivent
        }

        $manager->flush();
    }
}
