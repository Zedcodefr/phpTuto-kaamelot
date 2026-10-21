<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\ApiTokenRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Branché sur l'authenticator natif "access_token" (security.yaml) : Symfony extrait le jeton de
 * "Authorization: Bearer ..." et nous demande à qui il appartient. ≈ un AuthenticationHandler maison en ASP.NET Core.
 */
final readonly class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private ApiTokenRepository $tokens)
    {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $token = $this->tokens->findValid($accessToken)
            ?? throw new BadCredentialsException('Invalid or expired token.');

        $user = $token->getUser();

        // Le 2e argument (loader) évite de recharger l'utilisateur via le user provider : il est déjà là.
        return new UserBadge($user->getUserIdentifier(), static fn () => $user);
    }
}
