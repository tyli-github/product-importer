<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Service\ProductValidator;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductValidatorTest extends TestCase
{
    private ValidatorInterface&Stub $validator;

    protected function setUp(): void
    {
        $this->validator = $this->createStub(ValidatorInterface::class);
    }

    private function makeService(): ProductValidator
    {
        return new ProductValidator($this->validator);
    }

    public function testValidateReturnsEmptyArrayForValidProduct(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $result = $this->makeService()->validate(new Product());

        $this->assertSame([], $result);
    }

    public function testValidateReturnsFormattedErrorMessages(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('must not be blank', null, [], null, 'name', null),
            new ConstraintViolation('invalid format', null, [], null, 'price', null),
        ]);
        $this->validator->method('validate')->willReturn($violations);

        $result = $this->makeService()->validate(new Product());

        $this->assertSame(['name: must not be blank', 'price: invalid format'], $result);
    }
}
