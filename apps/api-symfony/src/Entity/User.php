<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * Utilisateur de l'API (≈ IdentityUser en ASP.NET Core Identity).
 * Le mot de passe stocké est toujours un hash, jamais exposé en JSON :
 * #[Ignore] garantit que même un User sérialisé tel quel reste {id, email, displayName} ;
 * les réponses de l'API passent en plus par UserOutput.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 50)]
    private string $displayName;

    #[ORM\Column]
    private string $password;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    public function __construct(string $email, string $displayName, string $password)
    {
        $this->email = $email;
        $this->displayName = $displayName;
        $this->password = $password;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    #[Ignore]
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    #[Ignore]
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    #[Ignore]
    public function getPassword(): string
    {
        return $this->password;
    }

    public function eraseCredentials(): void
    {
        // Rien à effacer : on ne garde aucun mot de passe en clair sur l'objet.
    }

    public function changePassword(string $hashedPassword): static
    {
        $this->password = $hashedPassword;

        return $this;
    }
}
