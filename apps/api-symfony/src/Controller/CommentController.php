<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateCommentInput;
use App\Entity\Comment;
use App\Entity\Task;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/api', name: 'api_comments_', format: 'json')]
final class CommentController extends AbstractController
{
    public function __construct(
        private readonly CommentRepository $commentsRepo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/tasks/{id}/comments', name: 'list', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function list(Task $task): JsonResponse
    {
        return $this->json($this->commentsRepo->findByTask($task));
    }

    #[Route('/tasks/{id}/comments', name: 'create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function create(Task $task, #[MapRequestPayload] CreateCommentInput $input): JsonResponse
    {
        $comment = new Comment($task, $input->content);

        $this->em->persist($comment);
        $this->em->flush();

        return $this->json($comment, Response::HTTP_CREATED);
    }

    #[Route('/comments/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Comment $comment): Response
    {
        $this->em->remove($comment);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
