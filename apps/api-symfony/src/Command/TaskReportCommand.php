<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\TaskStats;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'app:tasks:report')]
class TaskReportCommand{

    public function __construct(private readonly TaskStats $taskStats){}

      public function __invoke(SymfonyStyle $io, #[Option] string $format = 'table'): int
      {
        $stats = $this->taskStats->compute();

        if ($format === 'json') {
            $io->writeln(json_encode($stats, JSON_THROW_ON_ERROR));
            return Command::SUCCESS;
        }

        if ($format === 'table') {
            $rows = [];
            foreach ($stats as $statut => $nombre) {
                $rows[] = [$statut, $nombre];
            }
            $io->table(['Statut', 'Nombre'], $rows);
            return Command::SUCCESS;
        }

        $io->error(sprintf('Format inconnu : "%s" (attendu : table ou json)', $format));
            return Command::INVALID;
    }
}
