<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ApiTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Jeton d'accès opaque (pas de JWT) : "Authorization: Bearer <token>".
 *
 * On ne stocke que l'EMPREINTE SHA-256 du jeton (64 caractères hexadécimaux), comme on ne stocke qu'un hash
 * de mot de passe : une fuite de la base ne donne pas de jetons utilisables.
 * SHA-256 suffit ici (pas besoin de bcrypt) : le jeton est aléatoire sur 256 bits, impossible à deviner.
 */
#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
#[ORM\Table(name: 'api_token')]
class ApiToken
{
    public const string TTL = '+24 hours';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    private function __construct(User $user, string $tokenHash, \DateTimeImmutable $expiresAt)
    {
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
    }

    /**
     * Crée un jeton pour $user. Renvoie l'entité à persister ET le jeton en clair, à donner au client
     * une seule fois (il n'est jamais stocké).
     *
     * @return array{0: self, 1: string}
     */
    public static function issueFor(User $user, \DateTimeImmutable $now = new \DateTimeImmutable()): array
    {
        $plainToken = bin2hex(random_bytes(32));

        return [new self($user, self::hash($plainToken), $now->modify(self::TTL)), $plainToken];
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return $this->expiresAt <= $now;
    }
}
