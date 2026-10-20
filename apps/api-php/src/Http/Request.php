<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Requête HTTP entrante, immuable. ≈ HttpRequest d'ASP.NET Core (en très simplifié).
 *
 * Propriétaires :
 *   - fromGlobals() : 🅰️ Lot A (HTTP & routage)
 *   - json()        : 🅲 Lot C (métier & validation)
 * Le constructeur est FOURNI et ne doit pas changer : c'est le contrat entre les lots.
 */
final readonly class Request
{
    /**
     * @param string                $method HTTP en majuscules : GET, POST, PATCH, DELETE
     * @param string                $path   chemin sans query string ni "/" final : "/api/tasks/3"
     * @param array<string, string> $query  paramètres de la query string (?status=todo)
     * @param string                $body   corps brut de la requête (le JSON envoyé)
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public string $body = '',
    ) {
    }

    /**
     * 🅰️ LOT A — Construit la Request à partir des superglobales PHP.
     *
     * - méthode : $_SERVER['REQUEST_METHOD'] (en majuscules)
     * - chemin  : $_SERVER['REQUEST_URI'] sans la query string, sans "/" final ("/" reste "/")
     * - query   : $_GET
     * - corps   : à lire dans le flux php://input
     */
    public static function fromGlobals(): self
    {
        throw new \LogicException('TODO Lot A : Request::fromGlobals()');
    }

    /**
     * 🅲 LOT C — Décode le corps JSON en tableau associatif.
     *
     * - JSON invalide (ou corps vide)               → HttpException 400
     * - JSON valide mais pas un objet (ex. [1,2], [], 3) → HttpException 400
     * - "{}"                                          → []
     *
     * @return array<string, mixed>
     *
     * @throws HttpException
     */
    public function json(): array
    {
        throw new \LogicException('TODO Lot C : Request::json()');
    }
}
