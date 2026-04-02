<?php

declare(strict_types=1);

namespace App\Services;

final class UpdateCatalogue
{
    private const SUCCESS = 'SUCCESS';
    private const FAIL = 'FAIL';

    public function __construct(
        private readonly string $projectDir,
    ) {}

    public function update(string $oldDirectory, string $newName, string $baseDir): string
    {
        $oldDirectory = trim(str_replace('\\', '/', $oldDirectory), '/');
        $newName = trim(str_replace(['\\','/'], '', $newName));

        if ($oldDirectory === '' || $newName === '') {
            return "No old directory or new name provided";
        }

        if ($baseDir !== 'templates' && $baseDir !== 'generated' && $baseDir !== 'archive') {
            return "Invalid base directory";
        }

        // Map visible roots to real filesystem paths
        // if (str_starts_with($oldDirectory, 'generated/')) {
        //     $relative = substr($oldDirectory, strlen('generated/'));
        //     $base = $this->projectDir . '/var/generated';
        // } elseif (str_starts_with($oldDirectory, 'templates/')) {
        //     $relative = substr($oldDirectory, strlen('templates/'));
        //     $base = $this->projectDir . '/templates';
        // } else {
        //     return self::FAIL;
        // }

        $rootBase = match ($baseDir) {
            'templates' => $this->projectDir . '/templates',
            'generated' => $this->projectDir . '/generated',
            'archive' => $this->projectDir . '/archive',
        };

        $oldPath = $rootBase . '/' . $oldDirectory;

        if (!is_dir($oldPath)) {
            return "{$oldPath} is not a directory";
        }

        $parentDir = dirname($oldPath);
        $newPath = $parentDir . '/' . $newName;

        if (file_exists($newPath)) {
            return "{$newPath} already exists";
        }

        return rename($oldPath, $newPath) ? self::SUCCESS : self::FAIL;
    }
}