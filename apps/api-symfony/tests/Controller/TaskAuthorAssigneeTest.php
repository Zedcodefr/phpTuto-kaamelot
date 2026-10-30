<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ApiToken;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Auteur et assigné d'une tâche (B2-4a). Dans un fichier à part pour ne pas toucher TaskControllerTest,
 * qui est partagé avec les autres développeurs.
 */
final class TaskAuthorAssigneeTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCreateWithTokenSetsAuthorFromConnectedUser(): void
    {
        $alice = $this->createUser('alice@test.local', 'Alice');
        $this->authenticateAs($alice);

        $this->client->jsonRequest('POST', '/api/tasks', ['title' => 'Avec auteur']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame($this->asUser($alice), $this->json()['author']);
        self::assertNull($this->json()['assignee']);
    }

    public function testCreateIgnoresAnAuthorSentByTheClient(): void
    {
        $alice = $this->createUser('alice@test.local', 'Alice');
        $bob = $this->createUser('bob@test.local', 'Bob');
        $this->authenticateAs($alice);

        $this->client->jsonRequest('POST', '/api/tasks', ['title' => 'Triche', 'author' => $bob->getId(), 'authorId' => $bob->getId()]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame($this->asUser($alice), $this->json()['author']);
    }

    public function testCreateWithoutTokenHasNoAuthor(): void
    {
        $this->client->jsonRequest('POST', '/api/tasks', ['title' => 'Anonyme']);

        self::assertResponseStatusCodeSame(201);
        self::assertNull($this->json()['author']);
    }

    public function testCreateWithAssignee(): void
    {
        $bob = $this->createUser('bob@test.local', 'Bob');

        $this->client->jsonRequest('POST', '/api/tasks', ['title' => 'Pour Bob', 'assigneeId' => $bob->getId()]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame($this->asUser($bob), $this->json()['assignee']);
    }

    public function testCreateWithUnknownAssigneeReturns422(): void
    {
        $this->client->jsonRequest('POST', '/api/tasks', ['title' => 'Inconnu', 'assigneeId' => 999999]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['assigneeId'], array_column($this->json()['violations'], 'propertyPath'));
    }

    public function testCreateWithNonIntegerAssigneeReturns422(): void
    {
        $this->client->jsonRequest('POST', '/api/tasks', ['title' => 'Mal typé', 'assigneeId' => 'abc']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testPatchAssignsAndChangesAssignee(): void
    {
        $bob = $this->createUser('bob@test.local', 'Bob');
        $carol = $this->createUser('carol@test.local', 'Carol');
        $id = $this->createTask();

        $this->client->jsonRequest('PATCH', "/api/tasks/$id", ['assigneeId' => $bob->getId()]);
        self::assertSame($this->asUser($bob), $this->json()['assignee']);

        $this->client->jsonRequest('PATCH', "/api/tasks/$id", ['assigneeId' => $carol->getId()]);
        self::assertSame($this->asUser($carol), $this->json()['assignee']);
    }

    public function testPatchWithoutAssigneeIdKeepsAssignee(): void
    {
        $bob = $this->createUser('bob@test.local', 'Bob');
        $id = $this->createTask($bob);

        $this->client->jsonRequest('PATCH', "/api/tasks/$id", ['status' => 'done']);

        self::assertResponseIsSuccessful();
        self::assertSame($this->asUser($bob), $this->json()['assignee']);
    }

    public function testPatchWithExplicitNullUnassigns(): void
    {
        $bob = $this->createUser('bob@test.local', 'Bob');
        $id = $this->createTask($bob);

        $this->client->jsonRequest('PATCH', "/api/tasks/$id", ['assigneeId' => null]);

        self::assertResponseIsSuccessful();
        self::assertNull($this->json()['assignee']);
    }

    public function testPatchWithUnknownAssigneeReturns422AndKeepsAssignee(): void
    {
        $bob = $this->createUser('bob@test.local', 'Bob');
        $id = $this->createTask($bob);

        $this->client->jsonRequest('PATCH', "/api/tasks/$id", ['assigneeId' => 999999]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['assigneeId'], array_column($this->json()['violations'], 'propertyPath'));

        $this->client->request('GET', "/api/tasks/$id");
        self::assertSame($this->asUser($bob), $this->json()['assignee']);
    }

    public function testPatchNeverChangesAuthor(): void
    {
        $alice = $this->createUser('alice@test.local', 'Alice');
        $bob = $this->createUser('bob@test.local', 'Bob');
        $id = $this->createTask(author: $alice);
        $this->authenticateAs($bob);

        $this->client->jsonRequest('PATCH', "/api/tasks/$id", ['title' => 'Renommée', 'author' => $bob->getId()]);

        self::assertResponseIsSuccessful();
        self::assertSame($this->asUser($alice), $this->json()['author']);
    }

    public function testUserIsSerializedWithoutSensitiveFields(): void
    {
        $alice = $this->createUser('alice@test.local', 'Alice');
        $id = $this->createTask($alice, $alice);

        $this->client->request('GET', "/api/tasks/$id");

        foreach (['author', 'assignee'] as $field) {
            self::assertSame(['id', 'email', 'displayName'], array_keys($this->json()[$field]));
        }
    }

    private function createUser(string $email, string $displayName): User
    {
        $user = new User($email, $displayName, '');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createTask(?User $assignee = null, ?User $author = null): int
    {
        $task = new Task('Tâche', author: $author);
        $task->assignTo($assignee);
        $this->em->persist($task);
        $this->em->flush();

        return $task->getId();
    }

    private function authenticateAs(User $user): void
    {
        $token = new ApiToken($user);
        $this->em->persist($token);
        $this->em->flush();
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token->getToken());
    }

    /**
     * @return array{id: int|null, email: string, displayName: string}
     */
    private function asUser(User $user): array
    {
        return ['id' => $user->getId(), 'email' => $user->getEmail(), 'displayName' => $user->getDisplayName()];
    }

    /**
     * @return array<mixed>
     */
    private function json(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
