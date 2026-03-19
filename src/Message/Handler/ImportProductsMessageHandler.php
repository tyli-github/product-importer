<?php

declare(strict_types=1);

namespace App\Message\Handler;

use App\DTO\ProductImportContext;
use App\Entity\ImportJob;
use App\Event\ImportCompletedEvent;
use App\Message\ImportProductsMessage;
use App\Repository\ProductRepository;
use App\Service\ImportJobService;
use App\Service\ImportLogService;
use App\Service\ImportSourceDetector;
use App\Service\ProductImportService;
use App\Service\ProductValidator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
readonly class ImportProductsMessageHandler
{
    public function __construct(
        #[AutowireIterator('import.reader')]
        private iterable $readers,
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $managerRegistry,
        private ImportJobService $jobService,
        private ImportLogService $importLogService,
        private ProductValidator $productValidator,
        private ProductRepository $productRepository,
        private ImportSourceDetector $sourceDetector,
        private LoggerInterface $logger,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ImportProductsMessage $message): void
    {
        $job = $this->entityManager->getRepository(ImportJob::class)->find($message->jobId);

        if ($job === null) {
            $this->logger->error('ImportJob not found', ['jobId' => $message->jobId]);

            return;
        }

        try {
            $job->setStatus(ImportJob::STATUS_RUNNING);
            $this->entityManager->flush();

            $reader = $this->sourceDetector->detect($message->source, $this->readers);

            if ($reader === null) {
                throw new RuntimeException(sprintf('No reader supports the source: %s', $message->source));
            }

            $context = new ProductImportContext($message->dryRun, $message->allowUpdates);

            $importService = new ProductImportService(
                $this->managerRegistry,
                $this->productValidator,
                $this->productRepository,
                $reader,
                $this->logger,
                $this->importLogService,
                $this->eventDispatcher,
            );

            $result = $importService->import($message->source, $job, $context);

            $this->jobService->updateJobResults($job, $result);

            $this->eventDispatcher->dispatch(new ImportCompletedEvent($job, $result));

            $this->logger->info('Import completed', [
                'jobId' => $message->jobId,
                'processed' => $result->processed,
                'failed' => $result->failed,
                'skipped' => $result->skipped,
                'updated' => $result->updated,
            ]);
        } catch (Throwable $e) {
            $job->setStatus(ImportJob::STATUS_FAILED);
            $job->setCompletedAt(new DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->error('Import failed', [
                'jobId' => $message->jobId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
