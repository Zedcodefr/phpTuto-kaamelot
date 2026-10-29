<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\TaskFilter;
use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository Doctrine ≈ DbSet<Task> + méthodes de requête personnalisées.
 * find(), findAll(), findBy(), findOneBy() sont hérités.
 *
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * @return list<Task>
     */
    public function findAllOrdered(): array
    {
        // QueryBuilder ≈ LINQ to Entities (le DQL interroge des entités, pas des tables)
        return $this->createQueryBuilder('t')
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function search(TaskFilter $filter): Paginator
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.id', 'ASC')
            ->setFirstResult(($filter->page - 1) * $filter->limit)
            ->setMaxResults($filter->limit);

        if ($filter->status !== null) {
            $qb->andWhere('t.status = :status')->setParameter('status', $filter->status);
        }
        if ($filter->search !== null && $filter->search !== '') {
            $qb->andWhere('LOWER(t.title) LIKE :q OR LOWER(t.description) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($filter->search).'%');
        }

        return new Paginator($qb);
    }
}
