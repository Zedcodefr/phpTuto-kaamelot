<?php

declare(strict_types=1);

namespace App\Kata\Repository;

use App\Kata\Dto\BookSummary;
use App\Kata\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * KATA DOCTRINE & DQL — SOLUTION.
 *
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    /** @return list<Book> */
    public function k01FindAllOrderedByTitle(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Book> */
    public function k02FindByAuthorName(string $name): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.author', 'a')
            ->where('a.name = :name')
            ->setParameter('name', $name)
            ->orderBy('b.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Book> */
    public function k03FindPublishedBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.publishedAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('b.publishedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Book> */
    public function k04FindWithAtLeastPages(int $minPages): array
    {
        return $this->getEntityManager()
            ->createQuery('SELECT b FROM App\Kata\Entity\Book b WHERE b.pages >= :min ORDER BY b.pages DESC')
            ->setParameter('min', $minPages)
            ->getResult();
    }

    /** @return array<string, int> */
    public function k05CountBooksPerAuthor(): array
    {
        $rows = $this->getEntityManager()
            ->createQuery(
                'SELECT a.name AS name, COUNT(b.id) AS total
                 FROM App\Kata\Entity\Author a
                 LEFT JOIN a.books b
                 GROUP BY a.id, a.name
                 ORDER BY a.name ASC'
            )
            ->getArrayResult();

        return array_column(array_map(fn (array $r) => ['name' => $r['name'], 'total' => (int) $r['total']], $rows), 'total', 'name');
    }

    public function k06FindOneWithReviews(int $id): ?Book
    {
        return $this->createQueryBuilder('b')
            ->addSelect('r')
            ->leftJoin('b.reviews', 'r')
            ->where('b.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<array{title: string, average: float}> */
    public function k07FindAverageRatingsAtLeast(float $minAverage): array
    {
        $rows = $this->createQueryBuilder('b')
            ->select('b.title AS title', 'AVG(r.rating) AS average')
            ->join('b.reviews', 'r')
            ->groupBy('b.id', 'b.title')
            ->having('AVG(r.rating) >= :min')
            ->setParameter('min', $minAverage)
            ->orderBy('average', 'DESC')
            ->addOrderBy('b.title', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(fn (array $r) => ['title' => $r['title'], 'average' => round((float) $r['average'], 2)], $rows);
    }

    /** @return list<Book> */
    public function k08FindByTag(string $tagName): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.tags', 't')
            ->where('t.name = :tag')
            ->setParameter('tag', $tagName)
            ->orderBy('b.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Book> */
    public function k09Search(?string $text = null, ?string $authorCountry = null, ?bool $outOfPrint = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->join('b.author', 'a')
            ->orderBy('b.title', 'ASC');

        if (null !== $text && '' !== $text) {
            $qb->andWhere('LOWER(b.title) LIKE :text')->setParameter('text', '%' . mb_strtolower($text) . '%');
        }
        if (null !== $authorCountry) {
            $qb->andWhere('a.country = :country')->setParameter('country', $authorCountry);
        }
        if (null !== $outOfPrint) {
            $qb->andWhere('b.outOfPrint = :oop')->setParameter('oop', $outOfPrint);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return array{items: list<Book>, total: int} */
    public function k10Paginate(int $page, int $limit): array
    {
        $query = $this->createQueryBuilder('b')
            ->orderBy('b.title', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query);

        return ['items' => iterator_to_array($paginator, false), 'total' => count($paginator)];
    }

    /** @return list<BookSummary> */
    public function k11FindSummaries(): array
    {
        return $this->getEntityManager()
            ->createQuery(
                'SELECT NEW App\Kata\Dto\BookSummary(b.title, a.name, COUNT(r.id))
                 FROM App\Kata\Entity\Book b
                 JOIN b.author a
                 LEFT JOIN b.reviews r
                 GROUP BY b.id, b.title, a.name
                 ORDER BY b.title ASC'
            )
            ->getResult();
    }

    public function k12MarkOutOfPrintPublishedBefore(\DateTimeImmutable $date): int
    {
        return $this->getEntityManager()
            ->createQuery('UPDATE App\Kata\Entity\Book b SET b.outOfPrint = true WHERE b.publishedAt < :date AND b.outOfPrint = false')
            ->setParameter('date', $date)
            ->execute();
    }

    /** @return list<Book> */
    public function k13FindNeverReviewed(): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.reviews', 'r')
            ->where('r.id IS NULL')
            ->orderBy('b.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
