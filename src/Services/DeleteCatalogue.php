<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Ištrina katalogą templates/{directory}/{folderName}.
 * delete(directory, folderName) → SUCCESS | FAIL
 * Operuoja su /template/directory/subdirectory
 */
final class DeleteCatalogue
{
    private const SUCCESS = 'SUCCESS';
    private const FAIL = 'FAIL';

    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Ištrina katalogą templates/{directory}/{folderName} arba templates/{directory}.
     *
     * @param string $directory  Kelias po templates/ (pvz. "4 Tvarkos")
     * @param string $folderName Katalogo pavadinimas trinimui (pvz. "Senas"). Jei tuščias – trinamas pats directory
     * @return 'SUCCESS'|'FAIL'
     */
    public function delete(string $directory, string $folderName = ''): string
    {
        $directory = trim(str_replace('\\', '/', $directory));
        $folderName = trim(str_replace(['\\', '/'], '', $folderName));

        $templatesDir = $this->projectDir . '/templates';
        $targetPath = $folderName !== ''
            ? $templatesDir . '/' . $directory . '/' . $folderName
            : $templatesDir . '/' . $directory;

        $base = realpath($templatesDir);
        if (!$base || $targetPath === $templatesDir) {
            return self::FAIL;
        }

        try {
            if (!is_dir($targetPath)) {
                return self::FAIL;
            }
            $this->removeDirectory($targetPath);
            return !is_dir($targetPath) ? self::SUCCESS : self::FAIL;
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
}
