<?php

declare(strict_types=1);

namespace App\Tests\Controller;

final class CatalogueControllerTest extends ApiWebTestCase
{
    private const TEST_DIR = '__test_catalogue_ctrl';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupTestDir();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestDir();
        parent::tearDown();
    }

    private function cleanupTestDir(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $path = $projectDir . '/templates/' . self::TEST_DIR;
        if (is_dir($path)) {
            $this->removeDir($path);
        }
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

    public function testCreateCatalogueSuccess(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/catalogue/template/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'directory' => self::TEST_DIR,
            'folderName' => 'NewFolder',
        ]));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('SUCCESS', $data['status']);
    }

    public function testCreateCatalogueInvalidJson(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/catalogue/template/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid');

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('FAIL', $data['status']);
    }

    public function testCreateCatalogueMissingFolderName(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/catalogue/template/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'directory' => self::TEST_DIR,
            'folderName' => '',
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    public function testUpdateCatalogueSuccess(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/catalogue/template/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'directory' => self::TEST_DIR,
            'folderName' => 'OldName',
        ]));
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/catalogue/template/update', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'oldDirectory' => self::TEST_DIR . '/OldName',
            'newDirectory' => self::TEST_DIR . '/NewName',
        ]));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('SUCCESS', $data['status']);
    }

    public function testUpdateCatalogueMissingParams(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/catalogue/template/update', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'oldDirectory' => '',
            'newDirectory' => 'X',
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    public function testDeleteCatalogueSuccess(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/catalogue/template/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'directory' => self::TEST_DIR,
            'folderName' => 'ToDelete',
        ]));
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/catalogue/template/delete', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'directory' => self::TEST_DIR,
            'folderName' => 'ToDelete',
        ]));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('SUCCESS', $data['status']);
    }

    public function testDeleteCatalogueMissingDirectory(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/catalogue/template/delete', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'directory' => '',
            'folderName' => 'X',
        ]));

        self::assertResponseStatusCodeSame(400);
    }
}
