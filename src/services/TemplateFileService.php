<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Šablonų failų valdymas: trynimas ir pervadinimas.
 * Operuoja su failais templates/{path}.
 */
final class TemplateFileService
{
    private const SUCCESS = 'SUCCESS';
    private const FAIL = 'FAIL';

    private static array $allowedExtensions = ['doc', 'docx'];

    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Ištrina šabloną templates/{path}.
     *
     * @param string $path Kelias po templates/ (pvz. "4 Tvarkos/3 Mobingo Tvarka 2023.docx")
     * @return 'SUCCESS'|'FAIL'
     */
    public function delete(string $path): string
    {
        $fullPath = $this->resolvePath($path);
        if ($fullPath === null) {
            return self::FAIL;
        }

        try {
            if (!is_file($fullPath)) {
                return self::FAIL;
            }
            if (!$this->isAllowedExtension($fullPath)) {
                return self::FAIL;
            }
            return unlink($fullPath) ? self::SUCCESS : self::FAIL;
        } catch (\Throwable) {
            return self::FAIL;
        }
    }

    /**
     * Pervadina šabloną templates/{path} į templates/{directory}/{newName}.
     *
     * @param string $path    Dabartinis kelias (pvz. "4 Tvarkos/3 Mobingo Tvarka 2023.docx")
     * @param string $newName Naujas failo pavadinimas (pvz. "Naujas pavadinimas.docx")
     * @return 'SUCCESS'|'FAIL'
     */
    public function rename(string $path, string $newName): string
    {
        $fullPath = $this->resolvePath($path);
        if ($fullPath === null) {
            return self::FAIL;
        }

        $newName = trim(str_replace(['\\', '/'], '', $newName));
        if ($newName === '') {
            return self::FAIL;
        }

        if (!$this->isAllowedExtension($newName)) {
            return self::FAIL;
        }

        $directory = dirname($path);
        $templatesDir = realpath($this->projectDir . '/templates');
        $newRelPath = ($directory !== '.' ? $directory . '/' : '') . $newName;
        $newFullPath = $templatesDir . '/' . $newRelPath;

        try {
            if (!is_file($fullPath)) {
                return self::FAIL;
            }
            if (file_exists($newFullPath)) {
                return self::FAIL;
            }
            return rename($fullPath, $newFullPath) ? self::SUCCESS : self::FAIL;
        } catch (\Throwable) {
            return self::FAIL;
        }
    }

    private function resolvePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            return null;
        }

        $templatesDir = $this->projectDir . '/templates';
        $fullPath = $templatesDir . '/' . $path;
        $base = realpath($templatesDir);

        if (!$base) {
            return null;
        }

        $resolved = realpath($fullPath);
        if ($resolved === false || !str_starts_with($resolved, $base)) {
            return null;
        }

        return $resolved;
    }

    private function isAllowedExtension(string $pathOrName): bool
    {
        $ext = strtolower(pathinfo($pathOrName, PATHINFO_EXTENSION));
        return in_array($ext, self::$allowedExtensions, true);
    }
}
