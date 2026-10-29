<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Comptes de démonstration du contrat (partie 2B) : mot de passe `password`.
 */
final class UserFixtures extends Fixture
{
    public const USER_ALICE = 'user-alice';
    public const USER_BOB = 'user-bob';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $users = [
            self::USER_ALICE => ['alice@taskboard.local', 'Alice', ['ROLE_ADMIN']],
            self::USER_BOB => ['bob@taskboard.local', 'Bob', []],
        ];

        foreach ($users as $reference => [$email, $displayName, $roles]) {
            $user = new User($email, $displayName, '');
            $user->changePassword($this->hasher->hashPassword($user, 'password'));
            $user->setRoles($roles);

            $manager->persist($user);
            $this->addReference($reference, $user);
        }

        $manager->flush();
    }
}
