<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\GetPDF;
use PHPUnit\Framework\TestCase;

final class GetPDFTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = \dirname(__DIR__, 2);
    }

    public function testConvertToPdfThrowsWhenFileNotFound(): void
    {
        $libreBin = 'soffice'; // arba pilnas kelias, jei yra
        $service  = new GetPDF($this->projectDir, $libreBin);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Failas nerastas');

        $service->convertToPdf('__does_not_exist__.docx');
    }

    public function testConvertToPdfThrowsWhenNotDocOrDocx(): void
    {
        $dirName  = '__getpdf_test_' . \uniqid();
        $baseDir  = $this->projectDir . '/templates/' . $dirName;
        $txtPath  = $baseDir . '/file.txt';

        $this->removeIfExists($baseDir);
        \mkdir($baseDir, 0775, true);
        \file_put_contents($txtPath, 'content');

        $libreBin = 'soffice';
        $service  = new GetPDF($this->projectDir, $libreBin);

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Palaikomi tik .doc/.docx');
            $service->convertToPdf($dirName . '/file.txt');
        } finally {
            $this->removeIfExists($baseDir);
        }
    }

    public function testConvertToPdfThrowsWhenLibreOfficeBinNotFound(): void
    {
        $dirName   = '__getpdf_test_' . \uniqid();
        $baseDir   = $this->projectDir . '/templates/' . $dirName;
        $docxPath  = $baseDir . '/doc.docx';

        $this->removeIfExists($baseDir);
        \mkdir($baseDir, 0775, true);
        \file_put_contents($docxPath, 'content'); // netikras .docx, bet failas egzistuoja

        $fakeBin = $this->projectDir . '/__nonexistent_soffice_' . \uniqid();
        $service = new GetPDF($this->projectDir, $fakeBin);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('LibreOffice nerastas');
            $service->convertToPdf($dirName . '/doc.docx');
        } finally {
            $this->removeIfExists($baseDir);
        }
    }

    private function removeIfExists(string $path): void
    {
        if (!\file_exists($path)) {
            return;
        }
        if (\is_file($path) || \is_link($path)) {
            @\unlink($path);
            return;
        }
        $items = \array_diff(\scandir($path) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $this->removeIfExists($path . '/' . $item);
        }
        @\rmdir($path);
    }
}
