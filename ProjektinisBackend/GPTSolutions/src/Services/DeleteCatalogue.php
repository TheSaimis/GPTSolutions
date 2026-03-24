<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Bundle\SecurityBundle\Security;

/**
 * Ištrina katalogą {baseDir}/{directory}.
 *
 * Elgsena:
 * - templates/generated -> soft delete į /deleted/{baseDir}/{directory}
 * - deleted -> jei vartotojas turi ROLE_ADMIN, ištrina visam laikui
 */
final class DeleteCatalogue
{
    private const SUCCESS = 'SUCCESS';
    private const FAIL = 'FAIL';

    public function __construct(
        private readonly string $projectDir,
        private readonly Security $security,
    ) {}

    /**
     * @param string $directory Kelias po baseDir
     * @param string $baseDir   "templates" | "generated" | "deleted"
     */
    public function delete(string $directory, string $baseDir): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');
        if ($directory === '' || $directory === '.') {
            return 'Invalid directory';
        }

        $base = $this->resolveBase($baseDir);
        if ($base === null) {
            return 'Invalid base directory';
        }

        $targetPath = $base . '/' . $directory;
        $resolvedTarget = realpath($targetPath);

        if ($resolvedTarget === false || !is_dir($resolvedTarget)) {
            return 'Not a directory';
        }

        if (
            !str_starts_with($resolvedTarget, $base . DIRECTORY_SEPARATOR)
            && $resolvedTarget !== $base
        ) {
            return "Can't delete outside base directory";
        }

        if ($resolvedTarget === $base) {
            return "Can't delete base directory";
        }

        try {
            if ($baseDir === 'deleted') {
                if (!$this->security->isGranted('ROLE_ADMIN')) {
                    return 'Only admin can permanently delete from deleted';
                }

                $this->removeDirectory($resolvedTarget);
                return self::SUCCESS;
            }

            $deletedBase = $this->projectDir . '/deleted/' . $baseDir . '/' . $directory;
            $parentDir = dirname($deletedBase);

            if (!is_dir($parentDir) && !mkdir($parentDir, 0775, true) && !is_dir($parentDir)) {
                return "Failed to create $parentDir";
            }

            if (is_dir($deletedBase)) {
                $this->removeDirectory($deletedBase);
            }

            return $this->moveDirectory($resolvedTarget, $deletedBase)
                ? self::SUCCESS
                : "Failed to move $resolvedTarget to $deletedBase";
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    private function moveDirectory(string $source, string $destination): bool
    {
        if (@rename($source, $destination)) {
            return true;
        }

        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
            return false;
        }

        $items = array_diff(scandir($source) ?: [], ['.', '..']);

        foreach ($items as $item) {
            $srcPath = $source . '/' . $item;
            $dstPath = $destination . '/' . $item;

            if (is_dir($srcPath)) {
                if (!$this->moveDirectory($srcPath, $dstPath)) {
                    return false;
                }
            } else {
                if (!copy($srcPath, $dstPath)) {
                    return false;
                }

                if (!unlink($srcPath)) {
                    return false;
                }
            }
        }

        return rmdir($source);
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
            'deleted' => $this->projectDir . '/deleted',
            default => null,
        };

        if ($fullPath === null) {
            return null;
        }

        if ($baseDir === 'deleted' && !is_dir($fullPath)) {
            return null;
        }

        $resolved = realpath($fullPath);

        return ($resolved !== false && is_dir($resolved)) ? $resolved : null;
    }
}