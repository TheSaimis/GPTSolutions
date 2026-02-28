<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Sukuria Word (.docx) failą iš šablono, pakeičiant žymes duotais duomenimis.
 *
 * Duomenys (data) – atitinka companies lentelės stulpelius:
 *   - directory: string      – šablonų kategorija (pvz. "4 Tvarkos")
 *   - template: string       – šablono failo pavadinimas (pvz. "Tvarka.docx")
 *   - companyName: string    – įmonės pavadinimas
 *   - code: string           – įmonės/instrukcijos kodas
 *   - documentDate: string   – dokumento data
 *   - role: string           – vaidmuo / pareigos
 */
final class CreateFile
{
    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Sukuria Word failą ir grąžina sukurtų failo pilną kelią.
     *
     * @param array{
     *     directory: string,
     *     template: string,
     *     companyName: string,
     *     code: string,
     *     documentDate: string,
     *     role: string
     * } $data
     *
     * @return string Sukurtų failo kelias
     *
     * @throws \InvalidArgumentException Jei trūksta būtinų laukų ar šablonas nerandamas
     */
    public function createWordDocument(array $data): string
    {
        $this->validateData($data);

        $directory    = $data['directory'] ?? '';
        $template     = $data['template'] ?? '';
        $companyName  = $data['companyName'] ?? '';
        $code         = $data['code'] ?? '';
        $documentDate = $data['documentDate'] ?? '';
        $role         = $data['role'] ?? '';

        $templatePath = $this->resolveTemplatePath($directory, $template);
        if ($templatePath === null || !is_readable($templatePath)) {
            throw new \InvalidArgumentException("Šablonas nerastas: {$directory}/{$template}");
        }

        $outputDir = $this->getGeneratedDir() . '/' . $code;
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $baseName   = pathinfo($template, PATHINFO_FILENAME);
        // this will ensure already existing files wont get overwritten
        $outputName = $baseName . '_' . $code . '_' . date('Ymd_His') . '.docx';
        $outputPath = $outputDir . '/' . $outputName;

        $processor = new TemplateProcessor($templatePath);

        $processor->setValue('companyName', $companyName);
        $processor->setValue('code', $code);
        $processor->setValue('documentDate', $documentDate);
        $processor->setValue('role', $role);

        $processor->saveAs($outputPath);

        return $outputPath;
    }

    private function validateData(array $data): void
    {
        $required = ['template', 'companyName', 'code'];
        foreach ($required as $key) {
            if (empty($data[$key]) || !is_string($data[$key])) {
                throw new \InvalidArgumentException("Būtinas laukas \"{$key}\" turi būti ne tuščias string.");
            }
        }
    }

    private function getTemplatesDir(): string
    {
        return $this->projectDir . '/templates';
    }

    private function getGeneratedDir(): string
    {
        return $this->projectDir . '/var/generated';
    }

    private function resolveTemplatePath(string $directory, string $templateFile): ?string
    {
        $base = $this->getTemplatesDir();

        $candidates = [];
        if ($directory !== '') {
            $candidates[] = $base . '/' . $directory . '/' . $templateFile;
        }
        $candidates[] = $base . '/' . $templateFile;

        foreach ($candidates as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
