<?php

declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly mixed $data = null,
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    public function send(): void
    {
        // Attention : les en-têtes doivent être envoyés AVANT tout affichage (echo).
        http_response_code($this->status);

        if ($this->status === 204) {
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        echo json_encode($this->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
