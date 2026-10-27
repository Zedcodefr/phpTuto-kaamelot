<?php

declare(strict_types=1);

namespace App\Tests\Labo;

use PHPUnit\Framework\Attributes\TestDox;

/**
 * L06 — Afficher chaque livre avec son auteur (9 livres, 4 auteurs différents).
 *
 * Pronostic : combien de requêtes pour la boucle naïve ? Et avec le fetch join ?
 */
final class L06NPlusOneTest extends LaboTestCase
{
    #[TestDox('L06 : la liste des livres avec leur auteur, deux façons')]
    public function testL06(): void
    {
        $pronostic = $this->pronostic('L06');
        $this->demarrer();

        // Version 1 : findAll(), puis on suit la relation dans la boucle
        $lignes = [];
        foreach ($this->books->findAll() as $book) {
            $lignes[] = $book->getTitle().' — '.$book->getAuthor()->getName();
        }
        $this->noter(sprintf('findAll() + getAuthor()->getName() dans la boucle : %d lignes', \count($lignes)));
        $boucle = $this->requetes();

        $this->demarrer();

        // Version 2 : la même liste, auteur chargé dans la même requête (fetch join)
        $books = $this->books->createQueryBuilder('b')
            ->join('b.author', 'a')
            ->addSelect('a')
            ->getQuery()
            ->getResult();
        $lignes = [];
        foreach ($books as $book) {
            $lignes[] = $book->getTitle().' — '.$book->getAuthor()->getName();
        }
        $this->noter(sprintf('join(\'b.author\', \'a\')->addSelect(\'a\') + la même boucle : %d lignes', \count($lignes)));

        $this->verdict($pronostic, [
            'requetes_boucle' => $boucle,
            'requetes_fetch_join' => $this->requetes(),
        ]);
    }
}
