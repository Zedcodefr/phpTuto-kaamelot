<?php

declare(strict_types=1);

namespace App\Tests\Atelier;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests de l'atelier Symfony (make sf-atelier) : un client HTTP en mémoire et deux raccourcis.
 * Chaque test tourne dans une transaction annulée à la fin (DAMA\DoctrineTestBundle).
 */
abstract class AtelierTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /** Crée une tâche par l'API et renvoie son id. */
    protected function createTask(string $title, string $status = 'todo'): int
    {
        $this->client->jsonRequest('POST', '/api/tasks', ['title' => $title, 'status' => $status]);
        self::assertResponseStatusCodeSame(201, "La création de « $title » doit fonctionner (POST /api/tasks)");

        return $this->json()['id'];
    }

    /** @return array<mixed> le corps JSON de la dernière réponse */
    protected function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
