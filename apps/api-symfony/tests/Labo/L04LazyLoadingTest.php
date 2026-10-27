<?php

declare(strict_types=1);

namespace App\Tests\Labo;

use PHPUnit\Framework\Attributes\TestDox;

/**
 * L04 — Suivre une relation.
 *
 * Pronostic : combien de requêtes au total, après chacune des quatre lignes ?
 */
final class L04LazyLoadingTest extends LaboTestCase
{
    #[TestDox('L04 : find(), getAuthor(), getId(), getName()')]
    public function testL04(): void
    {
        $pronostic = $this->pronostic('L04');
        $id = $this->book('Kafka sur le rivage')->getId();
        $this->demarrer();

        $book = $this->books->find($id);
        $this->noter('$book = find($id)');
        $apresFind = $this->requetes();

        $author = $book->getAuthor();
        $this->noter('$author = $book->getAuthor()');
        $this->commenter(sprintf('un objet %s, encore vide : %s', $author::class, self::fantome($author)));
        $apresGetAuthor = $this->requetes();

        $author->getId();
        $this->noter('$author->getId()');
        $this->commenter('encore vide : '.self::fantome($author));
        $apresGetId = $this->requetes();

        $author->getName();
        $this->noter('$author->getName()');
        $this->commenter(sprintf('« %s », encore vide : %s', $author->getName(), self::fantome($author)));

        $this->verdict($pronostic, [
            'apres_find' => $apresFind,
            'apres_getAuthor' => $apresGetAuthor,
            'apres_getId' => $apresGetId,
            'apres_getName' => $this->requetes(),
        ]);
    }

    /** PHP 8.4 : Doctrine utilise les « lazy objects » natifs (un vrai Author, rempli au premier accès). */
    private static function fantome(object $entite): string
    {
        return (new \ReflectionClass($entite))->isUninitializedLazyObject($entite) ? 'oui' : 'non';
    }
}
