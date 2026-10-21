<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Encapsule les superglobales ($_SERVER, $_GET...) dans un objet immuable.
 * ≈ HttpRequest d'ASP.NET Core (en beaucoup plus simple).
 */
final readonly class Request
{
    /**
     * @param array<string, string> $query
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public string $body = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self(
            method: strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/',
            query: $_GET,
            // Le corps brut de la requête (le JSON) se lit dans le flux php://input
            body: (string) file_get_contents('php://input'),
        );
    }

    /**
     * Décode le corps JSON en tableau associatif.
     *
     * @return array<string, mixed>
     *
     * @throws HttpException 400 si le JSON est invalide ou n'est pas un objet
     */
    public function json(): array
    {
        try {
            // Sans `true` : un objet JSON devient un \stdClass, une liste JSON un array.
            // C'est le seul moyen de distinguer {} de [] (tous deux décodés en [] avec `true`).
            $decoded = json_decode($this->body, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new HttpException(400, 'Invalid JSON: ' . $e->getMessage());
        }

        if (!$decoded instanceof \stdClass) {
            throw new HttpException(400, 'JSON body must be an object');
        }

        // On sait maintenant que c'est un objet : on redécode en tableaux associatifs (à tous les niveaux).
        return json_decode($this->body, true, flags: JSON_THROW_ON_ERROR);
    }
}
