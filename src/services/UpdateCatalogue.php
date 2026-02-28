<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Atnaujina (pervadina) katalogą templates/{directory}.
 * update(oldDirectory, newDirectory) → SUCCESS | FAIL
 * Operuoja su /template/directory/subdirectory
 */
final class UpdateCatalogue
{
    private const SUCCESS = 'SUCCESS';
    private const FAIL = 'FAIL';

    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Pervadina katalogą templates/{oldDirectory} į templates/{newDirectory}.
     *
     * @param string $oldDirectory Dabartinis kelias (pvz. "4 Tvarkos/Senas")
     * @param string $newDirectory Naujas kelias (pvz. "4 Tvarkos/Naujas")
     * @return 'SUCCESS'|'FAIL'
     */
    public function update(string $oldDirectory, string $newDirectory): string
    {
        $oldDirectory = trim(str_replace('\\', '/', $oldDirectory));
        $newDirectory = trim(str_replace('\\', '/', $newDirectory));

        if ($oldDirectory === '' || $newDirectory === '') {
            return self::FAIL;
        }

        $templatesDir = $this->projectDir . '/templates';
        $oldPath = $templatesDir . '/' . $oldDirectory;
        $newPath = $templatesDir . '/' . $newDirectory;

        try {
            if (!is_dir($oldPath)) {
                return self::FAIL;
            }
            if (is_dir($newPath)) {
                return self::FAIL;
            }
            return rename($oldPath, $newPath) ? self::SUCCESS : self::FAIL;
        } catch (\Throwable) {
            return self::FAIL;
        }
    }
}
