<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\AddWordDocument;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AddWordDocumentTest extends TestCase
{
    private string $projectDir;
    private string $templatesDir;

    protected function setUp(): void
    {
        $this->projectDir   = \dirname(__DIR__, 2);
        $this->templatesDir = $this->projectDir . '/templates';
    }

    public function testAddWordDocumentReturnsFailForNonDocExtension(): void
    {
        $tmpFile = $this->tmpFile('.txt', 'content');
        $upload  = new UploadedFile($tmpFile, 'document.txt', 'text/plain', \UPLOAD_ERR_OK, true);

        $service = new AddWordDocument($this->projectDir);
        $dir     = '__addword_test_' . \uniqid();
        $result  = $service->addWordDocument($upload, $dir);

        self::assertSame('FAIL', $result);
        @\unlink($tmpFile);
    }

    public function testAddWordDocumentReturnsSuccessAndSavesDocx(): void
    {
        $tmpFile = $this->tmpFile('.docx', 'docx content');
        $upload  = new UploadedFile($tmpFile, 'sablonas.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', \UPLOAD_ERR_OK, true);

        $dirName = '__addword_test_' . \uniqid();
        $target  = $this->templatesDir . '/' . $dirName . '/sablonas.docx';

        $this->removeIfExists($this->templatesDir . '/' . $dirName);

        $service = new AddWordDocument($this->projectDir);
        $result  = $service->addWordDocument($upload, $dirName);

        self::assertSame('SUCCESS', $result);
        self::assertFileExists($target);

        $this->removeIfExists($this->templatesDir . '/' . $dirName);
        @\unlink($tmpFile);
    }

    public function testCreateFolderReturnsFailForEmptyDirectory(): void
    {
        $service = new AddWordDocument($this->projectDir);

        self::assertSame('FAIL', $service->createFolder(''));
        self::assertSame('FAIL', $service->createFolder('.'));
    }

    public function testCreateFolderReturnsSuccessAndCreatesDirectory(): void
    {
        $dirName = '__addword_create_' . \uniqid();
        $full    = $this->templatesDir . '/' . $dirName;

        $this->removeIfExists($full);

        $service = new AddWordDocument($this->projectDir);
        $result  = $service->createFolder($dirName);

        self::assertSame('SUCCESS', $result);
        self::assertDirectoryExists($full);

        $this->removeIfExists($full);
    }

    public function testAddWordDocumentsBulkReturnsResultsForEachFile(): void
    {
        $tmpDocx = $this->tmpFile('.docx', 'x');
        $upload  = new UploadedFile($tmpDocx, 'one.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', \UPLOAD_ERR_OK, true);

        $dirName = '__addword_bulk_' . \uniqid();
        $this->removeIfExists($this->templatesDir . '/' . $dirName);

        $service = new AddWordDocument($this->projectDir);
        $result  = $service->addWordDocumentsBulk([$upload], $dirName);

        self::assertArrayHasKey('status', $result);
        self::assertArrayHasKey('results', $result);
        self::assertSame('SUCCESS', $result['status']);
        self::assertCount(1, $result['results']);
        self::assertSame('one.docx', $result['results'][0]['file']);
        self::assertSame('SUCCESS', $result['results'][0]['status']);

        $this->removeIfExists($this->templatesDir . '/' . $dirName);
        @\unlink($tmpDocx);
    }

    private function tmpFile(string $suffix, string $content): string
    {
        $path = \sys_get_temp_dir() . '/phpunit_addword_' . \uniqid() . $suffix;
        \file_put_contents($path, $content);

        return $path;
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
