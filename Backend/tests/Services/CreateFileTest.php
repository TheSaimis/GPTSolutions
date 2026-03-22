<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\CreateFile;
use App\Services\ManagerGenderResolver;
use App\Services\Metadata\DocxMetadataService;
use App\Services\Namer;
use App\Services\ConvertDocToDocx;
use App\Services\LibreOfficeBinResolver;
use App\Services\DocxSplitMacroReplacer;
use PHPUnit\Framework\TestCase;

final class CreateFileTest extends TestCase
{
    private string $projectDir;

    private Namer $namer;

    private ConvertDocToDocx $convertDocToDocx;

    protected function setUp(): void
    {
        $this->projectDir = \dirname(__DIR__, 2);
        $this->namer = new Namer(new ManagerGenderResolver());
        $fakeLibre = $this->projectDir . '/_missing_libreoffice_for_tests_' . \uniqid('', true);
        $this->convertDocToDocx = new ConvertDocToDocx($this->projectDir, new LibreOfficeBinResolver($fakeLibre, 'test'));
    }

    public function testCreateWordDocumentValidatesRequiredFields(): void
    {
        $service = new CreateFile(
            $this->projectDir,
            $this->namer,
            new DocxMetadataService(),
            $this->convertDocToDocx,
            new DocxSplitMacroReplacer()
        );

        $this->expectException(\InvalidArgumentException::class);

        // Trūksta template ir kodas/companyName
        $service->createWordDocument([
            'companyName' => 'Test Company',
        ]);
    }

    public function testCreateWordDocumentThrowsWhenTemplateNotFound(): void
    {
        $service = new CreateFile(
            $this->projectDir,
            $this->namer,
            new DocxMetadataService(),
            $this->convertDocToDocx,
            new DocxSplitMacroReplacer()
        );

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

