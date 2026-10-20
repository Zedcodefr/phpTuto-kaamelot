<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateTaskInput;
use App\Dto\UpdateTaskInput;
use App\Entity\Task;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

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
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->tasks->findAllOrdered());
    }

    // Task $task : l'entité est chargée automatiquement depuis {id} (EntityValueResolver).
    // Introuvable => 404 automatique.
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Task $task): JsonResponse
    {
        return $this->json($task);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateTaskInput $input): JsonResponse
    {
        $task = new Task($input->title, '' === $input->description ? null : $input->description, $input->status);

        // persist = "suivre cet objet" (≈ DbSet.Add), flush = exécuter le SQL (≈ SaveChanges)
        $this->em->persist($task);
        $this->em->flush();

        return $this->json($task, Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('api_tasks_show', ['id' => $task->getId()]),
        ]);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(Task $task, #[MapRequestPayload] UpdateTaskInput $input): JsonResponse
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
