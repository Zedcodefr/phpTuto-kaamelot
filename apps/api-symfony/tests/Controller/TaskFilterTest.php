<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Project;
use App\Entity\Task;
use App\Enum\TaskStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * B3-1 — Filtres et pagination de GET /api/tasks (contrat, partie 2C).
 */
final class TaskFilterTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    // Comme TaskControllerTest, on part d'une table task vide (base de test, transaction annulée par DAMA).
    public function testWithoutParametersReturnsEverythingWithTotalHeader(): void
    {
        $this->createTask('Une', TaskStatus::Todo);
        $this->createTask('Deux', TaskStatus::Done);

        $this->client->request('GET', '/api/tasks');

        self::assertResponseIsSuccessful();
        self::assertSame(['Une', 'Deux'], array_column($this->json(), 'title'));
        self::assertResponseHeaderSame('X-Total-Count', '2');
    }

    public function testFilterByStatusAndPaginate(): void
    {
        $a = $this->createTask('A', TaskStatus::InProgress);
        $this->createTask('Pas en cours', TaskStatus::Todo);
        $b = $this->createTask('B', TaskStatus::InProgress);
        $c = $this->createTask('C', TaskStatus::InProgress);

        $this->client->request('GET', '/api/tasks?status=in_progress&limit=2');
        self::assertResponseIsSuccessful();
        self::assertSame([$a, $b], array_column($this->json(), 'id'));
        self::assertResponseHeaderSame('X-Total-Count', '3', 'Le total ignore la pagination, pas le filtre');

        $this->client->request('GET', '/api/tasks?status=in_progress&limit=2&page=2');
        self::assertSame([$c], array_column($this->json(), 'id'));
    }

    public function testSearchIsCaseInsensitiveOnTitleAndDescription(): void
    {
        $inTitle = $this->createTask('Apprendre ZORGLUB');
        $inDescription = $this->createTask('Autre', description: 'avec du zorglub dedans');
        $this->createTask('Sans rapport');

        $this->client->request('GET', '/api/tasks?search=ZorGlub');

        self::assertResponseIsSuccessful();
        self::assertSame([$inTitle, $inDescription], array_column($this->json(), 'id'));
        self::assertResponseHeaderSame('X-Total-Count', '2');
    }

    public function testFilterByProject(): void
    {
        $project = new Project('Projet filtré');
        $this->em->persist($project);
        $inProject = new Task('Dans le projet', null, TaskStatus::Todo, $project);
        $this->em->persist($inProject);
        $this->em->flush();
        $this->createTask('Hors projet');

        $this->client->request('GET', '/api/tasks?projectId=' . $project->getId());

        self::assertResponseIsSuccessful();
        self::assertSame([$inProject->getId()], array_column($this->json(), 'id'));
        self::assertResponseHeaderSame('X-Total-Count', '1');
    }

    public function testInvalidParametersReturn422(): void
    {
        foreach (['?status=nimporte' => 'status', '?limit=500' => 'limit', '?page=0' => 'page'] as $query => $field) {
            $this->client->request('GET', '/api/tasks' . $query);

            self::assertResponseStatusCodeSame(422, $query);
            self::assertContains($field, array_column($this->json()['violations'], 'propertyPath'), $query);
        }
    }

    private function createTask(string $title, TaskStatus $status = TaskStatus::Todo, ?string $description = null): int
    {
        $task = new Task($title, $description, $status);
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
