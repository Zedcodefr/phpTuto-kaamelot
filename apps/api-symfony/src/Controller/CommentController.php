<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateCommentInput;
use App\Entity\Comment;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Commentaires (contrat, partie 2C). Routes imbriquées sous /api/tasks/{id} pour la lecture et la création,
 * /api/comments/{id} pour la suppression.
 */
#[Route('/api', name: 'api_', format: 'json')]
final class CommentController extends AbstractController
{
    private const array READ = ['groups' => ['comment:read']];

    public function __construct(
        private readonly CommentRepository $comments,
        private readonly EntityManagerInterface $em,
    ) {
    }

    // Task $task : chargée depuis {id} par l'EntityValueResolver, 404 automatique si elle n'existe pas.
    #[Route('/tasks/{id}/comments', name: 'task_comments_list', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function list(Task $task): JsonResponse
    {
        return $this->json($this->comments->findByTaskOldestFirst($task), context: self::READ);
    }

    #[Route('/tasks/{id}/comments', name: 'task_comments_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function create(Task $task, #[MapRequestPayload] CreateCommentInput $input): JsonResponse
    {
        // Auteur = utilisateur connecté (null tant que la requête n'est pas authentifiée, cf. B2-4b).
        $author = $this->getUser();
        $comment = new Comment($task, $input->content, $author instanceof User ? $author : null);
        $this->em->persist($comment);
        $this->em->flush();

        // Pas d'en-tête Location : il n'existe pas de GET /api/comments/{id} (règle « Création » du contrat)
        return $this->json($comment, Response::HTTP_CREATED, context: self::READ);
    }

    #[Route('/comments/{id}', name: 'comments_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Comment $comment): Response
    {
        $this->em->remove($comment);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
