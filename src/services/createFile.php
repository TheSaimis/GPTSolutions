<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Sukuria Word (.docx) failą iš šablono, pakeičiant žymes duotais duomenimis.
 *
 * Tikėtini $data raktai:
 *   - directory: string      – šablonų kategorija (pvz. "4 Tvarkos")
 *   - template: string       – šablono failo pavadinimas (pvz. "Tvarka.docx")
 *   - kompanija: string      – įmonės pavadinimas
 *   - kodas: string          – įmonės/instrukcijos kodas
 *   - data: string           – dokumento data
 *   - role: string           – vaidmuo / pareigos
 *   - vardas: string
 *   - pavarde: string
 *   - tipas: string          – trumpinys (UAB, AB, MB, ĮI, IND V, VŠĮ)
 *
 * Šablone gali naudoti žymes:
 *   ${kompanija}, ${kodas}, ${data}, ${role}, ${vardas}, ${pavarde}, ${tipas},
 *   ${tipasPilnas} arba ${TIPASPILNAS}
 */
final class CreateFile
{
    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Sukuria Word failą ir grąžina sukurto failo pilną kelią.
     *
     * @param array<string, mixed> $data
     * @return string
     */
    public function createWordDocument(array $data): string
    {
        $this->validateData($data);

        $directory    = (string)($data['directory'] ?? '');
        $template     = (string)($data['template'] ?? '');
        $companyName  = (string)($data['kompanija'] ?? '');
        $code         = (string)($data['kodas'] ?? '');
        $documentDate = (string)($data['data'] ?? '');
        $role         = (string)($data['role'] ?? '');
        $vardas       = (string)($data['vardas'] ?? '');
        $pavarde      = (string)($data['pavarde'] ?? '');
        $tipas        = (string)($data['tipas'] ?? '');

        $tipasPilnas = $this->mapTipasPilnas($tipas);

        $templatePath = $this->resolveTemplatePath($directory, $template);
        if ($templatePath === null || !is_readable($templatePath)) {
            throw new \InvalidArgumentException("Šablonas nerastas: {$directory}/{$template}");
        }

        $outputDir = $this->getGeneratedDir() . '/' . $code;
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $baseName   = pathinfo($template, PATHINFO_FILENAME);
        // Užtikrina, kad neperrašytų jau esančių failų
        $outputName = $baseName . '_' . $code . '_' . date('Ymd_His') . '.docx';
        $outputPath = $outputDir . '/' . $outputName;

        $processor = new TemplateProcessor($templatePath);

        // Pagrindiniai laukai
        $processor->setValue('kompanija', $companyName);
        $processor->setValue('kodas', $code);
        $processor->setValue('data', $documentDate);
        $processor->setValue('role', $role);

        // Papildomi laukai
        $processor->setValue('vardas', $vardas);
        $processor->setValue('pavarde', $pavarde);

        // Tipas (trumpinys) + pilnas pavadinimas
        $processor->setValue('tipas', $tipas);

        // Kad veiktų šablonuose, kur žymė yra ${tipasPilnas} IR kur yra ${TIPASPILNAS}
        $processor->setValue('tipasPilnas', $tipasPilnas);
        $processor->setValue('TIPASPILNAS', $tipasPilnas);

        $processor->saveAs($outputPath);

        return $outputPath;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateData(array $data): void
    {
        $required = ['template', 'kompanija', 'kodas'];

        foreach ($required as $key) {
            if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '') {
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

    private function mapTipasPilnas(string $tipas): string
    {
        $t = mb_strtoupper(trim($tipas));

        // normalizuojam taškus ir dvigubus tarpus
        $t = str_replace('.', '', $t);
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        return match ($t) {
            'UAB' => 'Uždaroji akcinė bendrovė',
            'AB'  => 'Akcinė bendrovė',
            'MB'  => 'Mažoji bendrija',

            // kartais rašo II vietoje ĮI
            'IĮ', 'II' => 'Individuali įmonė',

            // skirtingi įvedimai
            'IND V', 'INDV', 'IV' => 'Individuali veikla',

            // kartais be diakritikų
            'VŠĮ', 'VSĮ', 'VSI' => 'Viešoji įstaiga',

            default => $tipas,
        };
    }
}