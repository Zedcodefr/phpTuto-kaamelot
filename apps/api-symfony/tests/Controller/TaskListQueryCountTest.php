<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Definition of Done : le nombre de requêtes SQL de GET /api/tasks ne dépend pas du nombre de tâches.
 * Sans jointure sur author/assignee, chaque utilisateur distinct déclencherait un SELECT de plus (N+1).
 */
final class TaskListQueryCountTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testListQueryCountDoesNotGrowWithTheNumberOfTasks(): void
    {
        $this->createTasksWithDistinctUsers(1);
        $withOne = $this->queryCountOfList();

        $this->createTasksWithDistinctUsers(5);
        $withSix = $this->queryCountOfList();

        self::assertSame($withOne, $withSix, 'Le nombre de requêtes augmente avec le nombre de tâches (N+1).');
    }

    private function createTasksWithDistinctUsers(int $count): void
    {
        static $n = 0;
        for ($i = 0; $i < $count; ++$i, ++$n) {
            $author = new User("author$n@test.local", "Author $n", '');
            $assignee = new User("assignee$n@test.local", "Assignee $n", '');
            $task = new Task("Tâche $n", author: $author);
            $task->assignTo($assignee);
            $this->em->persist($author);
            $this->em->persist($assignee);
            $this->em->persist($task);
        }
        $this->em->flush();
        $this->em->clear(); // la liste doit relire la base, pas l'Identity Map

        // Les INSERT ci-dessus sont journalisés par le kernel déjà démarré : une requête « à blanc »
        // le fait redémarrer, pour que la mesure suivante ne compte que les requêtes de la liste.
        $this->client->request('GET', '/api/health');
    }

    private function queryCountOfList(): int
    {
        // enableProfiler() ne vaut que pour la requête suivante : à rappeler avant chaque requête.
        $this->client->enableProfiler();
        $this->client->request('GET', '/api/tasks');
        self::assertResponseIsSuccessful();

        return $this->client->getProfile()->getCollector('db')->getQueryCount();
    }
}
