<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class ProductValidator
{
    public function __construct(
        private ValidatorInterface $validator,
        private ManagerRegistry $managerRegistry,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function validate(Product $product): array
    {
        $errors = $this->validator->validate($product);

        $messages = [];
        foreach ($errors as $error) {
            $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
        }

        return $messages;
    }

    public function isSkuTaken(string $sku): bool
    {
        /** @var ProductRepository $repository */
        $repository = $this->managerRegistry->getManager()->getRepository(Product::class);

        return $repository->findOneBy(['sku' => $sku]) !== null;
    }
}
