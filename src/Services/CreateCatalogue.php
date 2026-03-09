<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Sukuria naują katalogą templates/{directory}/{folderName}.
 * create(directory, folderName) → SUCCESS | FAIL
 */
final class CreateCatalogue
{
    private const SUCCESS = 'SUCCESS';
    private const FAIL = 'FAIL';

    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Sukuria katalogą templates/{directory}/{folderName}.
     *
     * @param string $directory  Kelias po templates/ (pvz. "4 Tvarkos" arba "")
     * @param string $folderName  Naujo katalogo pavadinimas
     * @return 'SUCCESS'|'FAIL'
     */
    public function create(string $directory, string $folderName): string
    {
        $directory = trim(str_replace('\\', '/', $directory));
        $folderName = trim(str_replace(['\\', '/'], '', $folderName));

        if ($folderName === '') {
            return self::FAIL;
        }

        $templatesDir = $this->projectDir . '/templates';
        $targetDir = $directory !== ''
            ? $templatesDir . '/' . $directory . '/' . $folderName
            : $templatesDir . '/' . $folderName;

        try {
            if (is_dir($targetDir)) {
                return self::SUCCESS;
            }
            return mkdir($targetDir, 0775, true) ? self::SUCCESS : self::FAIL;
        } catch (\Throwable) {
            return self::FAIL;
        }
    }
}
