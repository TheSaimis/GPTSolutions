<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\CreateCatalogue;
use PHPUnit\Framework\TestCase;

final class CreateCatalogueTest extends TestCase
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
