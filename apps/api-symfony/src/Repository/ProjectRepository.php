<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * @return list<Project>
     */
    public function findAllOrderedByName(): array
    {
        // Résultat mixte : [0 => Project, 'taskCount' => int] — un seul SELECT au lieu d'un COUNT par projet (N+1)
        $rows = $this->createQueryBuilder('p')
            ->select('p', 'COUNT(t.id) AS taskCount')
            ->leftJoin('p.tasks', 't')
            ->groupBy('p.id')
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static function (array $row): Project {
            $project = $row[0];
            $project->setKnownTaskCount((int) $row['taskCount']);

            return $project;
        }, $rows);
    }
}
