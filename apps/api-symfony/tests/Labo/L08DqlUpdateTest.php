<?php

declare(strict_types=1);

namespace App\Tests\Labo;

use App\Kata\Entity\Book;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * L08 — Un UPDATE en DQL sur un livre déjà chargé.
 *
 * « Mort sur le Nil » n'est pas épuisé. On le charge, puis un UPDATE DQL le marque épuisé.
 * Pronostic : que renvoie execute() ? Que répond l'objet PHP ensuite, à chaque étape ?
 */
final class L08DqlUpdateTest extends LaboTestCase
{
    #[TestDox('L08 : UPDATE DQL, puis relire le livre de trois façons')]
    public function testL08(): void
    {
        $pronostic = $this->pronostic('L08');
        $this->demarrer();

        $book = $this->book('Mort sur le Nil');
        $this->noter('livre chargé : isOutOfPrint() = '.var_export($book->isOutOfPrint(), true));

        $lignes = $this->em->createQuery('UPDATE '.Book::class.' b SET b.outOfPrint = true WHERE b.title = :title')
            ->setParameter('title', 'Mort sur le Nil')
            ->execute();
        $this->noter("UPDATE DQL : execute() renvoie $lignes");
        $enMemoire = $book->isOutOfPrint();

        $apresFind = $this->books->find($book->getId())->isOutOfPrint();
        $this->noter('nouveau find($id)');

        $this->em->refresh($book);
        $this->noter('refresh($book)');

        $this->verdict($pronostic, [
            'lignes_modifiees' => $lignes,
            'en_memoire_apres_update' => $enMemoire,
            'apres_nouveau_find' => $apresFind,
            'apres_refresh' => $book->isOutOfPrint(),
        ]);
    }
}
