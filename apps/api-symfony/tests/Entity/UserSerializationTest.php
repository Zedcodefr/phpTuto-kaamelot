<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Filet de sécurité : un User sérialisé tel quel (ex. Task.author) ne doit jamais
 * exposer le hash du mot de passe, les rôles ni l'identifiant de sécurité.
 */
final class UserSerializationTest extends KernelTestCase
{
    public function testUserIsSerializedAsIdEmailDisplayNameOnly(): void
    {
        self::bootKernel();
        $serializer = static::getContainer()->get(SerializerInterface::class);
        $user = new User('alice@test.local', 'Alice', 'hash-secret');
        $user->setRoles(['ROLE_ADMIN']);

        $json = $serializer->serialize($user, 'json');

        self::assertSame(['id', 'email', 'displayName'], array_keys(json_decode($json, true, flags: JSON_THROW_ON_ERROR)));
        self::assertStringNotContainsString('hash-secret', $json);
    }
}
