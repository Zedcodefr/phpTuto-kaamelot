<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Projets de démonstration. Les références permettent aux autres fixtures
 * (ex. tâches) de s'y rattacher via $this->getReference(...).
 */
final class ProjectFixtures extends Fixture
{
    public const PROJECT_PHP = 'project-php';
    public const PROJECT_REACT = 'project-react';

    public function load(ObjectManager $manager): void
    {
        $projects = [
            self::PROJECT_PHP => new Project('Formation PHP'),
            self::PROJECT_REACT => new Project('Formation React'),
        ];

        foreach ($projects as $reference => $project) {
            $manager->persist($project);
            $this->addReference($reference, $project);
        }

        $manager->flush();
    }
}
