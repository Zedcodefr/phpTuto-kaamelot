<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegisterInput;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth', name: 'api_auth_', format: 'json')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterInput $input): JsonResponse
    {
        // Le hash a besoin de l'objet User (il choisit l'algorithme d'après sa classe) :
        // on crée donc l'utilisateur avec un mot de passe vide, puis on le remplace par le hash.
        $user = new User($input->email, $input->displayName, '');
        $user->changePassword($this->hasher->hashPassword($user, $input->password));

        $this->em->persist($user);
        $this->em->flush();

        // Pas de Location : il n'existe pas de GET /api/users/{id} (voir le contrat).
        // On construit la réponse à la main pour ne jamais exposer le hash du mot de passe.
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
        ], Response::HTTP_CREATED);
    }
}
