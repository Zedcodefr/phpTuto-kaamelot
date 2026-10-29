<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\ApiTokenRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Appelé par l'authenticator `access_token` avec la valeur extraite de « Authorization: Bearer <valeur> ».
 * Il ne charge pas l'utilisateur lui-même : il renvoie un UserBadge, et le user provider fait le reste.
 */
final readonly class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private ApiTokenRepository $tokens)
    {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $apiToken = $this->tokens->findOneByToken($accessToken);

        // Même exception pour « inconnu » et « expiré » : le client n'a pas à faire la différence.
        if (null === $apiToken || $apiToken->isExpired()) {
            throw new BadCredentialsException('Invalid or expired token.');
        }

        return new UserBadge($apiToken->getUser()->getUserIdentifier());
    }
}
