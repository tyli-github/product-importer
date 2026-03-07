<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ImportJob;
use App\Service\CsvReaderService;
use App\Service\ImportJobService;
use App\Service\ProductImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'import:products',
    description: 'Import products from a CSV file',
)]
class ImportProductsCommand extends Command
{
    public function __construct(
        private readonly ProductImportService $importService,
        private readonly CsvReaderService $csvReader,
        private readonly ImportJobService $jobService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to CSV file')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate only, do not persist')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');
        $dryRun = $input->getOption('dry-run');

        if (!file_exists($filePath)) {
            $io->error(sprintf('File not found: %s', $filePath));

            return Command::FAILURE;
        }

        $job = $this->jobService->createJob($filePath, ImportJob::SOURCE_TYPE_CSV);

        $io->info(sprintf('Starting import (Job ID: %d)', $job->getId()));

        $progress = new ProgressBar($output);
        $progress->start();

        $result = $this->importService->import($filePath, $job, $dryRun, fn() => $progress->advance());

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
}
