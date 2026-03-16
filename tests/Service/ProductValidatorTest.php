<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\ProductValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductValidatorTest extends TestCase
{
    private ValidatorInterface&Stub $validator;

    private ManagerRegistry&Stub $managerRegistry;

    private EntityManagerInterface&Stub $entityManager;

    private ProductRepository&Stub $repository;

    protected function setUp(): void
    {
        $this->validator = $this->createStub(ValidatorInterface::class);
        $this->managerRegistry = $this->createStub(ManagerRegistry::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->repository = $this->createStub(ProductRepository::class);

        $this->managerRegistry->method('getManager')->willReturn($this->entityManager);
        $this->entityManager->method('getRepository')->willReturn($this->repository);
    }

    private function makeService(): ProductValidator
    {
        return new ProductValidator($this->validator, $this->managerRegistry);
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

    public function testIsSkuTakenReturnsTrueWhenProductExists(): void
    {
        $this->repository->method('findOneBy')->willReturn(new Product());

        $this->assertTrue($this->makeService()->isSkuTaken('SKU-001'));
    }

    public function testIsSkuTakenReturnsFalseWhenProductNotFound(): void
    {
        $this->repository->method('findOneBy')->willReturn(null);

        $this->assertFalse($this->makeService()->isSkuTaken('SKU-999'));
    }
}
