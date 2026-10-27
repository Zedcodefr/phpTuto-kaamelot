<?php

declare(strict_types=1);

namespace App\Tests\Labo;

use App\Kata\Entity\Author;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * L03 — persist() puis flush().
 *
 * Pronostic : combien de requêtes après persist() ? L'id est-il déjà connu à ce moment-là ? Et après flush() ?
 */
final class L03PersistFlushTest extends LaboTestCase
{
    #[TestDox('L03 : créer un auteur, persist(), puis flush()')]
    public function testL03(): void
    {
        $pronostic = $this->pronostic('L03');
        $this->demarrer();

        $leGuin = new Author('Ursula K. Le Guin', 'États-Unis');
        $this->em->persist($leGuin);
        $this->noter('new Author() + persist()');

        $avantFlush = $this->requetes();
        $idConnuAvantFlush = null !== $leGuin->getId();

        $this->em->flush();
        $this->noter(sprintf('flush() : getId() vaut maintenant %d', $leGuin->getId()));

        $this->verdict($pronostic, [
            'requetes_avant_flush' => $avantFlush,
            'id_connu_avant_flush' => $idConnuAvantFlush,
            'requetes_apres_flush' => $this->requetes(),
        ]);
    }
}
