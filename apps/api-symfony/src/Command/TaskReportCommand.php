<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\TaskStats;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A5 — Commande « invokable » (Symfony 7.3+) : pas d'héritage, les options sont des paramètres de __invoke().
 * Le même service TaskStats que le contrôleur de A1 : HTTP et CLI ne sont que deux portes d'entrée.
 */
#[AsCommand(name: 'app:tasks:report', description: 'Affiche le nombre de tâches par statut')]
final readonly class TaskReportCommand
{
    public function __construct(private TaskStats $stats)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'table ou json')] string $format = 'table',
    ): int {
        $stats = $this->stats->compute();

        if ('json' === $format) {
            $io->writeln(json_encode($stats, JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }
        if ('table' !== $format) {
            $io->error(\sprintf('Format inconnu « %s » : utilisez table ou json.', $format));

            return Command::INVALID;
        }

        $io->table(['Statut', 'Nombre'], array_map(null, array_keys($stats), array_values($stats)));

        return Command::SUCCESS;
    }
}
