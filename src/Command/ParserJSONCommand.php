<?php

namespace App\Command;

use App\Service\StatisticService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:parser-json')]
class ParserJSONCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'path to file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new StatisticService())->getByPath($input->getArgument('path'));
        return Command::SUCCESS;
    }
}
