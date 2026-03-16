<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Psr\Log\LoggerInterface;

readonly class ProductExportService
{
    private const array CSV_HEADERS = [
        'id',
        'name',
        'sku',
        'price',
        'description',
        'category',
        'stock',
        'status',
        'createdAt',
        'updatedAt',
    ];

    public function __construct(private ProductRepository $productRepository, private LoggerInterface $logger)
    {
    }

    /**
     * @param array{category?: string, status?: string} $filters
     */
    public function exportCsv(string $outputPath, array $filters = []): int
    {
        $products = $this->fetchProducts($filters);

        $handle = $this->openFile($outputPath);

        fputcsv($handle, self::CSV_HEADERS, escape: '');

        $count = 0;
        foreach ($products as $product) {
            fputcsv($handle, $this->toRow($product), escape: '');
            $count++;
        }

        fclose($handle);

        $this->logger->info('CSV export completed', ['output' => $outputPath, 'count' => $count]);

        return $count;
    }

    /**
     * @param array{category?: string, status?: string} $filters
     */
    public function exportJson(string $outputPath, array $filters = []): int
    {
        $products = $this->fetchProducts($filters);

        $rows = [];
        foreach ($products as $product) {
            $rows[] = array_combine(self::CSV_HEADERS, $this->toRow($product));
        }

        file_put_contents($outputPath, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $count = count($rows);

        $this->logger->info('JSON export completed', ['output' => $outputPath, 'count' => $count]);

        return $count;
    }

    /**
     * @param array{category?: string, status?: string} $filters
     * @return Product[]
     */
    private function fetchProducts(array $filters): array
    {
        $qb = $this->productRepository->createQueryBuilder('p');

        if (!empty($filters['category'])) {
            $qb->andWhere('p.category = :category')->setParameter('category', $filters['category']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('p.status = :status')->setParameter('status', $filters['status']);
        }

        $qb->orderBy('p.id', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<string|int|null>
     */
    private function toRow(Product $product): array
    {
        return [
            $product->getId(),
            $product->getName(),
            $product->getSku(),
            $product->getPrice(),
            $product->getDescription(),
            $product->getCategory(),
            $product->getStock(),
            $product->getStatus(),
            $product->getCreatedAt()->format('Y-m-d H:i:s'),
            $product->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return resource
     */
    private function openFile(string $outputPath): mixed
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($outputPath, 'w');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Cannot open file for writing: %s', $outputPath));
        }

        return $handle;
    }
}
