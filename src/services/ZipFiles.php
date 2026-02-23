<?php

declare(strict_types=1);

namespace App\Services;

use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

final class ZipFiles
{
    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * @param string $directory Reliatyvus kelias nuo var/generated (pvz. "123456")
     * @return string Pilnas kelias iki sukurto .zip failo
     */
    public function zipDirectory(string $directory): string
    {
        $sourceDir = $this->projectDir . '/var/generated/' . $directory;

        if (!is_dir($sourceDir)) {
            throw new \InvalidArgumentException("Katalogas nerastas: {$directory}");
        }

        $zipPath = $this->projectDir . '/var/generated/' . $directory . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Nepavyko sukurti ZIP failo: {$zipPath}");
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $filePath     = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        return $zipPath;
    }
}
