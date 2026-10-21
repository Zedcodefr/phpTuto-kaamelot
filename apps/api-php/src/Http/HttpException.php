<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Exception portant un code HTTP, convertie en réponse JSON "problem details"
 * par le front controller.
 */
class HttpException extends \RuntimeException
{
    private const TITLES = [
        400 => 'Bad Request',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Validation Failed',
    ];

    public function __construct(
        public readonly int $status,
        string $detail = '',
    ) {
        parent::__construct($detail);
    }

    public function toResponse(): JsonResponse
    {
        return new JsonResponse($this->toArray(), $this->status);
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(): array
    {
        return [
            'title' => self::TITLES[$this->status] ?? 'Error',
            'status' => $this->status,
            'detail' => $this->getMessage(),
        ];
    }
}
