<?php

declare(strict_types=1);

namespace App\Tests\Atelier;

use PHPUnit\Framework\Attributes\TestDox;

/**
 * A1 — Une route, un contrôleur, un service injecté par autowiring.
 * GET /api/tasks/stats → {"total": n, "todo": n, "in_progress": n, "done": n}
 */
final class A1StatsTest extends AtelierTestCase
{
    #[TestDox('A1 : GET /api/tasks/stats répond 200 avec total, todo, in_progress, done (dans cet ordre)')]
    public function testShape(): void
    {
        $this->client->request('GET', '/api/tasks/stats');

        self::assertResponseIsSuccessful();
        self::assertSame(['total', 'todo', 'in_progress', 'done'], array_keys($this->json()));
        foreach ($this->json() as $key => $value) {
            self::assertIsInt($value, "« $key » doit être un entier");
        }
    }

    #[TestDox('A1 : les compteurs suivent les tâches créées')]
    public function testCounts(): void
    {
        $this->client->request('GET', '/api/tasks/stats');
        $before = $this->json();

        $this->createTask('Une', 'todo');
        $this->createTask('Deux', 'todo');
        $this->createTask('Trois', 'in_progress');
        $this->createTask('Quatre', 'done');

        $this->client->request('GET', '/api/tasks/stats');
        $after = $this->json();

        self::assertSame(
            ['total' => 4, 'todo' => 2, 'in_progress' => 1, 'done' => 1],
            array_map(static fn (string $k): int => $after[$k] - $before[$k], ['total' => 'total', 'todo' => 'todo', 'in_progress' => 'in_progress', 'done' => 'done']),
        );
    }

    #[TestDox('A1 : /api/tasks/{id} n\'a pas été cassé (GET /api/tasks/abc → 404)')]
    public function testIdRouteStillWorks(): void
    {
        $id = $this->createTask('Toujours là');

        $this->client->request('GET', "/api/tasks/$id");
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/tasks/abc');
        self::assertResponseStatusCodeSame(404);
    }
}
