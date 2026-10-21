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
 * B3-2 — Commentaires (contrat, partie 2C).
 */
final class CommentControllerTest extends WebTestCase
{
    use LogsInUsers;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        // Prêt pour B2-4b (écritures authentifiées) : l'auteur du commentaire est l'utilisateur connecté.
        $this->user = $this->logIn($this->client);
    }

    public function testCreateThenListCommentsOldestFirst(): void
    {
        $taskId = $this->createTask();

        $this->client->jsonRequest('POST', "/api/tasks/$taskId/comments", ['content' => "Je m'en occupe"]);
        self::assertResponseStatusCodeSame(201);
        $first = $this->json();
        self::assertSame("Je m'en occupe", $first['content']);
        self::assertArrayHasKey('createdAt', $first);
        self::assertSame(
            ['id' => $this->user->getId(), 'email' => 'tester@taskboard.local', 'displayName' => 'Tester'],
            $first['author'],
            'author = l\'utilisateur connecté, au format User du contrat (jamais de mot de passe)',
        );
        self::assertArrayNotHasKey('task', $first);
        self::assertFalse($this->client->getResponse()->headers->has('Location'), 'pas de Location pour un commentaire');

        $this->client->jsonRequest('POST', "/api/tasks/$taskId/comments", ['content' => 'Terminé']);
        $second = $this->json();

        $this->client->request('GET', "/api/tasks/$taskId/comments");
        self::assertResponseIsSuccessful();
        self::assertSame([$first['id'], $second['id']], array_column($this->json(), 'id'));
    }

    public function testCommentsOfUnknownTaskReturn404(): void
    {
        $this->client->request('GET', '/api/tasks/999999/comments');
        self::assertResponseStatusCodeSame(404);

        $this->client->jsonRequest('POST', '/api/tasks/999999/comments', ['content' => 'x']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testInvalidContentReturns422(): void
    {
        $taskId = $this->createTask();

        foreach (['' => [], 'trop long' => ['content' => str_repeat('a', 1001)], 'vide' => ['content' => '']] as $case => $body) {
            $this->client->jsonRequest('POST', "/api/tasks/$taskId/comments", $body);

            self::assertResponseStatusCodeSame(422, (string) $case);
            self::assertContains('content', array_column($this->json()['violations'], 'propertyPath'));
        }
    }

    public function testDeleteComment(): void
    {
        $taskId = $this->createTask();
        $this->client->jsonRequest('POST', "/api/tasks/$taskId/comments", ['content' => 'À supprimer']);
        $id = $this->json()['id'];

        $this->client->request('DELETE', "/api/comments/$id");
        self::assertResponseStatusCodeSame(204);

        $this->client->request('DELETE', "/api/comments/$id");
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeletingATaskDeletesItsComments(): void
    {
        $taskId = $this->createTask();
        $this->client->jsonRequest('POST', "/api/tasks/$taskId/comments", ['content' => 'Orphelin ?']);
        $commentId = $this->json()['id'];

        $this->client->request('DELETE', "/api/tasks/$taskId");
        self::assertResponseStatusCodeSame(204);

        $this->client->request('DELETE', "/api/comments/$commentId");
        self::assertResponseStatusCodeSame(404, 'ON DELETE CASCADE a supprimé le commentaire');
    }

    private function createTask(): int
    {
        $task = new Task('Tâche commentée', author: $this->user); // B2-5 : seul l'auteur (ou un admin) peut supprimer la tâche
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
