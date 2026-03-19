<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findBySku(string $sku): ?Product
    {
        return $this->findOneBy(['sku' => $sku]);
    }

    public function skuExists(string $sku): bool
    {
        return $this->findBySku($sku) !== null;
    }
}
