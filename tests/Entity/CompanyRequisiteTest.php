<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\CompanyRequisite;
use PHPUnit\Framework\TestCase;

final class CompanyRequisiteTest extends TestCase
{
    private function createValidEntity(): CompanyRequisite
    {
        $e = new CompanyRequisite();
        $e->setCompanyName('UAB Test Company');
        $e->setCode('123456789');
        $e->setCreatedAt(new \DateTimeImmutable());
        return $e;
    }

    public function testGettersAndSetters(): void
    {
        $e = new CompanyRequisite();
        $e->setCompanyType('UAB');
        $e->setCompanyName('Test UAB');
        $e->setCode('123456789');
        $e->setCategory('Category');
        $e->setAddress('Gatvė 1');
        $e->setCityOrDistrict('Vilnius');
        $e->setManagerType('vadovas');
        $e->setManagerFirstName('Jonas');
        $e->setManagerLastName('Jonaitis');
        $e->setDocumentDate('2025-01-01');
        $e->setRole('Vadovas');
        $e->setDirectory('/path/to/dir');
        $createdAt = new \DateTimeImmutable('2025-01-15');
        $e->setCreatedAt($createdAt);

        $this->assertSame('UAB', $e->getCompanyType());
        $this->assertSame('Test UAB', $e->getCompanyName());
        $this->assertSame('123456789', $e->getCode());
        $this->assertSame('Category', $e->getCategory());
        $this->assertSame('Gatvė 1', $e->getAddress());
        $this->assertSame('Vilnius', $e->getCityOrDistrict());
        $this->assertSame('vadovas', $e->getManagerType());
        $this->assertSame('Jonas', $e->getManagerFirstName());
        $this->assertSame('Jonaitis', $e->getManagerLastName());
        $this->assertSame('2025-01-01', $e->getDocumentDate());
        $this->assertSame('Vadovas', $e->getRole());
        $this->assertSame('/path/to/dir', $e->getDirectory());
        $this->assertSame($createdAt, $e->getCreatedAt());
        $this->assertNull($e->getId());
    }

    public function testNullableOptionalFields(): void
    {
        $e = $this->createValidEntity();

        $this->assertNull($e->getCompanyType());
        $this->assertNull($e->getCategory());
        $this->assertNull($e->getAddress());
        $this->assertNull($e->getCityOrDistrict());
        $this->assertNull($e->getManagerType());
        $this->assertNull($e->getManagerFirstName());
        $this->assertNull($e->getManagerLastName());
        $this->assertNull($e->getDocumentDate());
        $this->assertNull($e->getRole());
        $this->assertNull($e->getDirectory());
    }
}
