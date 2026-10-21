<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Données de démonstration (≈ HasData() / seeding EF Core).
 * Chargées par `make sf-db-reset`. Mêmes données que apps/api-php/database/schema.sql.
 */
final class AppFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [ProjectFixtures::class, UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $php = $this->getReference(ProjectFixtures::PHP, Project::class);
        $alice = $this->getReference('user-alice', User::class);
        $bob = $this->getReference('user-bob', User::class);

        // B2-4a : auteur et assigné (la 1re tâche reste sans auteur, comme une tâche créée avant l'authentification)
        $tasks = [
            ['Installer l\'environnement', 'make install puis make up', TaskStatus::Done, null, null, null],
            ['Découvrir la syntaxe PHP', 'Variables, tableaux, classes, enums...', TaskStatus::InProgress, $php, $alice, $bob],
            ['Écrire l\'API en PHP pur', 'Router, PDO, JSON', TaskStatus::Todo, $php, $alice, null],
            ['Migrer l\'API vers Symfony', null, TaskStatus::Todo, $php, $bob, $bob],
        ];

        foreach ($tasks as [$title, $description, $status, $project, $author, $assignee]) {
            $task = new Task($title, $description, $status, $project, $author);
            $task->assignTo($assignee);
            $manager->persist($task);
        }

        $manager->flush();
    }
}
