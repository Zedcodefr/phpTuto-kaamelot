<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Task;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * B2-5 — « Peut-il supprimer CETTE tâche ? » (≈ IAuthorizationHandler avec ressource en ASP.NET Core).
 * Autoconfiguré : il suffit d'étendre Voter. Utilisé par #[IsGranted('TASK_DELETE', 'task')].
 *
 * @extends Voter<string, Task>
 */
final class TaskVoter extends Voter
{
    public const string DELETE = 'TASK_DELETE';

    public function __construct(private readonly AccessDecisionManagerInterface $decisions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::DELETE === $attribute && $subject instanceof Task;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // L'admin peut tout supprimer (via la hiérarchie de rôles, pas un in_array sur getRoles()).
        if ($this->decisions->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        // Tâche sans auteur (créée avant l'authentification) : admin seulement.
        $author = $subject->getAuthor();

        return null !== $author && $author->getId() === $user->getId();
    }
}
