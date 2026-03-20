<?php

declare (strict_types = 1);

namespace App\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

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
        $sourceDir = $this->projectDir . '/generated/' . $directory;

        if (! is_dir($sourceDir)) {
            throw new \InvalidArgumentException("Katalogas nerastas: {$directory}");
        }

        $zipPath = $this->projectDir . '/generated/' . $directory . '.zip';

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
                $relativePath = str_replace('\\', '/', $relativePath);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        return $zipPath;
    }

    public function zipFiles(array $absolutePaths, string $zipBaseName = 'generated'): string
    {
        $zipDir = $this->projectDir . '/var/zips';
        if (! is_dir($zipDir)) {
            mkdir($zipDir, 0775, true);
        }

        $zipPath = $zipDir . '/' . $zipBaseName . '_' . date('Ymd_His') . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Nepavyko sukurti ZIP failo: {$zipPath}");
        }

        $added = 0;

        foreach ($absolutePaths as $filePath) {
            if (! is_string($filePath) || $filePath === '') {
                continue;
            }

            if (! file_exists($filePath) || ! is_readable($filePath)) {
                continue;
            }

            // name inside zip
            $zip->addFile($filePath, basename($filePath));
            $added++;
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            throw new \InvalidArgumentException("Nė vienas failas nepridėtas į ZIP.");
        }

        return $zipPath;
    }

    /**
     * Suarchyvuoja templates/{directory}/ į .zip.
     *
     * @param string $directory Kelias po templates/ (pvz. "4 Tvarkos" arba "4 Tvarkos/3 Mobingo")
     * @return string Pilnas kelias iki sukurto .zip failo
     * @throws \InvalidArgumentException Jei katalogas nerastas
     */
    public function zipTemplatesDirectory(string $directory): string
    {
        $templatesDir = $this->projectDir . '/templates';
        $sourceDir    = $directory !== '' ? $templatesDir . '/' . $directory : $templatesDir;

        if (! is_dir($sourceDir)) {
            throw new \InvalidArgumentException("Katalogas nerastas: {$directory}");
        }

        $safeName = str_replace(['/', '\\'], '_', $directory) ?: 'templates';
        $zipPath  = $this->projectDir . '/var/' . $safeName . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Nepavyko sukurti ZIP failo: {$zipPath}");
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $added = 0;
        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $name = $file->getFilename();
            if (str_starts_with($name, '~') || $name === 'desktop.ini') {
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! in_array($ext, ['doc', 'docx', 'xls', 'xlsx'], true)) {
                continue;
            }

            $filePath     = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);
            $zip->addFile($filePath, $relativePath);
            $added++;
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            throw new \InvalidArgumentException("Kataloge nėra .doc/.docx/.xls/.xlsx failų: {$directory}");
        }

        return $zipPath;
    }
}
