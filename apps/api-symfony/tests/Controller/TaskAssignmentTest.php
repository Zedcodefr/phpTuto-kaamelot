<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\Tests\Security\LogsInUsers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * B2-4a — Task.author (renseigné à la création) et Task.assignee (assigneeId).
 */
final class TaskAssignmentTest extends WebTestCase
{
    use LogsInUsers;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCreateSetsTheAuthorToTheConnectedUserAndAcceptsAnAssignee(): void
    {
        $alice = $this->logIn($this->client, 'alice@taskboard.local');
        $bob = $this->createUser('bob@taskboard.local', 'Bob');

        $this->client->jsonRequest('POST', '/api/tasks', ['title' => 'Avec auteur', 'assigneeId' => $bob->getId()]);

        self::assertResponseStatusCodeSame(201);
        $task = $this->json();
        self::assertSame(['id' => $alice->getId(), 'email' => 'alice@taskboard.local', 'displayName' => 'Tester'], $task['author']);
        self::assertSame(['id' => $bob->getId(), 'email' => 'bob@taskboard.local', 'displayName' => 'Bob'], $task['assignee']);
    }

    public function testUnknownAssigneeReturns422OnAssigneeId(): void
    {
        $this->logIn($this->client);

        $this->client->jsonRequest('POST', '/api/tasks', ['title' => 'Assignée à personne', 'assigneeId' => 999999]);

        self::assertResponseStatusCodeSame(422);
        self::assertContains('assigneeId', array_column($this->json()['violations'], 'propertyPath'));
    }

    public function testPatchAssignsThenUnassignsWithAnExplicitNull(): void
    {
        $this->logIn($this->client);
        $bob = $this->createUser('bob@taskboard.local', 'Bob');
        $task = new Task('À assigner');
        $this->em->persist($task);
        $this->em->flush();
        $id = $task->getId();

        $this->client->jsonRequest('PATCH', "/api/tasks/$id", ['assigneeId' => $bob->getId()]);
        self::assertResponseIsSuccessful();
        self::assertSame('Bob', $this->json()['assignee']['displayName']);

        $this->client->jsonRequest('PATCH', "/api/tasks/$id", ['title' => 'Toujours assignée']);
        self::assertSame('Bob', $this->json()['assignee']['displayName'], 'Un champ absent ne change rien.');

        $this->client->jsonRequest('PATCH', "/api/tasks/$id", ['assigneeId' => null]);
        self::assertResponseIsSuccessful();
        self::assertNull($this->json()['assignee']);
        self::assertNull($this->json()['author'], 'Tâche créée sans auteur : author reste null.');
    }

    private function createUser(string $email, string $displayName): User
    {
        $user = new User($email, $displayName);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /** @return array<mixed> */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
