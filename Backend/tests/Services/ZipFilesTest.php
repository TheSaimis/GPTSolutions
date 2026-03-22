<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\ZipFiles;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ZipFilesTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        // .../Backend/tests/Services → Backend
        $this->projectDir = \dirname(__DIR__, 2);
    }

    public function testZipDirectoryCreatesZipWithAllFiles(): void
    {
        $code    = 'test-zip-' . \uniqid();
        $baseDir = $this->projectDir . '/var/generated/' . $code;

        $this->removeIfExists($baseDir);
        \mkdir($baseDir . '/sub', 0775, true);

        \file_put_contents($baseDir . '/one.txt', '1');
        \file_put_contents($baseDir . '/sub/two.txt', '2');

        $service = new ZipFiles($this->projectDir);
        $zipPath = $service->zipDirectory($code);

        self::assertFileExists($zipPath);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath));

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        $zip->close();

        self::assertContains('one.txt', $names);
        self::assertContains('sub/two.txt', $names);
    }

    public function testZipDirectoryThrowsWhenDirectoryMissing(): void
    {
        $service = new ZipFiles($this->projectDir);

        $this->expectException(\InvalidArgumentException::class);
        $service->zipDirectory('__directory_does_not_exist__');
    }

    public function testZipTemplatesDirectorySkipsTempAndNonDocFiles(): void
    {
        $dirName   = '__zip_templates_test_' . \uniqid();
        $sourceDir = $this->projectDir . '/templates/' . $dirName;

        $this->removeIfExists($sourceDir);
        \mkdir($sourceDir, 0775, true);

        \file_put_contents($sourceDir . '/a.docx', 'A');
        \file_put_contents($sourceDir . '/b.doc', 'B');
        \file_put_contents($sourceDir . '/image.png', 'png');
        \file_put_contents($sourceDir . '/~$temp.docx', 'tmp');
        \file_put_contents($sourceDir . '/desktop.ini', 'ini');

        $service = new ZipFiles($this->projectDir);
        $zipPath = $service->zipTemplatesDirectory($dirName);

        self::assertFileExists($zipPath);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath));

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        $zip->close();

        sort($names);
        self::assertSame(['a.docx', 'b.doc'], $names);

        $this->removeIfExists($sourceDir);
    }

    public function testZipFilesCreatesZipFromExplicitFileList(): void
    {
        $tmpDir = $this->projectDir . '/var/zips-tests';
        $this->removeIfExists($tmpDir);
        \mkdir($tmpDir, 0775, true);

        $f1 = $tmpDir . '/one.docx';
        $f2 = $tmpDir . '/two.docx';
        \file_put_contents($f1, '1');
        \file_put_contents($f2, '2');

        $service = new ZipFiles($this->projectDir);
        $zipPath = $service->zipFiles([$f1, $f2], 'explicit');

        self::assertFileExists($zipPath);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath));

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        $zip->close();

        sort($names);
        self::assertSame(['one.docx', 'two.docx'], $names);
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

