<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ApiToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Teste l'authentification par token (firewall `api`) à travers GET /api/me et une route publique.
 * Les tokens sont de vrais ApiToken en base, envoyés dans l'en-tête Authorization comme le ferait un client.
 */
final class MeControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testMeReturnsCurrentUserWithValidToken(): void
    {
        $token = $this->createToken();

        $this->client->request('GET', '/api/me', server: $this->bearer($token->getToken()));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            ['id' => $token->getUser()->getId(), 'email' => 'carol@test.local', 'displayName' => 'Carol'],
            $this->json(),
        );
    }

    public function testMeWithoutTokenReturns401(): void
    {
        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(401, $this->json()['status']);
    }

    public function testUnknownTokenReturns401WithContractBody(): void
    {
        $this->client->request('GET', '/api/me', server: $this->bearer('faux'));

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
        self::assertSame(
            ['title' => 'Unauthorized', 'status' => 401, 'detail' => 'Invalid or expired token.'],
            $this->json(),
        );
    }

    public function testExpiredTokenReturns401(): void
    {
        $token = $this->createToken();
        $this->expire($token);

        $this->client->request('GET', '/api/me', server: $this->bearer($token->getToken()));

        self::assertResponseStatusCodeSame(401);
        self::assertSame('Invalid or expired token.', $this->json()['detail']);
    }

    public function testInvalidTokenIsRefusedEvenOnPublicRoute(): void
    {
        $this->client->request('GET', '/api/tasks', server: $this->bearer('faux'));

        self::assertResponseStatusCodeSame(401);
    }

    public function testPublicRouteWorksWithoutToken(): void
    {
        $this->client->request('GET', '/api/tasks');

        self::assertResponseStatusCodeSame(200);
    }

    private function createToken(): ApiToken
    {
        $user = new User('carol@test.local', 'Carol', '');
        $token = new ApiToken($user);
        $this->em->persist($user);
        $this->em->persist($token);
        $this->em->flush();

        return $token;
    }

    private function expire(ApiToken $token): void
    {
        // Pas de setter sur l'entité (un token ne se prolonge ni ne se raccourcit) : on force la valeur pour le test.
        (new \ReflectionProperty(ApiToken::class, 'expiresAt'))->setValue($token, new \DateTimeImmutable('-1 second'));
        $this->em->flush();
    }

    /**
     * @return array<string, string>
     */
    private function bearer(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
    }

    /**
     * @return array<mixed>
     */
    private function json(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
