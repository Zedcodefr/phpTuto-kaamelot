<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Réponse HTTP JSON.
 *
 * Propriétaire : 🅰️ Lot A. Le constructeur est FOURNI (contrat entre les lots) :
 * le Lot C crée des JsonResponse dans le contrôleur, le Lot A sait les envoyer.
 */
final class JsonResponse
{
    /**
     * @param mixed                 $data    sera passé à json_encode() (tableaux, scalaires, objets JsonSerializable...)
     * @param array<string, string> $headers en-têtes supplémentaires, ex. ['Location' => '/api/tasks/3']
     */
    public function __construct(
        public readonly mixed $data = null,
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    /**
     * 🅰️ LOT A — Envoie la réponse au client.
     *
     * - code HTTP = $this->status
     * - 204 : aucun corps, aucun Content-Type
     * - sinon : en-tête "Content-Type: application/json; charset=utf-8", en-têtes de $this->headers,
     *   puis le JSON de $this->data (caractères accentués et "/" NON échappés)
     */
    public function send(): void
    {
        throw new \LogicException('TODO Lot A : JsonResponse::send()');
    }
}
