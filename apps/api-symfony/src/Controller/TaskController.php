<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateTaskInput;
use App\Dto\UpdateTaskInput;
use App\Dto\TaskFilter;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Équivalent d'un [ApiController] ASP.NET Core avec [Route("api/tasks")].
 *
 * - format: 'json' => les erreurs (404, 422...) sont rendues en JSON "problem details"
 * - les dépendances sont injectées par autowiring, dans le constructeur OU dans l'action
 */
#[Route('/api/tasks', name: 'api_tasks_', format: 'json')]
final class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] TaskFilter $filter = new TaskFilter()): JsonResponse
    {
        $paginator = $this->tasks->search($filter);

        return $this->json(iterator_to_array($paginator), headers: ['X-Total-Count' => count($paginator)]);
    }

    // Task $task : l'entité est chargée automatiquement depuis {id} (EntityValueResolver).
    // Introuvable => 404 automatique.
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Task $task): JsonResponse
    {
        return $this->json($task);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateTaskInput $input, #[CurrentUser] ?User $user): JsonResponse
    {
        // L'auteur est l'utilisateur connecté, jamais une valeur envoyée par le client.
        // null tant que les écritures ne sont pas protégées (B2-4b) et qu'on appelle sans token.
        $task = new Task($input->title, '' === $input->description ? null : $input->description, $input->status, $user);
        if (null !== $input->assigneeId) {
            $task->assignTo($this->users->find($input->assigneeId));
        }

        // persist = "suivre cet objet" (≈ DbSet.Add), flush = exécuter le SQL (≈ SaveChanges)
        $this->em->persist($task);
        $this->em->flush();

        return $this->json($task, Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('api_tasks_show', ['id' => $task->getId()]),
        ]);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(Task $task, #[MapRequestPayload] UpdateTaskInput $input, Request $request): JsonResponse
    {
        if (null !== $input->title) {
            $task->rename($input->title);
        }
        if (null !== $input->description) {
            $task->describe('' === $input->description ? null : $input->description);
        }
        if (null !== $input->status) {
            $task->changeStatus($input->status);
        }
        // Dans le DTO, « assigneeId absent » et « assigneeId: null » sont identiques. On regarde donc
        // les clés du JSON brut : présente (même à null) = on modifie, null = on désassigne.
        if (array_key_exists('assigneeId', $request->toArray())) {
            $task->assignTo(null === $input->assigneeId ? null : $this->users->find($input->assigneeId));
        }

        // Pas de persist() : l'entité est déjà suivie par l'EntityManager (Unit of Work),
        // flush() détecte les changements tout seul (≈ change tracking d'EF Core).
        $this->em->flush();

        return $this->json($task);
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Task $task): Response
    {
        $this->em->remove($task);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
