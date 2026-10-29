<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Comment;
use App\Entity\Task;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Commentaires de démonstration, chargés après les tâches (getDependencies).
 */
final class CommentFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $comments = [
            [AppFixtures::TASK_REFERENCE . '0', 'Fait en 5 minutes, rien à signaler'],
            [AppFixtures::TASK_REFERENCE . '1', 'Les enums PHP 8.1 ressemblent beaucoup à ceux de C#'],
            [AppFixtures::TASK_REFERENCE . '1', 'Attention aux tableaux : ils sont copiés par valeur'],
            [AppFixtures::TASK_REFERENCE . '2', 'Je m\'en occupe'],
        ];

        foreach ($comments as [$taskReference, $content]) {
            $manager->persist(new Comment($this->getReference($taskReference, Task::class), $content));
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }
}
