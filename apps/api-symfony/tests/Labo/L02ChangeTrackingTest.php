<?php

declare(strict_types=1);

namespace App\Tests\Labo;

use PHPUnit\Framework\Attributes\TestDox;

/**
 * L02 — Modifier sans persist().
 *
 * « Les Misérables » n'est pas épuisé : on le marque épuisé.
 * « Mort sur le Nil » n'est pas épuisé non plus : on le marque… non épuisé.
 * Aucun appel à persist(). Pronostic : combien de requêtes, dont combien d'UPDATE ?
 */
final class L02ChangeTrackingTest extends LaboTestCase
{
    #[TestDox('L02 : modifier deux livres sans persist(), puis flush()')]
    public function testL02(): void
    {
        $pronostic = $this->pronostic('L02');
        $this->demarrer();

        $miserables = $this->book('Les Misérables');
        $nil = $this->book('Mort sur le Nil');
        $this->noter('deux livres chargés');

        $miserables->setOutOfPrint(true);
        $nil->setOutOfPrint(false);
        $this->noter('setOutOfPrint(true) et setOutOfPrint(false), pas de persist()');

        $this->em->flush();
        $this->noter('flush()');

        $this->verdict($pronostic, [
            'requetes' => $this->requetes(),
            'updates' => $this->requetesDeType('UPDATE'),
        ]);
    }
}
