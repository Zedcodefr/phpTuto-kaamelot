<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Projets de démonstration. Les références sont utilisées par AppFixtures pour rattacher les tâches.
 */
final class ProjectFixtures extends Fixture
{
    public const string PHP = 'project-php';
    public const string REACT = 'project-react';

    public function load(ObjectManager $manager): void
    {
        $php = new Project('Formation PHP', 'PHP, Symfony et Doctrine pour développeurs C#');
        $react = new Project('Formation React');

        $manager->persist($php);
        $manager->persist($react);
        $this->addReference(self::PHP, $php);
        $this->addReference(self::REACT, $react);

        $manager->flush();
    }
}
