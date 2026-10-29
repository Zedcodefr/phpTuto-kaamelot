<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\TaskStats;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tasks:report'
)]
final class TaskReportCommand
{
    public function __construct(private readonly TaskStats $stats)
    {
    }
    public function __invoke(SymfonyStyle $io, #[Option(description: 'table ou json')] string $format = 'table'): int
    {
        $report = $this->stats->compute();
        if ($format === 'table') {

            $io->table(
                ['Status', 'Count'],
                array_map(fn($status, $count) => [$status, $count], array_keys($report), $report)
            );
            return Command::SUCCESS;

        } elseif ($format === 'json') {

            $io->writeln(json_encode($report, JSON_PRETTY_PRINT));
            return Command::SUCCESS;

        } else {

            $io->error('Unsupported format: ' . $format);
            return Command::INVALID;
        }
    }
}
