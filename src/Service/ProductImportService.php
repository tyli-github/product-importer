<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ImportResult;
use App\DTO\ImportRowDecision;
use App\DTO\ProductImportContext;
use App\DTO\ProductImportRow;
use App\Entity\ImportJob;
use App\Entity\Product;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductImportService
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ValidatorInterface $validator,
        private readonly CsvReaderService $csvReader,
        private readonly LoggerInterface $logger,
        private readonly ImportLogService $importLogService,
    ) {
    }

    public function import(
        string $filePath,
        ImportJob $job,
        bool $dryRun = false,
        ?callable $onProgress = null
    ): ImportResult
    {
        $processed = 0;
        $failed = 0;
        $skipped = 0;
        $batch = [];
        $context = new ProductImportContext($dryRun);

        foreach ($this->csvReader->read($filePath) as $rowNumber => $row) {
            if ($onProgress !== null) {
                ($onProgress)();
            }

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

            $batch[] = [
                'rowNumber' => $importRow->getRowNumber(),
                'row' => $importRow->getRaw(),
                'product' => $decision->getProduct(),
            ];

            if (count($batch) === self::BATCH_SIZE) {
                $this->processBatch($batch, $job, $processed);
                $batch = [];
            }
        }

        if ($context->isDryRun() === false && $batch !== []) {
            $this->processBatch($batch, $job, $processed);
        }

        return new ImportResult($processed, $failed, $skipped);
    }

    private function processBatch(array $batch, ImportJob $job, int &$processed): void
    {
        $entityManager = $this->getEntityManager();

        try {
            foreach ($batch as $item) {
                $entityManager->persist($item['product']);
            }

            $entityManager->flush();

            foreach ($batch as $item) {
                $entityManager->detach($item['product']);
            }

            $processed += count($batch);
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

        $errors = $this->getValidationErrors($importRow->getProduct());
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
            if ($context->hasProcessedSku($sku)) {
                $this->logger->info('Skipped duplicate SKU', [
                    'row' => $importRow->getRowNumber(),
                    'sku' => $sku,
                ]);

                return ImportRowDecision::skip();
            }
            $productExist = $this->getEntityManager()->getRepository(Product::class)->findOneBy(['sku' => $sku]) !== null;
            if ($productExist === true) {
                $this->logger->info('Skipped existing SKU in database', [
                    'row' => $importRow->getRowNumber(),
                    'sku' => $sku,
                ]);

                return ImportRowDecision::skip();
            }

            $context->markSkuAsProcessed($sku);
        }

        if ($context->isDryRun()) {
            return ImportRowDecision::dryRunProcessed();
        }

        return ImportRowDecision::batch($importRow->getProduct());
    }

    private function getValidationErrors(Product $product): array
    {
        $errors = $this->validator->validate($product);

        $messages = [];
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
        }

        return $messages;
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
