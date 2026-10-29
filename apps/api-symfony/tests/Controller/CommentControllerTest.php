<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Comment;
use App\Entity\Task;
use App\Enum\TaskStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;


final class CommentControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testListReturnsCommentsOfTaskFromOldestToNewest(): void
    {
        $task = $this->createTask('Tâche commentée');
        $this->createComment($task, 'Premier');
        $this->createComment($task, 'Second');
        $this->createComment($this->createTask('Autre tâche'), 'Pas pour cette tâche');

        $this->client->request('GET', "/api/tasks/{$task->getId()}/comments");

        self::assertResponseIsSuccessful();
        self::assertSame(['Premier', 'Second'], array_column($this->json(), 'content'));
    }

    public function testListReturns404WhenTaskDoesNotExist(): void
    {
        $this->client->request('GET', '/api/tasks/999999/comments');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateComment(): void
    {
        $task = $this->createTask('Tâche');

        $this->client->jsonRequest('POST', "/api/tasks/{$task->getId()}/comments", ['content' => 'Je m\'en occupe']);

        self::assertResponseStatusCodeSame(201);
        self::assertFalse($this->client->getResponse()->headers->has('Location'));
        $comment = $this->json();
        self::assertSame('Je m\'en occupe', $comment['content']);
        self::assertIsInt($comment['id']);
        self::assertArrayHasKey('createdAt', $comment);
        self::assertArrayNotHasKey('task', $comment);
    }

    public function testCreateCommentReturns404WhenTaskDoesNotExist(): void
    {
        $this->client->jsonRequest('POST', '/api/tasks/999999/comments', ['content' => 'Perdu']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateCommentWithBlankContentReturns422(): void
    {
        $task = $this->createTask('Tâche');

        $this->client->jsonRequest('POST', "/api/tasks/{$task->getId()}/comments", ['content' => '   ']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['content'], array_column($this->json()['violations'], 'propertyPath'));
    }

    public function testCreateCommentWithTooLongContentReturns422(): void
    {
        $task = $this->createTask('Tâche');

        $this->client->jsonRequest('POST', "/api/tasks/{$task->getId()}/comments", ['content' => str_repeat('a', 1001)]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testDeleteComment(): void
    {
        $task = $this->createTask('Tâche');
        $commentId = $this->createComment($task, 'À supprimer')->getId();

        $this->client->request('DELETE', "/api/comments/$commentId");
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/tasks/{$task->getId()}/comments");
        self::assertSame([], $this->json());
    }

    public function testDeleteCommentReturns404WhenCommentDoesNotExist(): void
    {
        $this->client->request('DELETE', '/api/comments/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteTaskAlsoDeletesItsComments(): void
    {
        $task = $this->createTask('Tâche');
        $taskId = $task->getId();
        $commentId = $this->createComment($task, 'Supprimé avec la tâche')->getId();

        $this->client->request('DELETE', "/api/tasks/$taskId");

        self::assertResponseStatusCodeSame(204);
        $this->em->clear();
        self::assertNull($this->em->find(Comment::class, $commentId));
    }

    private function createTask(string $title): Task
    {
        $task = new Task($title, null, TaskStatus::Todo);
        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }

    private function createComment(Task $task, string $content): Comment
    {
        $comment = new Comment($task, $content);
        $this->em->persist($comment);
        $this->em->flush();

        return $comment;
    }

    /**
     * @return array<mixed>
     */
    private function json(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
