<?php

declare(strict_types=1);

namespace App\Tests\Labo;

use PHPUnit\Framework\Attributes\TestDox;

/**
 * L01 — Trois lectures du même livre.
 *
 * Pronostic : combien de requêtes SQL ? $a et $c sont-ils le même objet PHP ?
 */
final class L01IdentityMapTest extends LaboTestCase
{
    #[TestDox('L01 : trois lectures du même livre')]
    public function testL01(): void
    {
        $pronostic = $this->pronostic('L01');
        $id = $this->book('Les Misérables')->getId();
        $this->demarrer();

        $a = $this->books->find($id);
        $this->noter('$a = find($id)');

        $b = $this->books->find($id);
        $this->noter('$b = find($id)');

        $c = $this->books->findOneBy(['title' => 'Les Misérables']);
        $this->noter('$c = findOneBy([\'title\' => \'Les Misérables\'])');

        $this->verdict($pronostic, [
            'requetes' => $this->requetes(),
            'a_et_c_identiques' => $a === $c,
        ]);
    }
}
