<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Sukuria naują katalogą /${baseDir}/{directory}/{folderName}.
 * create(directory, folderName, baseDir) → SUCCESS | FAIL
 */
final class CreateCatalogue
{
    private const SUCCESS = 'SUCCESS';
    private const FAIL = 'FAIL';

    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * @param string $directory  Kelias po baseDir (pvz. "4 Tvarkos" arba "")
     * @param string $folderName Naujo katalogo pavadinimas
     * @param string $baseDir    "templates" | "generated" | "archive" | "deleted"
     * @return 'SUCCESS'|'FAIL'
     */
    public function create(string $directory, string $folderName, string $baseDir): string
    {
        $directory  = trim(str_replace('\\', '/', $directory));
        $folderName = trim(str_replace(['\\', '/'], '', $folderName));

        if ($folderName === '') {
            return self::FAIL;
        }

        $base = $this->resolveBase($baseDir);
        if ($base === null) {
            return self::FAIL;
        }

        $targetDir = $directory !== ''
            ? $base . '/' . $directory . '/' . $folderName
            : $base . '/' . $folderName;

        try {
            if (is_dir($targetDir)) {
                return self::SUCCESS;
            }
            return mkdir($targetDir, 0775, true) ? self::SUCCESS : self::FAIL;
        } catch (\Throwable) {
            return self::FAIL;
        }
    }

    private function resolveBase(string $baseDir): ?string
    {
        $baseDir = trim(str_replace('\\', '/', $baseDir), '/');
        if (! in_array($baseDir, ['templates', 'generated', 'archive', 'deleted'], true)) {
            return null;
        }
        $fullPath = $this->projectDir . '/' . $baseDir;
        if (! is_dir($fullPath) && ! @mkdir($fullPath, 0775, true) && ! is_dir($fullPath)) {
            return null;
        }
        $resolved = realpath($fullPath);
        return ($resolved !== false && is_dir($resolved)) ? $resolved : null;
    }
}
