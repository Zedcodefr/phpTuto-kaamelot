<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\LoginInput;
use App\Dto\RegisterInput;
use App\Entity\ApiToken;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', name: 'api_auth_', format: 'json')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/auth/register', name: 'register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterInput $input): JsonResponse
    {
        $user = new User($input->email, $input->displayName);
        $user->changePassword($this->hasher->hashPassword($user, $input->password));

        $this->em->persist($user);
        $this->em->flush();

        // Pas d'en-tête Location : il n'existe pas de route GET /api/users/{id} dans le contrat.
        return $this->json($user, Response::HTTP_CREATED, context: ['groups' => ['user:read']]);
    }

    #[Route('/auth/login', name: 'login', methods: ['POST'])]
    public function login(#[MapRequestPayload] LoginInput $input): JsonResponse
    {
        $user = $this->users->findOneByEmail($input->email);

        // Même réponse que l'email existe ou non : on ne révèle pas quels comptes existent.
        if (null === $user || !$this->hasher->isPasswordValid($user, $input->password)) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid credentials.');
        }

        [$token, $plainToken] = ApiToken::issueFor($user);
        $this->em->persist($token);
        $this->em->flush();

        return $this->json(['token' => $plainToken, 'user' => $user], context: ['groups' => ['user:read']]);
    }

    /**
     * IsGranted sans token → l'authenticator access_token (point d'entrée du firewall) répond 401.
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($user, context: ['groups' => ['user:read']]);
    }
}
