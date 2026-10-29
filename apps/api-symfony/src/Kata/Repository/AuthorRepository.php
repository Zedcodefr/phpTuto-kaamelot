<?php

declare(strict_types=1);

namespace App\Kata\Repository;

use App\Kata\Entity\Author;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * KATA DOCTRINE & DQL — À COMPLÉTER.
 * Lancez `make sf-kata` (ou `make sf-kata c="--filter=K05"`) et faites passer les tests un par un.
 * Expérimentez vos requêtes avec `make dql q="SELECT ..."` et regardez le SQL généré dans le profiler.
 *
 * @extends ServiceEntityRepository<Author>
 */
class AuthorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Author::class);
    }

    /**
     * K14 — Les auteurs dont au moins un livre a reçu une critique avec exactement cette note, triés par nom, SANS DOUBLON.
     * Notions : jointures en chaîne (a.books puis b.reviews), distinct().
     *
     * @return list<Author>
     */
    public function k14FindAuthorsWithARatingOf(int $rating): array
    {
        throw new \LogicException('TODO K14');
    }

    /**
     * K15 — Les auteurs qui n'ont écrit aucun livre, triés par nom.
     * ⚠️ Contrainte : utiliser une SOUS-REQUÊTE avec NOT EXISTS (un second QueryBuilder et getDQL()).
     *
     * @return list<Author>
     */
    public function k15FindAuthorsWithoutBooks(): array
    {
        throw new \LogicException('TODO K15');
    }

    /**
     * K16 — L'auteur dont les livres cumulent le plus de critiques (null s'il n'y a aucune critique).
     * Notions : GROUP BY sur l'entité, ORDER BY COUNT(...), setMaxResults(1), getOneOrNullResult().
     */
    public function k16FindMostReviewedAuthor(): ?Author
    {
        throw new \LogicException('TODO K16');
    }
}
