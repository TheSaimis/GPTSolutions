<?php

declare(strict_types=1);

namespace App\Tests\Controller;

final class TemplateControllerTest extends ApiWebTestCase
{
    public function testAllTemplates(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/templates/all');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($data);
    }

    public function testTemplatesByCategoryNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/templates/__nonexistent_category_xyz_123');

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);
    }

    public function testCreateFolderSuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $dir = '__test_template_ctrl_' . uniqid();

        $client->request('POST', '/api/template/createFolder', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['directory' => $dir]));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('SUCCESS', $data['status']);

        $this->removeTestDir($dir);
    }

    public function testCreateFolderMissingDirectory(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/template/createFolder', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['directory' => '']));

        self::assertResponseStatusCodeSame(400);
    }

    public function testFillFileMissingFile(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/template/fillFile', [
            'directory' => 'test',
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testFillFileMissingDirectory(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/template/fillFile', [
            'directory' => '',
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testFillFileBulkInvalidJson(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/template/fillFileBulk', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid');

        self::assertResponseStatusCodeSame(400);
    }

    public function testFillFileBulkMissingCompanyId(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/template/fillFileBulk', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'templates' => ['test/file.docx'],
        ]));

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);
    }

    public function testFillFileBulkMissingTemplates(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/template/fillFileBulk', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'companyId' => 1,
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    public function testFillFileBulkCompanyNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/template/fillFileBulk', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'companyId' => 999999,
            'templates' => ['test/file.docx'],
        ]));

        self::assertResponseStatusCodeSame(404);
    }

    public function testPreviewPdfNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/templates/pdf/__nonexistent/path.docx');

        self::assertResponseStatusCodeSame(404);
    }

    public function testGetTemplateFileNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/templates/file/__nonexistent/path.docx');

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);
    }

    private function removeTestDir(string $dir): void
    {
        $projectDir = dirname(__DIR__, 2);
        $path = $projectDir . '/templates/' . $dir;
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
            $full = $path . '/' . $item;
            is_dir($full) ? $this->removeDir($full) : @unlink($full);
        }
        @rmdir($path);
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
            $full = $path . '/' . $item;
            is_dir($full) ? $this->removeDir($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
