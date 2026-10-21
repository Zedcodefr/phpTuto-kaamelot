<?php

declare(strict_types=1);

namespace App\Kata\Repository;

use App\Kata\Entity\Author;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * KATA DOCTRINE & DQL — SOLUTION.
 *
 * @extends ServiceEntityRepository<Author>
 */
class AuthorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Author::class);
    }

    /** @return list<Author> */
    public function k14FindAuthorsWithARatingOf(int $rating): array
    {
        return $this->createQueryBuilder('a')
            ->distinct()
            ->join('a.books', 'b')
            ->join('b.reviews', 'r')
            ->where('r.rating = :rating')
            ->setParameter('rating', $rating)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Author> */
    public function k15FindAuthorsWithoutBooks(): array
    {
        $sub = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from('App\Kata\Entity\Book', 'b')
            ->where('b.author = a');

        return $this->createQueryBuilder('a')
            ->where('NOT EXISTS (' . $sub->getDQL() . ')')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function k16FindMostReviewedAuthor(): ?Author
    {
        return $this->createQueryBuilder('a')
            ->join('a.books', 'b')
            ->join('b.reviews', 'r')
            ->groupBy('a.id')
            ->orderBy('COUNT(r.id)', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
