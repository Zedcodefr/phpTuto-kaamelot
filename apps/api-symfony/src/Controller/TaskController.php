<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateTaskInput;
use App\Dto\TaskFilter;
use App\Dto\UpdateTaskInput;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Security\TaskVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Équivalent d'un [ApiController] ASP.NET Core avec [Route("api/tasks")].
 *
 * - format: 'json' => les erreurs (404, 422...) sont rendues en JSON "problem details"
 * - les dépendances sont injectées par autowiring, dans le constructeur OU dans l'action
 */
#[Route('/api/tasks', name: 'api_tasks_', format: 'json')]
final class TaskController extends AbstractController
{
    /** Décision d'équipe B1-3 : sérialisation par groupes. Toute réponse Task passe ce contexte. */
    public const array CONTEXT = ['groups' => ['task:read']];

    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly EntityManagerInterface $em,
        private readonly ProjectRepository $projects,
        private readonly UserRepository $users,
    ) {
    }

    // B3-1 : filtres + pagination. ⚠️ MapQueryString renvoie 404 par défaut si la validation échoue :
    // le contrat demande 422 (comme MapRequestPayload), d'où validationFailedStatusCode.
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        TaskFilter $filter = new TaskFilter(),
    ): JsonResponse {
        $page = $this->tasks->search($filter);

        return $this->json(iterator_to_array($page, false), headers: ['X-Total-Count' => (string) count($page)], context: self::CONTEXT);
    }

    // Task $task : l'entité est chargée automatiquement depuis {id} (EntityValueResolver).
    // Introuvable => 404 automatique.
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Task $task): JsonResponse
    {
        return $this->json($task, context: self::CONTEXT);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateTaskInput $input, #[CurrentUser] ?User $user): JsonResponse
    {
        // B2-4a : author = l'utilisateur connecté (null tant que B2-4b n'impose pas le token)
        $description = '' === $input->description ? null : $input->description;
        $task = new Task($input->title, $description, $input->status, $this->findProject($input->projectId), $user);
        $task->assignTo($this->findUser($input->assigneeId));

        // persist = "suivre cet objet" (≈ DbSet.Add), flush = exécuter le SQL (≈ SaveChanges)
        $this->em->persist($task);
        $this->em->flush();

        return $this->json($task, Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('api_tasks_show', ['id' => $task->getId()]),
        ], self::CONTEXT);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(Task $task, #[MapRequestPayload] UpdateTaskInput $input, Request $request): JsonResponse
    {
        // Le DTO ne distingue pas "absent" de "null explicite" : on regarde les clés du JSON brut
        // (docs/02-symfony/02-particularites.md §10, solution a).
        $sent = $request->toArray();

        if (null !== $input->title) {
            $task->rename($input->title);
        }
        if (array_key_exists('description', $sent)) {
            $task->describe('' === $input->description ? null : $input->description);
        }
        if (null !== $input->status) {
            $task->changeStatus($input->status);
        }
        if (array_key_exists('projectId', $sent)) {
            $task->moveToProject($this->findProject($input->projectId)); // null → détacher
        }
        if (array_key_exists('assigneeId', $sent)) {
            $task->assignTo($this->findUser($input->assigneeId)); // null → désassigner
        }

        // Pas de persist() : l'entité est déjà suivie par l'EntityManager (Unit of Work),
        // flush() détecte les changements tout seul (≈ change tracking d'EF Core).
        $this->em->flush();

        return $this->json($task, context: self::CONTEXT);
    }

    // B2-5 : seul l'auteur ou un ROLE_ADMIN (TaskVoter) → sinon 403 (401 si personne n'est connecté)
    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted(TaskVoter::DELETE, 'task')]
    public function delete(Task $task): Response
    {
        $this->em->remove($task);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** assigneeId déjà validé par #[ExistingUser] ; find() est servi par l'Identity Map. */
    private function findUser(?int $userId): ?User
    {
        return null === $userId ? null : $this->users->find($userId);
    }

    /** projectId déjà validé par #[ExistingProject] ; find() est servi par l'Identity Map. */
    private function findProject(?int $projectId): ?Project
    {
        return null === $projectId ? null : $this->projects->find($projectId);
    }
}
