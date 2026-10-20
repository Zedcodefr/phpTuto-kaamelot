<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
