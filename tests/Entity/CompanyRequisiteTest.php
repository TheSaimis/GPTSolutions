<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\CompanyRequisite;
use App\Entity\CompanyType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

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

    private function getValidator(): \Symfony\Component\Validator\Validator\ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testGettersAndSetters(): void
    {
        $e = new CompanyRequisite();
        $ct = new CompanyType();
        $ct->setTypeShort('UAB');
        $ct->setType('Uždaroji akcinė bendrovė');
        $e->setCompanyTypeRef($ct);
        $e->setCompanyName('Test UAB');
        $e->setCode('123456789');
        $e->setCategory('Category');
        $e->setAddress('Gatvė 1');
        $e->setCityOrDistrict('Vilnius');
        $e->setManagerType('vadovas');
        $e->setManagerGender('Vyras');
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
        $this->assertSame('Vyras', $e->getManagerGender());
        $this->assertSame('Jonas', $e->getManagerFirstName());
        $this->assertSame('Jonaitis', $e->getManagerLastName());
        $this->assertSame('2025-01-01', $e->getDocumentDate());
        $this->assertSame('Vadovas', $e->getRole());
        $this->assertSame('/path/to/dir', $e->getDirectory());
        $this->assertSame($createdAt, $e->getCreatedAt());
        $this->assertNull($e->getId());
    }

    public function testValidEntityPassesValidation(): void
    {
        $e = $this->createValidEntity();
        $violations = $this->getValidator()->validate($e);
        $this->assertCount(0, $violations);
    }

    public function testCompanyNameNotBlank(): void
    {
        $e = $this->createValidEntity();
        $e->setCompanyName('');

        $violations = $this->getValidator()->validate($e);
        $this->assertGreaterThan(0, $violations->count());
        $messages = (string) $violations;
        $this->assertStringContainsString('Įmonės pavadinimas', $messages);
    }

    public function testCodeNotBlank(): void
    {
        $e = $this->createValidEntity();
        $e->setCode('');

        $violations = $this->getValidator()->validate($e);
        $this->assertGreaterThan(0, $violations->count());
        $messages = (string) $violations;
        $this->assertStringContainsString('Įmonės kodas', $messages);
    }

    public function testCodeMustBeExactly9Digits(): void
    {
        $e = $this->createValidEntity();
        $e->setCode('12345678'); // 8 digits

        $violations = $this->getValidator()->validate($e);
        $this->assertGreaterThan(0, $violations->count());
        $messages = (string) $violations;
        $this->assertStringContainsString('9', $messages);
    }

    public function testCodeMustContainOnlyDigits(): void
    {
        $e = $this->createValidEntity();
        $e->setCode('12345678A'); // letter at end

        $violations = $this->getValidator()->validate($e);
        $this->assertGreaterThan(0, $violations->count());
        $messages = (string) $violations;
        $this->assertMatchesRegularExpression('/skaitmenų|digits|numeric/i', $messages);
    }

    public function testNullableOptionalFields(): void
    {
        $e = $this->createValidEntity();

        $this->assertNull($e->getCompanyType());
        $this->assertNull($e->getCategory());
        $this->assertNull($e->getAddress());
        $this->assertNull($e->getCityOrDistrict());
        $this->assertNull($e->getManagerType());
        $this->assertNull($e->getManagerGender());
        $this->assertNull($e->getManagerFirstName());
        $this->assertNull($e->getManagerLastName());
        $this->assertNull($e->getDocumentDate());
        $this->assertNull($e->getRole());
        $this->assertNull($e->getDirectory());
    }
}
