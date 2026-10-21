<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Token invalide ou expiré : par défaut, l'authenticator access_token répond 401 avec un corps VIDE.
 * On renvoie plutôt le format d'erreur du contrat (problem details), en gardant l'en-tête WWW-Authenticate.
 */
final class ApiTokenFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(
            ['title' => 'Unauthorized', 'status' => Response::HTTP_UNAUTHORIZED, 'detail' => 'Invalid or expired token.'],
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => 'Bearer error="invalid_token"', 'Content-Type' => 'application/problem+json'],
        );
    }
}
