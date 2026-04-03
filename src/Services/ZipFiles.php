<?php

declare(strict_types=1);

namespace App\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

final class ZipFiles
{
    private const ALLOWED_ROOTS = ['templates', 'generated', 'archive', 'deleted'];
    private const ALLOWED_FILE_EXTENSIONS = ['doc', 'docx', 'xls', 'xlsx'];

    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Suarchyvuoja katalogą iš templates/ arba generated/.
     *
     * @param string $root "templates" arba "generated"
     * @param string $directory Kelias po root katalogu, pvz. "aseruogj" arba "folder/subfolder"
     * @return string Pilnas kelias iki sukurto ZIP failo
     */
    public function zipDirectory(string $root, string $directory): string
    {
        $root = trim(str_replace('\\', '/', $root), '/');
        $directory = trim(str_replace('\\', '/', $directory), '/');

        if (!in_array($root, self::ALLOWED_ROOTS, true)) {
            throw new \InvalidArgumentException("Nepalaikomas root katalogas: {$root}");
        }

        $baseDir = realpath($this->projectDir . '/' . $root);
        if ($baseDir === false || !is_dir($baseDir)) {
            throw new \InvalidArgumentException("Bazinis katalogas nerastas: {$root}");
        }

        $sourceDir = $directory !== ''
            ? $baseDir . '/' . $directory
            : $baseDir;

        $sourceReal = realpath($sourceDir);
        if ($sourceReal === false || !is_dir($sourceReal)) {
            throw new \InvalidArgumentException("Katalogas nerastas: {$root}/{$directory}");
        }

        if (!str_starts_with($sourceReal, $baseDir)) {
            throw new \InvalidArgumentException("Kelias išeina iš leistinų ribų: {$root}/{$directory}");
        }

        $zipDir = $this->projectDir . '/var/zips';
        if (!is_dir($zipDir) && !mkdir($zipDir, 0775, true) && !is_dir($zipDir)) {
            throw new \RuntimeException("Nepavyko sukurti ZIP katalogo: {$zipDir}");
        }

        $safeName = $directory !== ''
            ? str_replace(['/', '\\'], '_', $directory)
            : $root;

        $zipPath = $zipDir . '/' . $root . '_' . $safeName . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Nepavyko sukurti ZIP failo: {$zipPath}");
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceReal, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $added = 0;

        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $name = $file->getFilename();

            if (str_starts_with($name, '~') || $name === 'desktop.ini') {
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_FILE_EXTENSIONS, true)) {
                continue;
            }

            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $relativePath = substr($filePath, strlen($sourceReal) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);

            $zip->addFile($filePath, $relativePath);
            $added++;
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            throw new \InvalidArgumentException(
                "Kataloge nėra .doc/.docx/.xls/.xlsx failų: {$root}/{$directory}"
            );
        }

        return $zipPath;
    }

    public function zipFiles(array $absolutePaths, string $zipBaseName = 'generated'): string
    {
        $zipDir = $this->projectDir . '/var/zips';
        if (!is_dir($zipDir) && !mkdir($zipDir, 0775, true) && !is_dir($zipDir)) {
            throw new \RuntimeException("Nepavyko sukurti ZIP katalogo: {$zipDir}");
        }

        $zipPath = $zipDir . '/' . $zipBaseName . '_' . date('Ymd_His') . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Nepavyko sukurti ZIP failo: {$zipPath}");
        }

        $added = 0;

        foreach ($absolutePaths as $filePath) {
            if (!is_string($filePath) || $filePath === '') {
                continue;
            }

            if (!file_exists($filePath) || !is_readable($filePath)) {
                continue;
            }

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
}