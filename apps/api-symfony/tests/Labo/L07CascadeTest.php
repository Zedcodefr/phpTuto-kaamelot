<?php

declare(strict_types=1);

namespace App\Tests\Labo;

use App\Kata\Entity\Review;
use App\Kata\Entity\Tag;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * L07 — Accrocher un nouvel objet à un livre existant, sans persist().
 *
 * Relisez le mapping de Book::$reviews et de Book::$tags (src/Kata/Entity/Book.php).
 * Pronostic : chacun des deux flush() passe-t-il ?
 */
final class L07CascadeTest extends LaboTestCase
{
    #[TestDox('L07 : une nouvelle critique, puis une nouvelle étiquette, sans persist()')]
    public function testL07(): void
    {
        $pronostic = $this->pronostic('L07');
        $this->demarrer();
        $book = $this->book('Mort sur le Nil');
        $this->noter('livre chargé');

        new Review($book, 5, 'Relu avec plaisir'); // le constructeur l'ajoute à $book->getReviews()
        $critiqueAcceptee = $this->tenterFlush('new Review($book, …) puis flush()');

        $book->addTag(new Tag('égypte'));
        $etiquetteAcceptee = $this->tenterFlush('$book->addTag(new Tag(\'égypte\')) puis flush()');

        $this->verdict($pronostic, [
            'critique_acceptee' => $critiqueAcceptee,
            'etiquette_acceptee' => $etiquetteAcceptee,
        ]);
    }

    private function tenterFlush(string $action): bool
    {
        try {
            $this->em->flush();
            $this->noter($action.' : OK');

            return true;
        } catch (\Throwable $e) {
            $this->noter($action.' : exception');
            $this->commenter($e::class);
            $this->commenter($e->getMessage());

            return false;
        }
    }
}
