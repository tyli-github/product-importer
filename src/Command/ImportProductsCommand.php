<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\ImportProductsMessage;
use App\Service\ImportJobService;
use App\Service\ImportSourceDetector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'import:products',
    description: 'Import products from a CSV, JSON file, or HTTP URL',
)]
class ImportProductsCommand extends Command
{
    public function __construct(
        #[AutowireIterator('import.reader')]
        private readonly iterable $readers,
        private readonly ImportJobService $jobService,
        private readonly ImportSourceDetector $sourceDetector,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to file or HTTP URL')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate only, do not persist')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $source = $input->getArgument('file');
        $dryRun = $input->getOption('dry-run');

        $reader = $this->sourceDetector->detect($source, $this->readers);
        if ($reader === null) {
            $io->error(sprintf('No reader supports the source: %s', $source));

            return Command::FAILURE;
        }

        $job = $this->jobService->createJob($source, $reader->getSourceType());

        $this->messageBus->dispatch(new ImportProductsMessage(
            jobId: $job->getId(),
            source: $source,
            dryRun: $dryRun,
        ));

        $io->success(sprintf('Import queued (Job ID: %d)', $job->getId()));
        $io->info('Run: php bin/console messenger:consume async');

        return Command::SUCCESS;
    }
}
