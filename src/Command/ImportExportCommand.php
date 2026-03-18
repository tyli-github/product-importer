<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ProductExportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'import:export',
    description: 'Export products to CSV or JSON',
)]
class ImportExportCommand extends Command
{
    private const string DEFAULT_OUTPUT_DIR = 'var/share/export';

    public function __construct(private readonly ProductExportService $exportService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: csv or json', 'csv')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output file path (default: var/share/products.<format>)')
            ->addOption('category', null, InputOption::VALUE_REQUIRED, 'Filter by category')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $format = strtolower((string)$input->getOption('format'));
        if (!in_array($format, ['csv', 'json'], true)) {
            $io->error(sprintf('Invalid format "%s". Use csv or json.', $format));

            return Command::FAILURE;
        }

        $outputPath = $input->getOption('output')
            ?? sprintf('%s/%s/products.%s', getcwd(), self::DEFAULT_OUTPUT_DIR, $format);

        $filters = array_filter([
            'category' => $input->getOption('category'),
            'status' => $input->getOption('status'),
        ]);

        $io->info(sprintf('Exporting products to %s (%s)', $outputPath, $format));

        $count = match ($format) {
            'csv' => $this->exportService->exportCsv($outputPath, $filters),
            'json' => $this->exportService->exportJson($outputPath, $filters),
        };

        $io->success(sprintf('Exported %d product(s) to %s', $count, $outputPath));

        return Command::SUCCESS;
    }
}
