<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Product;
use Symfony\Contracts\EventDispatcher\Event;

class ProductImportedEvent extends Event
{
    public function __construct(private readonly Product $product)
    {
    }

    public function getProduct(): Product
    {
        return $this->product;
    }
}
