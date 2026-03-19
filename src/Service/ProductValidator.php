<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class ProductValidator
{
    public function __construct(private ValidatorInterface $validator)
    {
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
}
