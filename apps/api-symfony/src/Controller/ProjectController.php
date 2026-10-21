<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateProjectInput;
use App\Dto\UpdateProjectInput;
use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API Projets (contrat, partie 2A).
 */
#[Route('/api/projects', name: 'api_projects_', format: 'json')]
final class ProjectController extends AbstractController
{
    private const array CONTEXT = ['groups' => ['project:read']];

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->projects->findAllOrderedByName(), context: self::CONTEXT);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Project $project): JsonResponse
    {
        return $this->json($project, context: self::CONTEXT);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateProjectInput $input): JsonResponse
    {
        $project = new Project($input->name, '' === $input->description ? null : $input->description);
        $this->em->persist($project);
        $this->em->flush();

        return $this->json($project, Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('api_projects_show', ['id' => $project->getId()]),
        ], self::CONTEXT);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(Project $project, #[MapRequestPayload] UpdateProjectInput $input, Request $request): JsonResponse
    {
        if (null !== $input->name) {
            $project->rename($input->name);
        }
        // Le DTO ne distingue pas "absent" de "null" : on regarde les clés du JSON brut
        if (array_key_exists('description', $request->toArray())) {
            $project->describe('' === $input->description ? null : $input->description);
        }

        $this->em->flush();

        return $this->json($project, context: self::CONTEXT);
    }

    #[Route('/{id}/tasks', name: 'tasks', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function tasks(Project $project, TaskRepository $tasks): JsonResponse
    {
        return $this->json($tasks->findByProject($project), context: TaskController::CONTEXT);
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Project $project): Response
    {
        // On VÉRIFIE au lieu de cascader : un projet qui contient des tâches ne se supprime pas (409).
        // (Sans ce contrôle, la clé étrangère task.project_id ferait échouer le DELETE → 500.)
        $taskCount = $project->getTaskCount(); // EXTRA_LAZY : SELECT COUNT(*), les tâches ne sont pas chargées
        if ($taskCount > 0) {
            throw new ConflictHttpException(\sprintf('Project %d still has %d task(s)', $project->getId(), $taskCount));
        }

        $this->em->remove($project);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
