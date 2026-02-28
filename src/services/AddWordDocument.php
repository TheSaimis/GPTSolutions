<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Įkelia Word (.docx) šabloną į templates/{directory}/.
 * addWordDocument(blob docx file, directory) → SUCCESS | FAIL
 */
final class AddWordDocument
{
    private const SUCCESS = 'SUCCESS';
    private const FAIL = 'FAIL';

    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Išsaugo įkeltą .docx failą į templates/{directory}/.
     *
     * @param UploadedFile $file      Įkeltas .docx failas
     * @param string       $directory Katalogas po templates/ (pvz. "4 Tvarkos" arba "4 Tvarkos/3 Mobingo")
     * @return 'SUCCESS'|'FAIL'
     */
    public function addWordDocument(UploadedFile $file, string $directory): string
    {
        $directory = trim(str_replace('\\', '/', $directory));
        if ($directory === '' || $directory === '.') {
            $directory = '';
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['doc', 'docx'], true)) {
            return self::FAIL;
        }

        $templatesDir = $this->projectDir . '/templates';
        $targetDir = $templatesDir . ($directory !== '' ? '/' . $directory : '');

        try {
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0775, true);
            }

            $filename = $file->getClientOriginalName();
            if ($filename === '') {
                $filename = 'template_' . date('Ymd_His') . '.docx';
            }

            $targetPath = $targetDir . '/' . $filename;
            $file->move($targetDir, $filename);

            return file_exists($targetPath) && is_readable($targetPath) ? self::SUCCESS : self::FAIL;
        } catch (\Throwable) {
            return self::FAIL;
        }
    }

    /**
     * Sukuria naują katalogą templates/{directory}/.
     *
     * @param string $directory Kelias po templates/ (pvz. "4 Tvarkos" arba "4 Tvarkos/Naujas")
     * @return 'SUCCESS'|'FAIL'
     */
    public function createFolder(string $directory): string
    {
        $directory = trim(str_replace('\\', '/', $directory));
        if ($directory === '' || $directory === '.') {
            return self::FAIL;
        }

        $templatesDir = $this->projectDir . '/templates';
        $targetDir = $templatesDir . '/' . $directory;

        try {
            if (is_dir($targetDir)) {
                return self::SUCCESS;
            }
            return mkdir($targetDir, 0775, true) ? self::SUCCESS : self::FAIL;
        } catch (\Throwable) {
            return self::FAIL;
        }
    }

    /**
     * Masinis šablonų įkėlimas į templates/{directory}/.
     *
     * @param UploadedFile[] $files
     * @return array{status: 'SUCCESS'|'FAIL', results: array<array{file: string, status: 'SUCCESS'|'FAIL'}>}
     */
    public function addWordDocumentsBulk(array $files, string $directory): array
    {
        $results = [];
        $allSuccess = true;

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $filename = $file->getClientOriginalName() ?: 'unknown';
            $status = $this->addWordDocument($file, $directory);
            $results[] = ['file' => $filename, 'status' => $status];
            if ($status === self::FAIL) {
                $allSuccess = false;
            }
        }

        return [
            'status' => $allSuccess ? self::SUCCESS : self::FAIL,
            'results' => $results,
        ];
    }
}
