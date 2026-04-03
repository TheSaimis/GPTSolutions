<?php

declare (strict_types = 1);

namespace App\Services;

use App\Services\Metadata\DocxMetadataService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Įkelia Word (.docx) šabloną į templates/{directory}/.
 * addWordDocument(blob docx file, directory) → SUCCESS | FAIL
 */
final class AddWordDocument
{
    private const SUCCESS = 'SUCCESS';
    private const FAIL    = 'FAIL';

    public function __construct(
        private readonly string $projectDir,
        private readonly DocxMetadataService $docxMetadataService,
    ) {}

    /**
     * Išsaugo įkeltą .docx failą į templates/{directory}/
     * ir prideda unikalų metadata lauką documentId.
     *
     * @param UploadedFile $file      Įkeltas .docx failas
     * @param string       $directory Katalogas po templates/ (pvz. "4 Tvarkos" arba "4 Tvarkos/3 Mobingo")
     * @param string       $root      Šakninis katalogas (pvz. "templates")
     * @return 'SUCCESS'|'FAIL'
     */
    public function addWordDocument(UploadedFile $file, string $directory, string $root): array
    {
        $directory = trim(str_replace('\\', '/', $directory));
        if ($directory === '' || $directory === '.') {
            $directory = '';
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['doc', 'docx', 'xls', 'xlsx'], true)) {
            return [
                'status' => self::FAIL,
                'error'  => 'Invalid file type. Only .doc, .docx, .xls, .xlsx are allowed.',
            ];
        }

        try {
            $baseDir = match ($root) {
                'generated' => rtrim($this->projectDir, '/\\') . '/generated',
                'archive' => rtrim($this->projectDir, '/\\') . '/archive',
                'templates' => rtrim($this->projectDir, '/\\') . '/templates',
                default     => throw new \InvalidArgumentException('Invalid root folder'),
            };

            $targetDir = $baseDir . ($directory !== '' ? '/' . $directory : '');

            if (! is_dir($targetDir) && ! mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
                return [
                    'status' => self::FAIL,
                    'error'  => 'Failed to create target directory.',
                ];
            }

            $originalFilename = trim($file->getClientOriginalName());
            if ($originalFilename === '') {
                $originalFilename = 'template_' . date('Ymd_His') . '.' . $ext;
            }

            $filename   = str_replace(['\\', '/'], '_', $originalFilename);
            $targetPath = $targetDir . '/' . $filename;

            $file->move($targetDir, $filename);

            if (! file_exists($targetPath) || ! is_readable($targetPath)) {
                return [
                    'status' => self::FAIL,
                    'error'  => 'Saved file is not readable.',
                ];
            }
        } catch (\Throwable $e) {
            return [
                'status' => self::FAIL,
                'error'  => $e->getMessage(),
            ];
        }

        $metadata = [];

        if (in_array($ext, ['docx', 'xlsx'], true)) {
            try {
                $existing = $this->docxMetadataService->readDocxCustomProperties($targetPath);

                $metadataToEnsure = [
                    'templateId'   => $existing['templateId'] ?? $this->generateUuidV4(),
                    'uploadedAt'   => $existing['uploadedAt'] ?? date('Y-m-d H:i:s'),
                    'originalName' => $existing['originalName'] ?? $filename,
                    'mimeType'     => $existing['mimeType'] ?? $file->getClientMimeType(),
                ];

                $this->docxMetadataService->setDocxCustomProperties($targetPath, $metadataToEnsure);
                $metadata = $this->docxMetadataService->readDocxCustomProperties($targetPath);
            } catch (\Throwable) {
                $metadata = [];
            }
        }

        $directory = trim(str_replace('\\', '/', $directory), '/');

        if ($directory !== '' && str_starts_with($directory, $root . '/')) {
            $directory = substr($directory, strlen($root) + 1);
        }

        return [
            'status' => self::SUCCESS,
            'file'   => [
                'name'     => $filename,
                'type'     => 'file',
                'path'     => ($directory !== '' ? $directory . '/' : '') . $filename,
                'root'     => $root,
                'mimeType' => $file->getClientMimeType(),
                'size'     => filesize($targetPath),
                'metadata' => [
                    'custom' => $metadata,
                ],
            ],
        ];
    }

    /**
     * Sukuria naują katalogą templates/{directory}/.
     *
     * @param string $directory Kelias po templates/ (pvz. "4 Tvarkos" arba "4 Tvarkos/Naujas")
     * @param string $root      Šakninis katalogas (pvz. "templates")
     * @return 'SUCCESS'|'FAIL'
     */
    public function createFolder(string $directory, string $root): string
    {
        $directory = trim(str_replace('\\', '/', $directory));
        if ($directory === '' || $directory === '.') {
            return self::FAIL;
        }

        $templatesDir = rtrim($this->projectDir, '/\\') . '/' . trim($root, '/\\');
        $targetDir    = $templatesDir . '/' . $directory;

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
     * @param string         $directory
     * @param string         $root
     * @return array{
     *   status: 'SUCCESS'|'FAIL',
     *   results: array<array{file: string, status: 'SUCCESS'|'FAIL'}>
     * }
     */
    public function addWordDocumentsBulk(array $files, string $directory, string $root): array
    {
        $results    = [];
        $allSuccess = true;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $filename = $file->getClientOriginalName() ?: 'unknown';
            $result   = $this->addWordDocument($file, $directory, $root);
            $status   = $result['status'] ?? self::FAIL;

            $results[] = [
                'file'   => $filename,
                'status' => $status,
            ];

            if ($status === self::FAIL) {
                $allSuccess = false;
            }
        }

        return [
            'status'  => $allSuccess ? self::SUCCESS : self::FAIL,
            'results' => $results,
        ];
    }

    /**
     * Sugeneruoja UUID v4.
     */
    private function generateUuidV4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
