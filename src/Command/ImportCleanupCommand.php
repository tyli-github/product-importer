<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ImportJob;
use App\Repository\ImportJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'import:cleanup',
    description: 'Delete old import jobs and their log entries',
)]
class ImportCleanupCommand extends Command
{
    private const int DEFAULT_DAYS = 30;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Delete jobs older than N days', self::DEFAULT_DAYS)
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Only delete jobs with this status (completed|failed)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');
        $olderThan = (int)$input->getOption('older-than');
        $status = $input->getOption('status');

        if ($status !== null && !in_array($status, [ImportJob::STATUS_COMPLETED, ImportJob::STATUS_FAILED], true)) {
            $io->error(sprintf('--status must be "%s" or "%s"', ImportJob::STATUS_COMPLETED, ImportJob::STATUS_FAILED));

            return Command::FAILURE;
        }

        /** @var ImportJobRepository $importJobRepository */
        $importJobRepository = $this->em->getRepository(ImportJob::class);

        $jobs = $importJobRepository->findOlderThan($olderThan, $status);
        if (count($jobs) === 0) {
            $io->info('No matching jobs found.');

            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Name', 'Status', 'Started At'],
            array_map(fn(ImportJob $j) => [
                $j->getId(),
                $j->getName(),
                $j->getStatus(),
                $j->getStartedAt()->format('Y-m-d H:i:s'),
            ], $jobs),
        );

        if ($dryRun) {
            $io->note(sprintf('Dry run: %d job(s) would be deleted.', count($jobs)));

            return Command::SUCCESS;
        }


        $ids = array_map(fn(ImportJob $job) => $job->getId(), $jobs);

        $importJobRepository->deleteLogsForJobs($ids);
        $importJobRepository->deleteOldJobs($ids);

        $io->success(sprintf('Deleted %d job(s) and their log entries.', count($jobs)));

        return Command::SUCCESS;
    }}
