<?php

declare(strict_types=1);

namespace App\Command;

use App\Interface\ImportReaderInterface;
use App\Service\ImportJobService;
use App\Service\ImportLogService;
use App\Service\ProductImportService;
use App\Service\ProductValidator;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

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
        private readonly ImportLogService $importLogService,
        private readonly ManagerRegistry $managerRegistry,
        private readonly ProductValidator $productValidator,
        private readonly LoggerInterface $logger
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

        $reader = $this->resolveReader($source);

        if ($reader === null) {
            $io->error(sprintf('No reader supports the source: %s', $source));

            return Command::FAILURE;
        }

        $job = $this->jobService->createJob($source, $reader->getSourceType());

        $io->info(sprintf('Starting import (Job ID: %d)', $job->getId()));

        $importService = new ProductImportService(
            $this->managerRegistry,
            $this->productValidator,
            $reader,
            $this->logger,
            $this->importLogService,
        );

        $progress = new ProgressBar($output);
        $progress->start();

        $result = $importService->import($source, $job, $dryRun, fn() => $progress->advance());

        $progress->finish();
        $output->writeln('');

        $this->jobService->updateJobResults($job, $result);

        $io->table(
            ['Metric', 'Count'],
            [
                ['Total Rows', $result->total()],
                ['Processed', $result->processed],
                ['Failed', $result->failed],
                ['Skipped', $result->skipped],
            ]
        );

        if ($dryRun) {
            $io->warning('Dry run mode - no data persisted');
        }

        if ($result->failed > 0) {
            $io->warning(sprintf('%d rows failed validation', $result->failed));

            return Command::FAILURE;
        }

        $io->success('Import completed successfully');

        return Command::SUCCESS;
    }

    private function resolveReader(string $source,): ?ImportReaderInterface
    {
        /** @var ImportReaderInterface $reader */
        foreach ($this->readers as $reader) {
            if ($reader->supports($source)) {
                return $reader;
            }
        }

        return null;
    }
}
