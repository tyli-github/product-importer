<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ImportJobRepository;
use App\Repository\ImportLogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'import:status',
    description: 'Show status of import jobs',
)]
class ImportStatusCommand extends Command
{
    private const int JOB_LIMIT = 10;

    public function __construct(
        private readonly ImportJobRepository $jobRepository,
        private readonly ImportLogRepository $logRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('job-id', InputArgument::OPTIONAL, 'Show details for a specific import job')
            ->addOption('level', null, InputOption::VALUE_REQUIRED, 'Filter log entries by level (e.g. error)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $jobId = $input->getArgument('job-id');

        if ($jobId !== null) {
            return $this->showJobDetail($io, (int) $jobId, $input->getOption('level'));
        }

        return $this->listRecentJobs($io);
    }

    private function listRecentJobs(SymfonyStyle $io): int
    {
        $jobs = $this->jobRepository->findBy([], ['startedAt' => 'DESC'], self::JOB_LIMIT);

        if (empty($jobs)) {
            $io->info('No import jobs found.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($jobs as $job) {
            $duration = $job->getCompletedAt()
                ? $job->getCompletedAt()->getTimestamp() - $job->getStartedAt()->getTimestamp()
                : null;

            $rows[] = [
                $job->getId(),
                $job->getName(),
                $job->getStatus(),
                $job->getSourceType(),
                $job->getProcessedRows() ?? 0,
                $job->getFailedRows() ?? 0,
                $duration !== null ? "{$duration}s" : 'running',
            ];
        }

        $io->table(
            ['ID', 'Name', 'Status', 'Source', 'Processed', 'Failed', 'Duration'],
            $rows
        );

        return Command::SUCCESS;
    }

    private function showJobDetail(SymfonyStyle $io, int $jobId, ?string $level): int
    {
        $job = $this->jobRepository->find($jobId);

        if ($job === null) {
            $io->error("Import job #{$jobId} not found.");

            return Command::FAILURE;
        }

        $duration = $job->getCompletedAt()
            ? $job->getCompletedAt()->getTimestamp() - $job->getStartedAt()->getTimestamp()
            : null;

        $io->section("Import Job #{$job->getId()}");
        $io->definitionList(
            ['Name' => $job->getName()],
            ['Status' => $job->getStatus()],
            ['Source' => $job->getSourceType()],
            ['File' => $job->getFilePath() ?? 'N/A'],
            ['Processed' => $job->getProcessedRows() ?? 0],
            ['Failed' => $job->getFailedRows() ?? 0],
            ['Started' => $job->getStartedAt()->format('Y-m-d H:i:s')],
            ['Completed' => $job->getCompletedAt()?->format('Y-m-d H:i:s') ?? 'N/A'],
            ['Duration' => $duration !== null ? "{$duration}s" : 'running'],
        );

        $criteria = ['importJob' => $job];
        if ($level !== null) {
            $criteria['level'] = $level;
        }

        $logs = $this->logRepository->findBy($criteria, ['createdAt' => 'ASC']);

        if (empty($logs)) {
            $io->info('No log entries' . ($level ? " with level \"{$level}\"" : '') . '.');

            return Command::SUCCESS;
        }

        $io->section('Log Entries');
        $logRows = [];
        foreach ($logs as $log) {
            $logRows[] = [
                $log->getRowNumber() ?? '-',
                strtoupper((string) $log->getLevel()),
                $log->getMessage(),
            ];
        }

        $io->table(['Row', 'Level', 'Message'], $logRows);

        return Command::SUCCESS;
    }
}
