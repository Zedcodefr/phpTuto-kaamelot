<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\TaskFilter;
use App\Entity\Project;
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
            ->leftJoin('t.project', 'p')->addSelect('p')
            ->leftJoin('t.author', 'au')->addSelect('au')      // B2-4a : auteur et assigné chargés
            ->leftJoin('t.assignee', 'asg')->addSelect('asg')  // dans la même requête (anti N+1, ↩︎ K06)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * B3-1 — Liste filtrée et paginée (GET /api/tasks?status=&search=&page=&limit=).
     * count($paginator) = total sans pagination (→ X-Total-Count) ; itérer = les tâches de la page.
     *
     * @return Paginator<Task>
     */
    public function search(TaskFilter $filter): Paginator
    {
        $qb = $this->createQueryBuilder('t')
            // B1-3 : fetch join du projet (ManyToOne) → pas de N+1 à la sérialisation de task.project
            ->leftJoin('t.project', 'p')->addSelect('p')
            ->leftJoin('t.author', 'au')->addSelect('au')      // B2-4a : auteur et assigné chargés
            ->leftJoin('t.assignee', 'asg')->addSelect('asg')  // dans la même requête (anti N+1, ↩︎ K06)
            ->orderBy('t.id', 'ASC')
            ->setFirstResult($filter->offset())
            ->setMaxResults($filter->limit);

        if (null !== $filter->status) {
            $qb->andWhere('t.status = :status')->setParameter('status', $filter->status);
        }
        if (null !== $filter->projectId) {
            // IDENTITY() lit la clé étrangère project_id sans dépendre de la jointure (≈ t.ProjectId en EF Core)
            $qb->andWhere('IDENTITY(t.project) = :projectId')->setParameter('projectId', $filter->projectId);
        }
        if (null !== $filter->search && '' !== trim($filter->search)) {
            // Doctrine entoure ce OR de parenthèses : ... AND (title LIKE :q OR description LIKE :q)
            $qb->andWhere('LOWER(t.title) LIKE :q OR LOWER(t.description) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower(trim($filter->search)) . '%');
        }

        // Paginator plutôt que setMaxResults seul : reste juste quand des fetch joins de collections arriveront.
        return new Paginator($qb->getQuery());
    }

    /**
     * B1-4 — Tâches d'un projet, triées par id (même forme que GET /api/tasks).
     *
     * @return list<Task>
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.project', 'p')->addSelect('p')
            ->leftJoin('t.author', 'au')->addSelect('au')      // B2-4a : auteur et assigné chargés
            ->leftJoin('t.assignee', 'asg')->addSelect('asg')  // dans la même requête (anti N+1, ↩︎ K06)
            ->andWhere('t.project = :project')
            ->setParameter('project', $project)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
