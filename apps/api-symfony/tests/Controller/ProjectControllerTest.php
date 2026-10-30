<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test fonctionnel de /api/projects, calqué sur TaskControllerTest.
 * DAMA\DoctrineTestBundle annule la transaction de chaque test : pas de pollution entre tests.
 */
final class ProjectControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testListReturnsProjectsOrderedByName(): void
    {
        $this->createProject('Zèbre');
        $this->createProject('Abeille');

        $this->client->request('GET', '/api/projects');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['Abeille', 'Zèbre'], array_column($this->json(), 'name'));
    }

    public function testShowReturnsProject(): void
    {
        $id = $this->createProject('Formation PHP', 'Les bases');

        $this->client->request('GET', "/api/projects/$id");

        self::assertResponseStatusCodeSame(200);
        self::assertSame('Formation PHP', $this->json()['name']);
        self::assertSame('Les bases', $this->json()['description']);
    }

    public function testShowReturns404WhenProjectDoesNotExist(): void
    {
        $this->client->request('GET', '/api/projects/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateProject(): void
    {
        $this->client->jsonRequest('POST', '/api/projects', ['name' => 'Formation React']);

        self::assertResponseStatusCodeSame(201);
        $project = $this->json();
        self::assertSame('Formation React', $project['name']);
        self::assertResponseHeaderSame('Location', '/api/projects/' . $project['id']);
    }

    public function testCreateProjectWithDuplicateNameReturns422(): void
    {
        $this->createProject('Formation PHP');

        $this->client->jsonRequest('POST', '/api/projects', ['name' => 'Formation PHP']);

        self::assertResponseStatusCodeSame(422);
        self::assertContains('name', array_column($this->json()['violations'], 'propertyPath'));
    }

    public function testCreateProjectWithTooShortNameReturns422(): void
    {
        $this->client->jsonRequest('POST', '/api/projects', ['name' => 'A']);

        self::assertResponseStatusCodeSame(422);
        self::assertContains('name', array_column($this->json()['violations'], 'propertyPath'));
    }

    public function testPatchOnlyUpdatesGivenFields(): void
    {
        $id = $this->createProject('Formation PHP', 'Les bases');

        $this->client->jsonRequest('PATCH', "/api/projects/$id", ['name' => 'Formation PHP 8']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('Formation PHP 8', $this->json()['name']);
        self::assertSame('Les bases', $this->json()['description']);
    }

    public function testPatchWithOwnNameIsAllowed(): void
    {
        $id = $this->createProject('Formation PHP');

        $this->client->jsonRequest('PATCH', "/api/projects/$id", ['name' => 'Formation PHP']);

        self::assertResponseStatusCodeSame(200);
    }

    public function testPatchWithAnotherProjectNameReturns422(): void
    {
        $this->createProject('Formation PHP');
        $id = $this->createProject('Formation React');

        $this->client->jsonRequest('PATCH', "/api/projects/$id", ['name' => 'Formation PHP']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testDeleteProject(): void
    {
        $id = $this->createProject('À supprimer');

        $this->client->request('DELETE', "/api/projects/$id");
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/projects/$id");
        self::assertResponseStatusCodeSame(404);
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
