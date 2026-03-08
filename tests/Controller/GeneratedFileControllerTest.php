<?php

declare(strict_types=1);

namespace App\Tests\Controller;

final class GeneratedFileControllerTest extends ApiWebTestCase
{
    public function testZipDirectoryNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/generated/zip/__nonexistent_dir_xyz_123');

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);
    }

    public function testAllZipNoGeneratedDirs(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/generated/all/zip');

        // Either 200 with zip (if var/generated has dirs) or 404
        $status = $client->getResponse()->getStatusCode();
        self::assertContains($status, [200, 404]);

        if ($status === 404) {
            $data = json_decode($client->getResponse()->getContent(), true);
            self::assertArrayHasKey('error', $data);
        } else {
            self::assertSame('application/zip', $client->getResponse()->headers->get('Content-Type'));
        }
    }

    public function testTemplatesZipPathNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/templates/zip/__nonexistent_path_xyz');

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('FAIL', $data['status']);
        self::assertArrayHasKey('error', $data);
    }

    public function testTemplatesZip(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/templates/zip');

        // 200 with zip if templates exist and have doc/docx, else 404
        $status = $client->getResponse()->getStatusCode();
        self::assertContains($status, [200, 404]);

        if ($status === 200) {
            self::assertSame('application/zip', $client->getResponse()->headers->get('Content-Type'));
        }
    }
}
