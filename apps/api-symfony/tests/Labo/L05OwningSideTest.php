<?php

declare(strict_types=1);

namespace App\Tests\Labo;

use PHPUnit\Framework\Attributes\TestDox;

/**
 * L05 — Donner un livre à un autre auteur… par le mauvais bout.
 *
 * « Kafka sur le rivage » est de Haruki Murakami. On l'ajoute à la collection de Margaret Atwood, puis flush().
 * Pronostic : combien d'UPDATE ? Qui est l'auteur du livre quand on relit la base ?
 */
final class L05OwningSideTest extends LaboTestCase
{
    #[TestDox('L05 : $atwood->getBooks()->add($kafka), puis flush()')]
    public function testL05(): void
    {
        $pronostic = $this->pronostic('L05');
        $this->demarrer();

        $atwood = $this->author('Margaret Atwood');
        $kafka = $this->book('Kafka sur le rivage');
        $this->noter('Margaret Atwood et « Kafka sur le rivage » chargés');

        $atwood->getBooks()->add($kafka);
        $this->noter(sprintf('$atwood->getBooks()->add($kafka) : Atwood a %d livre(s) en mémoire', $atwood->getBooks()->count()));

        $this->em->flush();
        $this->noter('flush()');
        $updates = $this->requetesDeType('UPDATE');

        $this->em->clear();
        $auteurEnBase = $this->book('Kafka sur le rivage')->getAuthor()->getName();
        $this->noter('clear() puis relecture du livre en base');

        $this->verdict($pronostic, [
            'updates' => $updates,
            'auteur_en_base' => $auteurEnBase,
        ]);
    }
}
