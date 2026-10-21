<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiToken>
 */
class ApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }

    /**
     * Le jeton (en clair, tel que reçu dans l'en-tête) s'il existe et n'est pas expiré, avec son utilisateur
     * chargé dans la même requête (fetch join : ↩︎ kata K06). Comparaison de dates en paramètre : ↩︎ kata K03.
     */
    public function findValid(string $plainToken, \DateTimeImmutable $now = new \DateTimeImmutable()): ?ApiToken
    {
        return $this->createQueryBuilder('t')
            ->join('t.user', 'u')->addSelect('u')
            ->andWhere('t.tokenHash = :hash')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('hash', ApiToken::hash($plainToken))
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
