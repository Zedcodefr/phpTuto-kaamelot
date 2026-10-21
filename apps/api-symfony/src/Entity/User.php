<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * Utilisateur de l'API (≈ IdentityUser). Forme JSON du contrat : {id, email, displayName} UNIQUEMENT.
 * Décision d'équipe : groupes de sérialisation. id/email/displayName sont dans user:read (User seul) et dans
 * task:read / comment:read (User imbriqué dans une tâche ou un commentaire : auteur, assigné).
 * Tout le reste (mot de passe, rôles, identifiant Security) est en plus marqué #[Ignore], par sécurité.
 *
 * Table "app_user" : "user" est un mot réservé de PostgreSQL.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const array READ_GROUPS = ['user:read', 'task:read', 'comment:read'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 50)]
    private string $displayName;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles;

    /** Hash (bcrypt / argon2), jamais le mot de passe en clair. */
    #[ORM\Column]
    private string $password = '';

    /**
     * @param list<string> $roles
     */
    public function __construct(string $email, string $displayName, array $roles = [])
    {
        $this->email = self::normalizeEmail($email);
        $this->displayName = $displayName;
        $this->roles = $roles;
    }

    /** Les emails sont comparés sans tenir compte de la casse ni des espaces autour. */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    #[Groups(User::READ_GROUPS)]
    public function getId(): ?int
    {
        return $this->id;
    }

    #[Groups(User::READ_GROUPS)]
    public function getEmail(): string
    {
        return $this->email;
    }

    #[Groups(User::READ_GROUPS)]
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * Le hash est calculé par UserPasswordHasherInterface, qui a besoin de l'utilisateur (algorithme, sel) :
     * on construit l'utilisateur, puis on lui donne son hash.
     */
    public function changePassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    #[Ignore]
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return list<string> ROLE_USER est toujours présent
     */
    #[Ignore]
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    #[Ignore]
    public function isAdmin(): bool
    {
        return \in_array('ROLE_ADMIN', $this->roles, true);
    }

    /** Identifiant utilisé par Security (user provider, UserBadge) : l'email. */
    #[Ignore]
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // Rien à effacer : on ne garde jamais le mot de passe en clair dans l'entité.
    }
}
