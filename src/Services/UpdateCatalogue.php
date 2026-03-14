<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Atnaujina (pervadina) katalogą /${baseDir}/{directory}.
 * update(oldDirectory, newDirectory, baseDir) → SUCCESS | FAIL
 */
final class UpdateCatalogue
{
    private const SUCCESS = 'SUCCESS';
    private const FAIL = 'FAIL';

    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Pervadina katalogą {baseDir}/{oldDirectory} į {baseDir}/{newDirectory}.
     *
     * @param string $oldDirectory Dabartinis kelias (pvz. "4 Tvarkos/Senas")
     * @param string $newDirectory Naujas kelias (pvz. "4 Tvarkos/Naujas")
     * @param string $baseDir      "templates" arba "var/generated"
     * @return 'SUCCESS'|'FAIL'
     */
    public function update(string $oldDirectory, string $newDirectory, string $baseDir = 'templates'): string
    {
        $oldDirectory = trim(str_replace('\\', '/', $oldDirectory));
        $newDirectory = trim(str_replace('\\', '/', $newDirectory));

        if ($oldDirectory === '' || $newDirectory === '') {
            return self::FAIL;
        }

        $base = $this->resolveBase($baseDir);
        if ($base === null) {
            return self::FAIL;
        }

        $oldPath = $base . '/' . $oldDirectory;
        $newPath = $base . '/' . $newDirectory;

        try {
            if (! is_dir($oldPath)) {
                return self::FAIL;
            }
            if (is_dir($newPath)) {
                return self::FAIL;
            }

            $parentDir = dirname($newPath);
            if (! is_dir($parentDir)) {
                mkdir($parentDir, 0775, true);
            }

            return rename($oldPath, $newPath) ? self::SUCCESS : self::FAIL;
        } catch (\Throwable) {
            return self::FAIL;
        }
    }

    private function resolveBase(string $baseDir): ?string
    {
        $baseDir  = trim(str_replace('\\', '/', $baseDir), '/');
        $fullPath = $this->projectDir . '/' . $baseDir;
        $resolved = realpath($fullPath);
        return ($resolved !== false && is_dir($resolved)) ? $resolved : null;
    }
}
