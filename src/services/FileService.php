<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Bendras failų servisas – operuoja su bet kuriuo katalogu.
 * BaseDir perduodamas per kiekvieną metodą ir tikrinamas.
 *
 * @param string $baseDir Katalogas nuo projectDir (pvz. "templates", "var/generated")
 */
final class FileService
{
    private const SUCCESS = 'SUCCESS';
    private const FAIL = 'FAIL';

    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Grąžina pilną kelį ir tikrina, kad jis būtų baseDir ribose.
     */
    public function resolvePath(string $baseDir, string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            return null;
        }

        $baseFull = $this->getBaseFullPath($baseDir);
        if ($baseFull === null) {
            return null;
        }

        $fullPath = $baseFull . '/' . $path;
        $resolved = realpath($fullPath);
        if ($resolved === false || !str_starts_with($resolved, $baseFull)) {
            return null;
        }

        return $resolved;
    }

    /**
     * @return 'SUCCESS'|'FAIL'
     */
    public function delete(string $baseDir, string $path, array $allowedExtensions = ['doc', 'docx']): string
    {
        $fullPath = $this->resolvePath($baseDir, $path);
        if ($fullPath === null) {
            return self::FAIL;
        }

        try {
            if (!is_file($fullPath)) {
                return self::FAIL;
            }
            if ($allowedExtensions !== [] && !$this->isAllowedExtension($fullPath, $allowedExtensions)) {
                return self::FAIL;
            }
            return unlink($fullPath) ? self::SUCCESS : self::FAIL;
        } catch (\Throwable) {
            return self::FAIL;
        }
    }

    /**
     * @return 'SUCCESS'|'FAIL'
     */
    public function rename(string $baseDir, string $path, string $newName, array $allowedExtensions = ['doc', 'docx']): string
    {
        $fullPath = $this->resolvePath($baseDir, $path);
        if ($fullPath === null) {
            return self::FAIL;
        }

        $newName = trim(str_replace(['\\', '/'], '', $newName));
        if ($newName === '' || ($allowedExtensions !== [] && !$this->isAllowedExtension($newName, $allowedExtensions))) {
            return self::FAIL;
        }

        $baseFull = $this->getBaseFullPath($baseDir);
        if ($baseFull === null) {
            return self::FAIL;
        }

        $directory = dirname($path);
        $newRelPath = ($directory !== '.' ? $directory . '/' : '') . $newName;
        $newFullPath = $baseFull . '/' . $newRelPath;

        try {
            if (!is_file($fullPath) || file_exists($newFullPath)) {
                return self::FAIL;
            }
            return rename($fullPath, $newFullPath) ? self::SUCCESS : self::FAIL;
        } catch (\Throwable) {
            return self::FAIL;
        }
    }

    /**
     * Grąžina katalogo turinį (katalogai ir failai).
     *
     * @return array{name: string, type: 'directory'|'file', path: string, children?: array}[]
     */
    public function listDirectory(string $baseDir, string $path = ''): array
    {
        $baseFull = $this->getBaseFullPath($baseDir);
        if ($baseFull === null) {
            return [];
        }

        $targetPath = $path !== '' ? $baseFull . '/' . trim(str_replace('\\', '/', $path), '/') : $baseFull;
        $resolved = realpath($targetPath);
        if ($resolved === false || !is_dir($resolved) || !str_starts_with($resolved, $baseFull)) {
            return [];
        }

        $result = [];
        $items = scandir($resolved) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || str_starts_with($item, '~') || $item === 'desktop.ini') {
                continue;
            }
            $itemPath = $resolved . '/' . $item;
            $relPath = $path !== '' ? $path . '/' . $item : $item;
            if (is_dir($itemPath)) {
                $result[] = [
                    'name'     => $item,
                    'type'     => 'directory',
                    'path'     => $relPath,
                    'children' => $this->listDirectory($baseDir, $relPath),
                ];
            } else {
                $result[] = [
                    'name' => $item,
                    'type' => 'file',
                    'path' => $relPath,
                ];
            }
        }

        return $result;
    }

    /**
     * @return string|null Pilnas kelias į baseDir arba null jei neegzistuoja
     */
    public function getBaseFullPath(string $baseDir): ?string
    {
        $baseDir = trim(str_replace('\\', '/', $baseDir), '/');
        $fullPath = $this->projectDir . '/' . $baseDir;
        $resolved = realpath($fullPath);
        return ($resolved !== false && is_dir($resolved)) ? $resolved : null;
    }

    private function isAllowedExtension(string $pathOrName, array $allowedExtensions): bool
    {
        $ext = strtolower(pathinfo($pathOrName, PATHINFO_EXTENSION));
        return in_array($ext, $allowedExtensions, true);
    }
}
