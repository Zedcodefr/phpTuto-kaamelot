<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ApiToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * B2-2 / B2-3 : register, login, /api/me. La base de test est vide (pas de fixtures) : chaque test crée ses utilisateurs.
 */
final class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testRegisterReturns201AndTheUserWithoutPassword(): void
    {
        $this->client->jsonRequest('POST', '/api/auth/register', [
            'email' => 'carol@taskboard.local', 'password' => 'secret123', 'displayName' => 'Carol',
        ]);

        self::assertResponseStatusCodeSame(201);
        $user = $this->json();
        self::assertSame(['id', 'email', 'displayName'], array_keys($user));
        self::assertSame('carol@taskboard.local', $user['email']);

        $stored = $this->em->getRepository(User::class)->find($user['id']);
        self::assertNotSame('secret123', $stored->getPassword(), 'Le mot de passe doit être hashé.');
    }

    public function testRegisterWithAnEmailAlreadyTakenReturns422OnEmail(): void
    {
        $this->createUser('alice@taskboard.local');

        $this->client->jsonRequest('POST', '/api/auth/register', [
            'email' => 'Alice@TaskBoard.local', 'password' => 'secret123', 'displayName' => 'Alice 2',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['email'], $this->violatedFields());
    }

    public function testRegisterWithInvalidDataReturns422OnEachField(): void
    {
        $this->client->jsonRequest('POST', '/api/auth/register', [
            'email' => 'pas-un-email', 'password' => 'court', 'displayName' => 'A',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['displayName', 'email', 'password'], $this->violatedFields());
    }

    public function testLoginReturnsATokenThatGivesAccessToMe(): void
    {
        $this->createUser('alice@taskboard.local', 'Alice');

        $this->client->jsonRequest('POST', '/api/auth/login', ['email' => 'alice@taskboard.local', 'password' => 'password']);

        self::assertResponseIsSuccessful();
        $body = $this->json();
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $body['token']);
        self::assertSame(['id', 'email', 'displayName'], array_keys($body['user']));

        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $body['token']]);

        self::assertResponseIsSuccessful();
        self::assertSame('Alice', $this->json()['displayName']);
    }

    public function testLoginWithAWrongPasswordOrAnUnknownEmailReturns401(): void
    {
        $this->createUser('alice@taskboard.local');

        $this->client->jsonRequest('POST', '/api/auth/login', ['email' => 'alice@taskboard.local', 'password' => 'wrong']);
        self::assertResponseStatusCodeSame(401);

        $this->client->jsonRequest('POST', '/api/auth/login', ['email' => 'nobody@taskboard.local', 'password' => 'password']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testMeWithoutTokenReturns401(): void
    {
        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testMeWithAnUnknownOrExpiredTokenReturns401(): void
    {
        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer faux']);
        self::assertResponseStatusCodeSame(401);
        self::assertSame(401, $this->json()['status']);

        [$expired, $plainToken] = ApiToken::issueFor($this->createUser('alice@taskboard.local'), new \DateTimeImmutable('-25 hours'));
        $this->em->persist($expired);
        $this->em->flush();

        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $plainToken]);
        self::assertResponseStatusCodeSame(401);
    }

    private function createUser(string $email, string $displayName = 'Test'): User
    {
        $user = new User($email, $displayName);
        $user->changePassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'password'));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /** @return array<mixed> */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return list<string> champs en erreur, triés */
    private function violatedFields(): array
    {
        $fields = array_unique(array_column($this->json()['violations'], 'propertyPath'));
        sort($fields);

        return $fields;
    }
}
