<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * B2-5 — DELETE /api/tasks/{id} : l'auteur ou un ROLE_ADMIN, sinon 403.
 */
final class TaskVoterTest extends WebTestCase
{
    use LogsInUsers;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testBobCannotDeleteATaskOfAlice(): void
    {
        $id = $this->createTask($this->user('alice@taskboard.local', ['ROLE_ADMIN']));
        $this->logIn($this->client, 'bob@taskboard.local');

        $this->client->request('DELETE', "/api/tasks/$id");

        self::assertResponseStatusCodeSame(403);
    }

    public function testBobCanDeleteHisOwnTask(): void
    {
        $bob = $this->logIn($this->client, 'bob@taskboard.local');
        $id = $this->createTask($bob);

        $this->client->request('DELETE', "/api/tasks/$id");

        self::assertResponseStatusCodeSame(204);
    }

    public function testAnAdminCanDeleteAnyTaskEvenWithoutAuthor(): void
    {
        $bobsTask = $this->createTask($this->user('bob@taskboard.local'));
        $orphanTask = $this->createTask(null);
        $this->logIn($this->client, 'alice@taskboard.local', ['ROLE_ADMIN']);

        $this->client->request('DELETE', "/api/tasks/$bobsTask");
        self::assertResponseStatusCodeSame(204);
        $this->client->request('DELETE', "/api/tasks/$orphanTask");
        self::assertResponseStatusCodeSame(204);
    }

    public function testATaskWithoutAuthorCanOnlyBeDeletedByAnAdmin(): void
    {
        $id = $this->createTask(null);
        $this->logIn($this->client, 'bob@taskboard.local');

        $this->client->request('DELETE', "/api/tasks/$id");

        self::assertResponseStatusCodeSame(403);
    }

    /** @param list<string> $roles */
    private function user(string $email, array $roles = []): User
    {
        $user = new User($email, 'User', $roles);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createTask(?User $author): int
    {
        $task = new Task('Tâche', author: $author);
        $this->em->persist($task);
        $this->em->flush();

        return $task->getId();
    }
}
