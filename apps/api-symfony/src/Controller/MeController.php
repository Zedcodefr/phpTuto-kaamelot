<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\UserOutput;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api', name: 'api_', format: 'json')]
final class MeController extends AbstractController
{
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        // Pas de token : l'authenticator laisse passer (route publique côté firewall),
        // c'est ici qu'on refuse, avec le même corps d'erreur que le reste de l'API.
        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
        }

        return $this->json(UserOutput::fromEntity($user));
    }
}
