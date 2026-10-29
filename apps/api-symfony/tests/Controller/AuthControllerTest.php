<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels de l'authentification (contrat, partie 2B).
 * Même principe que TaskControllerTest : requêtes HTTP en mémoire, chaque test annulé par DAMA.
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

    public function testRegisterCreatesUserWithoutExposingPassword(): void
    {
        $this->register(['email' => 'carol@test.local', 'password' => 'motdepasse', 'displayName' => 'Carol']);

        self::assertResponseStatusCodeSame(201);
        $user = $this->json();
        self::assertSame('carol@test.local', $user['email']);
        self::assertSame('Carol', $user['displayName']);
        self::assertIsInt($user['id']);
        // Le contrat : jamais de mot de passe dans les réponses, et pas de Location pour register.
        self::assertSame(['id', 'email', 'displayName'], array_keys($user));
        self::assertResponseNotHasHeader('Location');
    }

    public function testRegisterStoresHashedPassword(): void
    {
        $this->register(['email' => 'carol@test.local', 'password' => 'motdepasse', 'displayName' => 'Carol']);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'carol@test.local']);
        self::assertNotNull($user);
        self::assertNotSame('motdepasse', $user->getPassword());
        self::assertTrue(
            static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'motdepasse'),
        );
    }

    public function testRegisterWithAlreadyUsedEmailReturns422(): void
    {
        $payload = ['email' => 'carol@test.local', 'password' => 'motdepasse', 'displayName' => 'Carol'];
        $this->register($payload);
        self::assertResponseStatusCodeSame(201);

        $this->register($payload);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['email'], $this->violationPaths());
    }

    public function testRegisterWithInvalidEmailReturns422(): void
    {
        $this->register(['email' => 'pas-un-email', 'password' => 'motdepasse', 'displayName' => 'Carol']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['email'], $this->violationPaths());
    }

    public function testRegisterWithShortPasswordReturns422(): void
    {
        $this->register(['email' => 'carol@test.local', 'password' => '1234567', 'displayName' => 'Carol']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['password'], $this->violationPaths());
    }

    #[DataProvider('invalidDisplayNames')]
    public function testRegisterWithInvalidDisplayNameReturns422(string $displayName): void
    {
        $this->register(['email' => 'carol@test.local', 'password' => 'motdepasse', 'displayName' => $displayName]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['displayName'], $this->violationPaths());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDisplayNames(): iterable
    {
        yield 'trop court (1 caractère)' => ['C'];
        yield 'trop long (51 caractères)' => [str_repeat('a', 51)];
    }

    public function testRegisterWithMissingFieldsReturns422(): void
    {
        $this->register([]);

        self::assertResponseStatusCodeSame(422);
        self::assertEqualsCanonicalizing(['email', 'password', 'displayName'], $this->violationPaths());
    }

    public function testRegisterWithMalformedJsonReturns400(): void
    {
        $this->client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: '{oups');

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function register(array $payload): void
    {
        $this->client->jsonRequest('POST', '/api/auth/register', $payload);
    }

    /**
     * @return list<string>
     */
    private function violationPaths(): array
    {
        return array_values(array_unique(array_column($this->json()['violations'], 'propertyPath')));
    }

    /**
     * @return array<mixed>
     */
    private function json(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
