<?php

declare(strict_types=1);

namespace App\Tests\Atelier;

use PHPUnit\Framework\Attributes\TestDox;

/**
 * A4 — Une règle métier dans l'entité, une exception métier traduite en code HTTP.
 * POST /api/tasks/{id}/complete → 200 + la tâche passée à "done" ; déjà terminée → 409.
 */
final class A4CompleteTest extends AtelierTestCase
{
    #[TestDox('A4 : POST /api/tasks/{id}/complete → 200, status = done')]
    public function testComplete(): void
    {
        $id = $this->createTask('À finir', 'in_progress');

        $this->client->request('POST', "/api/tasks/$id/complete");

        self::assertResponseIsSuccessful();
        self::assertSame($id, $this->json()['id']);
        self::assertSame('done', $this->json()['status']);

        $this->client->request('GET', "/api/tasks/$id");
        self::assertSame('done', $this->json()['status'], 'Le changement doit être enregistré en base');
    }

    #[TestDox('A4 : terminer une tâche déjà terminée → 409 en JSON')]
    public function testAlreadyDone(): void
    {
        $id = $this->createTask('Déjà faite', 'done');

        $this->client->request('POST', "/api/tasks/$id/complete");

        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(409, $this->json()['status'], 'Le corps est le JSON d\'erreur habituel, avec status = 409');
    }

    #[TestDox('A4 : tâche inexistante → 404 ; GET sur /complete → 405')]
    public function testNotFoundAndMethod(): void
    {
        $this->client->request('POST', '/api/tasks/999999/complete');
        self::assertResponseStatusCodeSame(404);

        $id = $this->createTask('Méthode');
        $this->client->request('GET', "/api/tasks/$id/complete");
        self::assertResponseStatusCodeSame(405);
    }
}
