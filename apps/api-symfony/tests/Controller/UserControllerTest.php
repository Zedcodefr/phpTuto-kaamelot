<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserControllerTest extends WebTestCase
{
    public function testListIsPublicAndSortedByDisplayName(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist(new User('zoe@taskboard.local', 'Zoé'));
        $em->persist(new User('bob@taskboard.local', 'Bob'));
        $em->flush();

        $client->request('GET', '/api/users');

        self::assertResponseIsSuccessful();
        $users = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['Bob', 'Zoé'], array_column($users, 'displayName'));
        self::assertSame(['id', 'email', 'displayName'], array_keys($users[0]));
    }
}
