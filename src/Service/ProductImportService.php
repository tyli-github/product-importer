<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ImportResult;
use App\DTO\ImportRowDecision;
use App\DTO\ProductImportContext;
use App\DTO\ProductImportRow;
use App\Entity\ImportJob;
use App\Entity\Product;
use App\Event\ProductImportedEvent;
use App\Interface\ImportReaderInterface;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductImportService
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ProductValidator $productValidator,
        private readonly ProductRepository $productRepository,
        private readonly ImportReaderInterface $reader,
        private readonly LoggerInterface $logger,
        private readonly ImportLogService $importLogService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function import(string $filePath, ImportJob $job, ProductImportContext $context): ImportResult
    {
        $processed = 0;
        $failed = 0;
        $skipped = 0;
        $updated = 0;
        $batch = [];

        foreach ($this->reader->read($filePath) as $rowNumber => $row) {
            // product DTO: used to simplify data handling for this class
            $importRow = new ProductImportRow($rowNumber, $row, $this->mapRowToProduct($row));

            $decision = $this->prepareRowDecision($importRow, $job, $context);

            if ($decision->isSkip()) {
                $skipped++;

                continue;
            }

            if ($decision->isFail()) {
                $failed++;

                continue;
            }

            if ($decision->isDryRunProcessed()) {
                $processed++;

                continue;
            }

            if ($decision->isUpdate()) {
                $updated++;
            } else {
                $processed++;
            }

            $batch[] = $decision;

            if (count($batch) === self::BATCH_SIZE) {
                $this->processBatch($batch, $job);
                $batch = [];
            }
        }

        if ($context->isDryRun() === false && $batch !== []) {
            $this->processBatch($batch, $job);
        }

        return new ImportResult($processed, $failed, $skipped, $updated);
    }

    /**
     * @param array<int, ImportRowDecision> $batch
     */
    private function processBatch(array $batch, ImportJob $job): void
    {
        $entityManager = $this->getEntityManager();

        try {
            foreach ($batch as $decision) {
                if (!$decision->isUpdate()) {
                    $entityManager->persist($decision->getProduct());
                }
            }

            $entityManager->flush();

            foreach ($batch as $decision) {
                $this->eventDispatcher->dispatch(new ProductImportedEvent($decision->getProduct()));
                $entityManager->detach($decision->getProduct());
            }
        } catch (DbalException $exception) {
            $message = sprintf('Database error during import: %s', $exception->getMessage());
            $context = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'batch_size' => count($batch),
            ];

            $this->logger->warning('Batch flush failed', $context);
            $this->importLogService->create($job, 'error', $message, null, $context);
        }
    }

    private function mapRowToProduct(array $row): Product
    {
        $product = new Product();
        $product->setName(trim($row['name'] ?? ''));
        $product->setSku(trim($row['sku'] ?? ''));
        $product->setPrice(($row['price'] ?? '') !== '' ? $row['price'] : null);
        $product->setDescription($row['description'] ?? null);
        $product->setCategory($row['category'] ?? null);
        $product->setStock(($row['stock'] ?? '') !== '' ? (int) $row['stock'] : null);
        $product->setStatus($row['status'] ?? null);

        return $product;
    }

    private function prepareRowDecision(
        ProductImportRow $importRow,
        ImportJob $job,
        ProductImportContext $context
    ): ImportRowDecision {
        if ($this->isSkippable($importRow) === true) {
            return ImportRowDecision::skip();
        }

        $errors = $this->productValidator->validate($importRow->getProduct());
        if ($errors !== []) {
            if ($context->isDryRun() === false) {
                $this->importLogService->create(
                    $job,
                    'error',
                    implode(', ', $errors),
                    $importRow->getRowNumber(),
                    $importRow->getRaw(),
                );
            }

            $this->logger->warning('Validation failed', [
                'row' => $importRow->getRowNumber(),
                'errors' => $errors,
            ]);

            return ImportRowDecision::fail();
        }

        if ($sku = $importRow->getSku()) {
            // skip the product if sku has already been checked in the current import
            if ($context->hasProcessedSku($sku)) {
                $this->logger->info('Skipped duplicate SKU', [
                    'row' => $importRow->getRowNumber(),
                    'sku' => $sku,
                ]);

                return ImportRowDecision::skip();
            }

            $decision = $this->handleExistingSku($sku, $importRow, $context);
            if ($decision !== null) {
                return $decision;
            }

            $context->markSkuAsProcessed($sku);
        }

        if ($context->isDryRun()) {
            return ImportRowDecision::dryRunProcessed();
        }

        return ImportRowDecision::batch($importRow->getProduct());
    }

    private function handleExistingSku(string $sku, ProductImportRow $importRow, ProductImportContext $context): ?ImportRowDecision
    {
        $existingProduct = $this->productRepository->findBySku($sku);
        if ($existingProduct === null) {
            return null;
        }

        if (!$context->isAllowUpdates()) {
            $this->logger->info('Skipped existing SKU in database', [
                'row' => $importRow->getRowNumber(),
                'sku' => $sku,
            ]);

            return ImportRowDecision::skip();
        }

        $this->mergeProductData($existingProduct, $importRow->getProduct());
        $this->logger->info('Updating existing SKU', [
            'row' => $importRow->getRowNumber(),
            'sku' => $sku,
        ]);

        $context->markSkuAsProcessed($sku);

        if ($context->isDryRun()) {
            return ImportRowDecision::dryRunProcessed();
        }

        return ImportRowDecision::update($existingProduct);
    }

    private function mergeProductData(Product $existing, Product $new): void
    {
        $existing->setName($new->getName());
        $existing->setPrice($new->getPrice());
        $existing->setDescription($new->getDescription());
        $existing->setCategory($new->getCategory());
        $existing->setStock($new->getStock());
        $existing->setStatus($new->getStatus());
        $existing->setUpdatedAt(new \DateTimeImmutable());
    }

    private function isSkippable(ProductImportRow $importRow): bool
    {
        return array_all($importRow->getRaw(), fn($value) => trim((string) $value) === '');
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->managerRegistry->getManager();
    }
}
