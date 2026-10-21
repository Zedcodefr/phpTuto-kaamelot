<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users', name: 'api_users_', format: 'json')]
final class UserController extends AbstractController
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /** Public (GET), pour choisir un assigné. */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->users->findAllOrderedByDisplayName(), context: ['groups' => ['user:read']]);
    }
}
