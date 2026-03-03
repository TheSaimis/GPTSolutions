<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\CreateCatalogue;
use App\Services\DeleteCatalogue;
use App\Services\UpdateCatalogue;
use PHPUnit\Framework\TestCase;

final class CatalogueServicesTest extends TestCase
{
    private string $projectDir;
    private string $templatesDir;

    protected function setUp(): void
    {
        $this->projectDir   = \dirname(__DIR__, 2);
        $this->templatesDir = $this->projectDir . '/templates';
    }

    public function testCreateCatalogueCreatesNestedDirectory(): void
    {
        $service = new CreateCatalogue($this->projectDir);

        $parent = '__tests_catalogues';
        $name   = 'CreateMe';

        $fullPath = $this->templatesDir . '/' . $parent . '/' . $name;
        $this->removeIfExists($this->templatesDir . '/' . $parent);

        $result = $service->create($parent, $name);

        self::assertSame('SUCCESS', $result);
        self::assertDirectoryExists($fullPath);
    }

    public function testUpdateCatalogueRenamesDirectory(): void
    {
        $service = new UpdateCatalogue($this->projectDir);

        $base      = $this->templatesDir . '/__tests_update';
        $oldFolder = $base . '/OldName';
        $newFolder = $base . '/NewName';

        $this->removeIfExists($base);
        \mkdir($oldFolder, 0775, true);

        $result = $service->update('__tests_update/OldName', '__tests_update/NewName');

        self::assertSame('SUCCESS', $result);
        self::assertDirectoryExists($newFolder);
        self::assertDirectoryDoesNotExist($oldFolder);
    }

    public function testDeleteCatalogueRemovesDirectoryRecursively(): void
    {
        $service = new DeleteCatalogue($this->projectDir);

        $dir  = '__tests_delete/Parent/Child';
        $full = $this->templatesDir . '/' . $dir;

        $this->removeIfExists($this->templatesDir . '/__tests_delete');
        \mkdir($full, 0775, true);
        \file_put_contents($full . '/file.txt', 'test');

        $result = $service->delete('__tests_delete');

        self::assertSame('SUCCESS', $result);
        self::assertDirectoryDoesNotExist($this->templatesDir . '/__tests_delete');
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

