<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\UpdateCatalogue;
use PHPUnit\Framework\TestCase;

final class UpdateCatalogueTest extends TestCase
{
    private string $projectDir;
    private string $templatesDir;

    protected function setUp(): void
    {
        $this->projectDir   = \dirname(__DIR__, 2);
        $this->templatesDir = $this->projectDir . '/templates';
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
