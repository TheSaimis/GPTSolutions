<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Ištrina katalogą /${baseDir}/{directory}/{folderName}.
 * delete(directory, folderName, baseDir) → SUCCESS | FAIL
 */
final class DeleteCatalogue
{
    private const SUCCESS = 'SUCCESS';
    private const FAIL = 'FAIL';

    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Ištrina katalogą {baseDir}/{directory}/{folderName} arba {baseDir}/{directory}.
     *
     * @param string $directory  Kelias po baseDir (pvz. "4 Tvarkos")
     * @param string $folderName Katalogo pavadinimas trinimui. Jei tuščias – trinamas pats directory
     * @param string $baseDir    "templates" arba "var/generated"
     * @return 'SUCCESS'|'FAIL'
     */
    public function delete(string $directory, string $folderName = '', string $baseDir = 'templates'): string
    {
        $directory  = trim(str_replace('\\', '/', $directory));
        $folderName = trim(str_replace(['\\', '/'], '', $folderName));

        $base = $this->resolveBase($baseDir);
        if ($base === null) {
            return self::FAIL;
        }

        $targetPath = $folderName !== ''
            ? $base . '/' . $directory . '/' . $folderName
            : $base . '/' . $directory;

        if ($targetPath === $base || $directory === '') {
            return self::FAIL;
        }

        try {
            if (! is_dir($targetPath)) {
                return self::FAIL;
            }
            $this->removeDirectory($targetPath);
            return ! is_dir($targetPath) ? self::SUCCESS : self::FAIL;
        } catch (\Throwable) {
            return self::FAIL;
        }
    }

    private function removeDirectory(string $dir): void
    {
        $items = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function resolveBase(string $baseDir): ?string
    {
        $baseDir  = trim(str_replace('\\', '/', $baseDir), '/');
        $fullPath = $this->projectDir . '/' . $baseDir;
        $resolved = realpath($fullPath);
        return ($resolved !== false && is_dir($resolved)) ? $resolved : null;
    }
}
