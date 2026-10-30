<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\LoginInput;
use App\Dto\RegisterInput;
use App\Dto\UserOutput;
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

#[Route('/api/auth', name: 'api_auth_', format: 'json')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly UserRepository $users,
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
        // UserOutput limite la réponse à id/email/displayName : jamais le hash du mot de passe.
        return $this->json(UserOutput::fromEntity($user), Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(#[MapRequestPayload] LoginInput $input): JsonResponse
    {
        $user = $this->users->findOneBy(['email' => $input->email]);

        // Un seul message pour « email inconnu » et « mauvais mot de passe » :
        // on ne doit pas permettre de deviner quels emails ont un compte.
        if (null === $user || !$this->hasher->isPasswordValid($user, $input->password)) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid credentials.');
        }

        $token = new ApiToken($user);
        $this->em->persist($token);
        $this->em->flush();

        return $this->json(['token' => $token->getToken(), 'user' => UserOutput::fromEntity($user)]);
    }
}
