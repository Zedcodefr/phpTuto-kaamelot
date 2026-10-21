<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Project;
use App\Entity\Task;
use App\Tests\Security\LogsInUsers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Contrat partie 2A — Projets. Base taskboard_test, chaque test dans une transaction annulée (DAMA).
 */
final class ProjectControllerTest extends WebTestCase
{
    use LogsInUsers;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->logIn($this->client); // B2-4b : les écritures exigeront un utilisateur connecté
    }

    public function testListIsOrderedByName(): void
    {
        $this->createProject('Zèbre');
        $this->createProject('Alpha');

        $this->client->request('GET', '/api/projects');

        self::assertResponseIsSuccessful();
        $projects = $this->json();
        self::assertSame(['Alpha', 'Zèbre'], array_column($projects, 'name'));
        self::assertSame(
            ['id', 'name', 'description', 'createdAt', 'taskCount'],
            array_keys($projects[0]),
        );
    }

    public function testShowReturns404WhenProjectDoesNotExist(): void
    {
        $this->client->request('GET', '/api/projects/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateProject(): void
    {
        $this->client->jsonRequest('POST', '/api/projects', ['name' => 'Formation Doctrine']);

        self::assertResponseStatusCodeSame(201);
        $project = $this->json();
        self::assertSame('Formation Doctrine', $project['name']);
        self::assertNull($project['description']);
        self::assertSame(0, $project['taskCount']);
        self::assertResponseHeaderSame('Location', '/api/projects/' . $project['id']);
    }

    public function testCreateWithDuplicateNameReturns422OnName(): void
    {
        $this->createProject('Déjà pris');

        $this->client->jsonRequest('POST', '/api/projects', ['name' => 'Déjà pris']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['name'], array_column($this->json()['violations'], 'propertyPath'));
    }

    public function testCreateWithTooShortNameReturns422(): void
    {
        $this->client->jsonRequest('POST', '/api/projects', ['name' => 'X']);

        self::assertResponseStatusCodeSame(422);
        self::assertContains('name', array_column($this->json()['violations'], 'propertyPath'));
    }

    public function testPatchKeepsItsOwnNameAndClearsDescriptionWithNull(): void
    {
        $id = $this->createProject('Mon projet', 'Une description');

        $this->client->jsonRequest('PATCH', "/api/projects/$id", ['name' => 'Mon projet', 'description' => null]);

        self::assertResponseIsSuccessful();
        self::assertSame('Mon projet', $this->json()['name']);
        self::assertNull($this->json()['description']);
    }

    public function testPatchWithNameOfAnotherProjectReturns422(): void
    {
        $this->createProject('Premier');
        $id = $this->createProject('Second');

        $this->client->jsonRequest('PATCH', "/api/projects/$id", ['name' => 'Premier']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testDeleteEmptyProject(): void
    {
        $id = $this->createProject('À supprimer');

        $this->client->request('DELETE', "/api/projects/$id");
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $this->client->getResponse()->getContent());

        $this->client->request('GET', "/api/projects/$id");
        self::assertResponseStatusCodeSame(404);
    }

    // ---- B1-3 : relation Task → Project

    public function testCreateTaskInProjectExposesProjectSummary(): void
    {
        $projectId = $this->createProject('Formation PHP');

        $this->client->jsonRequest('POST', '/api/tasks', ['title' => 'Apprendre Doctrine', 'projectId' => $projectId]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['id' => $projectId, 'name' => 'Formation PHP'], $this->json()['project']);

        $this->client->request('GET', "/api/projects/$projectId");
        self::assertSame(1, $this->json()['taskCount']);
    }

    public function testCreateTaskWithUnknownProjectReturns422OnProjectId(): void
    {
        $this->client->jsonRequest('POST', '/api/tasks', ['title' => 'abc', 'projectId' => 999999]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['projectId'], array_column($this->json()['violations'], 'propertyPath'));
    }

    public function testPatchMovesThenDetachesTask(): void
    {
        $projectId = $this->createProject('Formation PHP');
        $taskId = $this->createTask('Tâche', null);

        $this->client->jsonRequest('PATCH', "/api/tasks/$taskId", ['projectId' => $projectId]);
        self::assertResponseIsSuccessful();
        self::assertSame($projectId, $this->json()['project']['id']);

        $this->client->jsonRequest('PATCH', "/api/tasks/$taskId", ['status' => 'done']);
        self::assertSame($projectId, $this->json()['project']['id'], 'projectId absent : le projet ne change pas');

        $this->client->jsonRequest('PATCH', "/api/tasks/$taskId", ['projectId' => null]);
        self::assertResponseIsSuccessful();
        self::assertNull($this->json()['project']);
    }

    public function testListTasksExposesProjectWithoutCircularReference(): void
    {
        $projectId = $this->createProject('Formation PHP');
        $this->createTask('Dans le projet', $projectId);
        $this->createTask('Sans projet', null);

        $this->client->request('GET', '/api/tasks');

        self::assertResponseIsSuccessful();
        self::assertSame(
            [['id' => $projectId, 'name' => 'Formation PHP'], null],
            array_column($this->json(), 'project'),
        );
    }

    // ---- B1-4 : tâches d'un projet et suppression protégée

    public function testProjectTasks(): void
    {
        $projectId = $this->createProject('Formation PHP');
        $this->createTask('B', $projectId);
        $this->createTask('Ailleurs', null);
        $this->createTask('A', $projectId);

        $this->client->request('GET', "/api/projects/$projectId/tasks");

        self::assertResponseIsSuccessful();
        self::assertSame(['B', 'A'], array_column($this->json(), 'title'), 'triées par id');

        $this->client->request('GET', '/api/projects/999999/tasks');
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteProjectWithTasksReturns409(): void
    {
        $projectId = $this->createProject('Non vide');
        $this->createTask('Tâche', $projectId);

        $this->client->request('DELETE', "/api/projects/$projectId");

        self::assertResponseStatusCodeSame(409);
    }

    private function createTask(string $title, ?int $projectId): int
    {
        $project = null === $projectId ? null : $this->em->find(Project::class, $projectId);
        $task = new Task($title, null, project: $project);
        $this->em->persist($task);
        $this->em->flush();

        return $task->getId();
    }

    private function createProject(string $name, ?string $description = null): int
    {
        $project = new Project($name, $description);
        $this->em->persist($project);
        $this->em->flush();

        return $project->getId();
    }

    /**
     * @return array<mixed>
     */
    private function json(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
