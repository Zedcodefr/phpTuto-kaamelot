<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateProjectInput;
use App\Dto\UpdateProjectInput;
use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * CRUD des projets, calqué sur TaskController (route "api/projects").
 */
#[Route('/api/projects', name: 'api_projects_', format: 'json')]
final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->projects->findBy([], ['name' => 'ASC']));
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Project $project): JsonResponse
    {
        return $this->json($project);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateProjectInput $input): JsonResponse
    {
        $project = new Project($input->name, $input->description ?: null);

        $this->em->persist($project);
        $this->em->flush();

        return $this->json($project, Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('api_projects_show', ['id' => $project->getId()]),
        ]);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(Project $project, #[MapRequestPayload] UpdateProjectInput $input): JsonResponse
    {
        if (null !== $input->name) {
            $project->rename($input->name);
        }
        if (null !== $input->description) {
            $project->describe($input->description ?: null);
        }

        $this->em->flush();

        return $this->json($project);
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Project $project): Response
    {
        $this->em->remove($project);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
