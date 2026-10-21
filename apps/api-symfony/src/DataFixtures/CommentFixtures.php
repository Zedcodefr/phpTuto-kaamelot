<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Comment;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * B3-2 — Commentaires de démonstration. Chargés APRÈS les tâches et les utilisateurs.
 * AppFixtures (fichier partagé) n'expose pas de références de tâches : on relit les tâches par leur titre,
 * ce qui évite de modifier AppFixtures.
 */
final class CommentFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [AppFixtures::class, UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $tasks = $manager->getRepository(Task::class);
        $alice = $this->getReference('user-alice', User::class);
        $bob = $this->getReference('user-bob', User::class);

        $comments = [
            ['Découvrir la syntaxe PHP', 'Je m\'en occupe', $alice],
            ['Découvrir la syntaxe PHP', 'Attention à == contre ===', $bob],
            ['Écrire l\'API en PHP pur', 'Le Lot C est vert', null],
        ];

        foreach ($comments as [$title, $content, $author]) {
            $task = $tasks->findOneBy(['title' => $title]) ?? throw new \LogicException("Tâche « $title » absente");
            $manager->persist(new Comment($task, $content, $author));
        }

        $manager->flush();
    }
}
