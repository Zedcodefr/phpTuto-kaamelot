<?php

declare(strict_types=1);

namespace App\Tests\Atelier;

use PHPUnit\Framework\Attributes\TestDox;

/**
 * A2 — Une contrainte de validation maison : #[NotShouting].
 * Un titre d'au moins 5 lettres sans aucune minuscule est refusé (422), en POST comme en PATCH.
 */
final class A2NotShoutingTest extends AtelierTestCase
{
    #[TestDox('A2 : POST avec un titre tout en majuscules → 422 sur title')]
    public function testCreateRejectsShouting(): void
    {
        $this->client->jsonRequest('POST', '/api/tasks', ['title' => 'RÉPONDRE AU CLIENT']);

        self::assertResponseStatusCodeSame(422);
        self::assertContains('title', array_column($this->json()['violations'] ?? [], 'propertyPath'));
    }

    #[TestDox('A2 : PATCH avec un titre tout en majuscules → 422 sur title')]
    public function testUpdateRejectsShouting(): void
    {
        $id = $this->createTask('Calme');

        $this->client->jsonRequest('PATCH', "/api/tasks/$id", ['title' => 'PLUS CALME DU TOUT']);

        self::assertResponseStatusCodeSame(422);
        self::assertContains('title', array_column($this->json()['violations'] ?? [], 'propertyPath'));
    }

    #[TestDox('A2 : les titres normaux passent (minuscules, sigle court, majuscules partielles)')]
    public function testAcceptsNormalTitles(): void
    {
        foreach (['Réunion IMPORTANTE', 'API', 'Lire la doc PHP 8.4', '2026 : bilan'] as $title) {
            $this->client->jsonRequest('POST', '/api/tasks', ['title' => $title]);
            self::assertResponseStatusCodeSame(201, "« $title » doit être accepté");
        }
    }

    #[TestDox('A2 : le message d\'erreur est en français et parle de majuscules')]
    public function testMessage(): void
    {
        $this->client->jsonRequest('POST', '/api/tasks', ['title' => 'URGENT URGENT']);

        self::assertResponseStatusCodeSame(422);
        $messages = implode(' ', array_column($this->json()['violations'] ?? [], 'title'));
        self::assertStringContainsStringIgnoringCase('majuscule', $messages);
    }
}
