<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Paramètres de query string de GET /api/tasks (contrat, partie 2C).
 * Rempli par #[MapQueryString] (≈ [FromQuery] TaskFilter filter en ASP.NET Core) puis validé.
 *
 * Tous les champs ont une valeur par défaut : sans paramètre, le comportement est celui de la partie 1.
 */
final readonly class TaskFilter
{
    public function __construct(
        // "nimporte" ne peut pas devenir un TaskStatus : erreur de dénormalisation → 422 (voir le contrôleur)
        public ?TaskStatus $status = null,

        #[Assert\Positive]
        public ?int $projectId = null,

        public ?string $search = null,

        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 50,
    ) {
    }

    /** Index de la première ligne de la page (OFFSET SQL). */
    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
