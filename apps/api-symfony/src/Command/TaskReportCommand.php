<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\TaskStats;
use PHPUnit\Util\Json;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:tasks:report')]
final class TaskReportCommand
{

    public function __construct(private readonly TaskStats $taskStats)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Option] string $format = 'table') : int
    {
        if($format === 'table')
            {
                $cases = $this->taskStats->compute();
                $io->table(array_keys($cases), array_values($cases));
                return Command::SUCCESS;
            }

            if($format === 'json')
                {
                $io->write(json_encode($this->taskStats->compute()));
                return Command::SUCCESS;
                }

                if($format === 'xml')
              {
                $io->error('xml');
                    }
                    return command::INVALID;

    }
}
