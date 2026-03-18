<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Ištrina katalogą {baseDir}/{directory}.
 * delete(directory, baseDir) → SUCCESS | FAIL
 */
final class DeleteCatalogue
{
    private const SUCCESS = 'SUCCESS';
    private const FAIL = 'FAIL';

    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Ištrina katalogą {baseDir}/{directory}.
     *
     * Leidžiami tik baseDir:
     * - templates
     * - generated
     *
     * @param string $directory Kelias po baseDir (pvz. "4 Tvarkos/Naujas")
     * @param string $baseDir   "templates" arba "generated"
     * @return 'SUCCESS'|'FAIL'
     */
    public function delete(string $directory, string $baseDir): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');
        if ($directory === '' || $directory === '.') {
            return "Invalid directory";
        }

        $base = $this->resolveBase($baseDir);
        if ($base === null) {
            return "Invalid base directory";
        }

        $targetPath = $base . '/' . $directory;
        $resolvedTarget = realpath($targetPath);

        if ($resolvedTarget === false || !is_dir($resolvedTarget)) {
            return "Not a directory";
        }

        // extra safety: must stay inside allowed base
        if (!str_starts_with($resolvedTarget, $base . DIRECTORY_SEPARATOR) && $resolvedTarget !== $base) {
            return "Can't delete outside base directory";
        }

        // never allow deleting the base root itself
        if ($resolvedTarget === $base) {
            return "Can't delete base directory";
        }

        try {
            $deletedBase = $this->projectDir . '/deleted/' . $baseDir . '/' . $directory;
            $parentDir = dirname($deletedBase);

            if (!is_dir($parentDir) && !mkdir($parentDir, 0775, true) && !is_dir($parentDir)) {
                return "Failed to create $parentDir";
            }

            if (is_dir($deletedBase)) {
                $this->removeDirectory($deletedBase);
            }

            return rename($resolvedTarget, $deletedBase) ? self::SUCCESS : "Failed to rename $resolvedTarget to $deletedBase";
        } catch (\Throwable) {
            return "Some sort of error happened or sum shi";
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
        $baseDir = trim(str_replace('\\', '/', $baseDir), '/');

        $fullPath = match ($baseDir) {
            'templates' => $this->projectDir . '/templates',
            'generated' => $this->projectDir . '/generated',
            default => null,
        };

        if ($fullPath === null) {
            return null;
        }

        $resolved = realpath($fullPath);

        return ($resolved !== false && is_dir($resolved)) ? $resolved : null;
    }
}