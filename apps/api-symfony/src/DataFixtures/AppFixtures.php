<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Task;
use App\Enum\TaskStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Données de démonstration (≈ HasData() / seeding EF Core).
 * Chargées par `make sf-db-reset`. Mêmes données que apps/api-php/database/schema.sql.
 */
final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $tasks = [
            ['Installer l\'environnement', 'make install puis make up', TaskStatus::Done],
            ['Découvrir la syntaxe PHP', 'Variables, tableaux, classes, enums...', TaskStatus::InProgress],
            ['Écrire l\'API en PHP pur', 'Router, PDO, JSON', TaskStatus::Todo],
            ['Migrer l\'API vers Symfony', null, TaskStatus::Todo],
        ];

        foreach ($tasks as [$title, $description, $status]) {
            $manager->persist(new Task($title, $description, $status));
        }

        $manager->flush();
    }
}
