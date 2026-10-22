<?php

declare(strict_types=1);

namespace App\Tests\Atelier;

use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A5 — Une commande console qui réutilise le service de A1.
 * make sf c="app:tasks:report"                 → un tableau statut / nombre
 * make sf c="app:tasks:report --format=json"   → le même JSON que GET /api/tasks/stats
 */
final class A5CommandTest extends KernelTestCase
{
    private function tester(): CommandTester
    {
        $application = new Application(self::bootKernel());
        try {
            return new CommandTester($application->find('app:tasks:report'));
        } catch (CommandNotFoundException) {
            self::fail('La commande app:tasks:report n\'existe pas encore (classe avec #[AsCommand] dans src/Command/)');
        }
    }

    #[TestDox('A5 : app:tasks:report affiche chaque statut et le total')]
    public function testTable(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        foreach (['todo', 'in_progress', 'done', 'total'] as $label) {
            self::assertStringContainsString($label, $tester->getDisplay());
        }
    }

    #[TestDox('A5 : --format=json sort le même JSON que GET /api/tasks/stats')]
    public function testJson(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--format' => 'json']));
        $stats = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['total', 'todo', 'in_progress', 'done'], array_keys($stats));
    }

    #[TestDox('A5 : un format inconnu est refusé (code de sortie 2 = Command::INVALID)')]
    public function testInvalidFormat(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['--format' => 'xml']));
        self::assertStringContainsString('xml', $tester->getDisplay());
    }
}
