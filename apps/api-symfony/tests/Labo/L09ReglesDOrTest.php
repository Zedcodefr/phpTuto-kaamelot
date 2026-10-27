<?php

declare(strict_types=1);

namespace App\Tests\Labo;

use App\Kata\Entity\Book;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * L09 — Combien de « pavés » (plus de 1000 pages) ? Trois versions, même réponse.
 *
 * Pronostic : combien d'objets Book chaque version crée-t-elle en mémoire ?
 * (livresEnMemoire() compte les objets Book présents dans l'Identity Map.)
 */
final class L09ReglesDOrTest extends LaboTestCase
{
    #[TestDox('L09 : compter les pavés en PHP, en base, ou en base pour de vrai')]
    public function testL09(): void
    {
        $pronostic = $this->pronostic('L09');

        // Version A : on charge tout, on filtre et on compte en PHP
        $this->demarrer();
        $a = \count(array_filter($this->books->findAll(), fn (Book $b) => $b->getPages() > 1000));
        $this->noter("A. count(array_filter(findAll(), …)) = $a");
        $entitesA = $this->livresEnMemoire();

        // Version B : la base filtre, PHP compte
        $this->demarrer();
        $b = \count($this->books->createQueryBuilder('b')
            ->where('b.pages > :pages')
            ->setParameter('pages', 1000)
            ->getQuery()
            ->getResult());
        $this->noter("B. count(…->where('b.pages > :pages')…->getResult()) = $b");
        $entitesB = $this->livresEnMemoire();

        // Version C : la base filtre et compte
        $this->demarrer();
        $c = (int) $this->books->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.pages > :pages')
            ->setParameter('pages', 1000)
            ->getQuery()
            ->getSingleScalarResult();
        $this->noter("C. ->select('COUNT(b.id)')…->getSingleScalarResult() = $c");

        $this->verdict($pronostic, [
            'entites_findAll' => $entitesA,
            'entites_where' => $entitesB,
            'entites_count' => $this->livresEnMemoire(),
        ]);
    }
}
