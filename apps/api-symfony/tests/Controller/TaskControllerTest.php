<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Task;
use App\Enum\TaskStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test fonctionnel : on démarre le kernel Symfony et on lui envoie de vraies requêtes HTTP
 * (en mémoire, sans serveur web). ≈ WebApplicationFactory<Program> + HttpClient en ASP.NET Core.
 *
 * Base utilisée : taskboard_test. Grâce à DAMA\DoctrineTestBundle, chaque test tourne
 * dans une transaction annulée à la fin : aucun test ne pollue les suivants.
 */
final class TaskControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testListReturnsTasksOrderedById(): void
    {
        $this->createTask('Première');
        $this->createTask('Seconde');

        $this->client->request('GET', '/api/tasks');

        self::assertResponseIsSuccessful();
        $titles = array_column($this->json(), 'title');
        self::assertSame(['Première', 'Seconde'], $titles);
    }

    public function testShowReturns404WhenTaskDoesNotExist(): void
    {
        $this->client->request('GET', '/api/tasks/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateTask(): void
    {
        $this->client->jsonRequest('POST', '/api/tasks', ['title' => 'Apprendre Symfony']);

        self::assertResponseStatusCodeSame(201);
        $task = $this->json();
        self::assertSame('Apprendre Symfony', $task['title']);
        self::assertSame('todo', $task['status']);
        self::assertResponseHeaderSame('Location', '/api/tasks/' . $task['id']);
    }

    public function testCreateTaskWithBlankTitleReturns422(): void
    {
        $this->client->jsonRequest('POST', '/api/tasks', ['title' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertContains('title', array_column($this->json()['violations'], 'propertyPath'));
    }

    public function testPatchOnlyUpdatesGivenFields(): void
    {
        $id = $this->createTask('Tâche', 'Sa description');

        $this->client->jsonRequest('PATCH', "/api/tasks/$id", ['status' => 'done']);

        self::assertResponseIsSuccessful();
        self::assertSame('done', $this->json()['status']);
        self::assertSame('Sa description', $this->json()['description']);
    }

    public function testDeleteTask(): void
    {
        $id = $this->createTask('À supprimer');

        $this->client->request('DELETE', "/api/tasks/$id");
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/tasks/$id");
        self::assertResponseStatusCodeSame(404);
    }

    private function createTask(string $title, ?string $description = null): int
    {
        $task = new Task($title, $description, TaskStatus::Todo);
        $this->em->persist($task);
        $this->em->flush();

        return $task->getId();
    }

    /**
     * @return array<mixed>
     */
    private function json(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
