<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\CreateFile;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreateFileTest extends KernelTestCase
{
    private function createCreateFile(): CreateFile
    {
        self::bootKernel();

        return self::getContainer()->get(CreateFile::class);
    }

    public function testCreateWordDocumentValidatesRequiredFields(): void
    {
        $service = $this->createCreateFile();

        $this->expectException(\InvalidArgumentException::class);

        // Trūksta template ir kodas/companyName
        $service->createWordDocument([
            'companyName' => 'Test Company',
        ]);
    }

    public function testCreateWordDocumentThrowsWhenTemplateNotFound(): void
    {
        $service = $this->createCreateFile();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Šablonas nerastas');

        $service->createWordDocument([
            'directory'   => '',
            'template'    => '__does_not_exist__.docx',
            'companyName' => 'Test Company',
            'code'        => 'CODE123',
        ]);
    }
}
