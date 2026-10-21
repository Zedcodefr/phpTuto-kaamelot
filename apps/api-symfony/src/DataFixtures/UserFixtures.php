<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Comptes de démonstration du contrat : alice (ROLE_ADMIN) et bob, mot de passe "password".
 * Références "user-alice" / "user-bob" pour les autres fixtures (auteur des tâches, des commentaires...).
 */
final class UserFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        foreach ([
            'user-alice' => new User('alice@taskboard.local', 'Alice', ['ROLE_ADMIN']),
            'user-bob' => new User('bob@taskboard.local', 'Bob'),
        ] as $reference => $user) {
            $user->changePassword($this->hasher->hashPassword($user, 'password'));
            $manager->persist($user);
            $this->addReference($reference, $user);
        }

        $manager->flush();
    }
}
