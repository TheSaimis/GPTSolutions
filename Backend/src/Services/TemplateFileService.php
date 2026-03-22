<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Šablonų failų valdymas – naudoja FileService su baseDir = "templates".
 */
final class TemplateFileService
{
    private const TEMPLATES_BASE = 'templates';

    public function __construct(
        private readonly FileService $fileService,
    ) {}
    /**
     * Ištrina šabloną templates/{path}.
     *
     * @return 'SUCCESS'|'FAIL'
     */
    public function delete(string $path): string
    {
        return $this->fileService->delete(self::TEMPLATES_BASE, $path);
    }

    /**
     * Pervadina šabloną.
     *
     * @return 'SUCCESS'|'FAIL'
     */
    public function rename(string $path, string $newName): string
    {
        return $this->fileService->rename(self::TEMPLATES_BASE, $path, $newName);
    }
}
