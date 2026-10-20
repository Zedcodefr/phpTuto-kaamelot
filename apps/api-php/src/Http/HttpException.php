<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Exception portant un code HTTP. Le Lot C la LANCE (404, 400...),
 * le Lot A l'ATTRAPE dans public/index.php et la transforme en réponse JSON.
 *
 * Propriétaire : 🅰️ Lot A. Le constructeur est FOURNI.
 */
class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $detail = '',
    ) {
        parent::__construct($detail);
    }

    /**
     * 🅰️ LOT A — Convertit l'exception en JsonResponse (corps = toArray(), code = $this->status).
     */
    public function toResponse(): JsonResponse
    {
        throw new \LogicException('TODO Lot A : HttpException::toResponse()');
    }

    /**
     * 🅰️ LOT A — Corps "problem details" (voir docs/api-contract.md) :
     * ['title' => 'Not Found', 'status' => 404, 'detail' => <message de l'exception>]
     * Titres : 400 Bad Request, 404 Not Found, 405 Method Not Allowed, 422 Validation Failed, autre : Error.
     *
     * @return array<string, mixed>
     */
    protected function toArray(): array
    {
        throw new \LogicException('TODO Lot A : HttpException::toArray()');
    }
}
