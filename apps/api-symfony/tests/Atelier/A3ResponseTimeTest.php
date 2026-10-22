<?php

declare(strict_types=1);

namespace App\Tests\Atelier;

use PHPUnit\Framework\Attributes\TestDox;

/**
 * A3 — Événements du kernel : un en-tête X-Response-Time sur toutes les réponses de /api.
 * Valeur : la durée de traitement en millisecondes, nombre entier (ex. "X-Response-Time: 12").
 */
final class A3ResponseTimeTest extends AtelierTestCase
{
    #[TestDox('A3 : X-Response-Time présent sur une réponse 200, en millisecondes entières')]
    public function testHeaderOnSuccess(): void
    {
        $this->client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHasHeader('X-Response-Time');
        self::assertMatchesRegularExpression('/^\d+$/', (string) $this->client->getResponse()->headers->get('X-Response-Time'));
    }

    #[TestDox('A3 : X-Response-Time présent aussi sur les erreurs (404, 422)')]
    public function testHeaderOnErrors(): void
    {
        $this->client->request('GET', '/api/tasks/999999');
        self::assertResponseStatusCodeSame(404);
        self::assertResponseHasHeader('X-Response-Time');

        $this->client->jsonRequest('POST', '/api/tasks', ['title' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHasHeader('X-Response-Time');
    }

    #[TestDox('A3 : pas d\'en-tête en dehors de /api')]
    public function testNotOutsideApi(): void
    {
        $this->client->request('GET', '/pas-une-api');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseNotHasHeader('X-Response-Time');
    }
}
