<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Sukuria Word (.docx) failą iš šablono, pakeičiant žymes duotais duomenimis.
 *
 * Tikėtini $data raktai (lietuviškai arba anglų):
 *   - directory, template – šablono kelias
 *   - kompanija / companyName – įmonės pavadinimas
 *   - kodas / code – įmonės kodas
 *   - data / documentDate – dokumento data
 *   - role – pareigos
 *   - vardas / managerFirstName – vadovo vardas
 *   - pavarde / managerLastName – vadovo pavardė
 *   - tipas / companyType – įmonės tipas (UAB, AB, MB, ĮI, IND V, VŠĮ)
 *   - tipasPilnas / category – pilna kategorija
 *   - adresas / address – adresas
 *   - managerType – vadovo tipas (lyčiai: vadovas/vadovė, direktorius/direktorė)
 *
 * Šablone: ${kompanija}, ${kodas}, ${data}, ${role}, ${vardas}, ${pavarde},
 * ${tipas}, ${tipasPilnas}, ${TIPASPILNAS}, ${adresas}, ${vadovas}, ${lytis}
 *
 * Savavališki pakeitimai (replacements): objektas arba masyvas porų.
 * Randa šablone ${placeholder} ir pakeičia į nurodytą vertę.
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
        $companyName  = (string)($data['kompanija'] ?? $data['companyName'] ?? '');
        $code         = (string)($data['kodas'] ?? $data['code'] ?? '');
        $documentDate = (string)($data['data'] ?? $data['documentDate'] ?? '');
        $role         = (string)($data['role'] ?? '');
        $vardas       = (string)($data['vardas'] ?? $data['managerFirstName'] ?? '');
        $pavarde      = (string)($data['pavarde'] ?? $data['managerLastName'] ?? '');
        $tipas        = (string)($data['tipas'] ?? $data['companyType'] ?? '');
        $tipasPilnas  = (string)($data['tipasPilnas'] ?? $data['category'] ?? '');
        $adresas      = (string)($data['adresas'] ?? $data['address'] ?? '');

        if ($tipasPilnas === '') {
            $tipasPilnas = $this->mapTipasPilnas($tipas);
        }

        $templatePath = $this->resolveTemplatePath($directory, $template);
        if ($templatePath === null || !is_readable($templatePath)) {
            throw new \InvalidArgumentException("Šablonas nerastas: {$directory}/{$template}");
        }

        $outputDir = $this->getGeneratedDir() . '/' . $code;
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $baseName   = pathinfo($template, PATHINFO_FILENAME);
        $outputName = $baseName . '_' . $code . '_' . date('Ymd_His') . '.docx';
        $outputPath = $outputDir . '/' . $outputName;

        $processor = new TemplateProcessor($templatePath);

        $processor->setValue('kompanija', $companyName);
        $processor->setValue('kodas', $code);
        $processor->setValue('data', $documentDate);
        $processor->setValue('role', $role);
        $processor->setValue('vardas', $vardas);
        $processor->setValue('pavarde', $pavarde);
        $processor->setValue('tipas', $tipas);
        $processor->setValue('tipasPilnas', $tipasPilnas);
        $processor->setValue('TIPASPILNAS', $tipasPilnas);
        $processor->setValue('adresas', $adresas);
        $processor->setValue('vadovas', $this->formatManagerFullName(
            $data['managerFirstName'] ?? $data['vardas'] ?? null,
            $data['managerLastName'] ?? $data['pavarde'] ?? null
        ));
        $processor->setValue('lytis', $this->resolveGender((string)($data['managerType'] ?? '')));

        $processor->setValue('companyName', $companyName);
        $processor->setValue('code', $code);
        $processor->setValue('documentDate', $documentDate);

        $this->applyReplacements($processor, $data['replacements'] ?? []);

        $processor->saveAs($outputPath);

        return $outputPath;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateData(array $data): void
    {
        $companyName = $data['kompanija'] ?? $data['companyName'] ?? '';
        $code        = $data['kodas'] ?? $data['code'] ?? '';
        $required    = ['template'];
        if (empty($companyName) || !is_string($companyName) || trim($companyName) === '') {
            throw new \InvalidArgumentException('Būtinas laukas "kompanija" arba "companyName"');
        }
        if (empty($code) || !is_string($code) || trim($code) === '') {
            throw new \InvalidArgumentException('Būtinas laukas "kodas" arba "code"');
        }
        if (empty($data['template']) || !is_string($data['template']) || trim($data['template']) === '') {
            throw new \InvalidArgumentException('Būtinas laukas "template"');
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
        $t = str_replace('.', '', $t);
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        return match ($t) {
            'UAB' => 'Uždaroji akcinė bendrovė',
            'AB'  => 'Akcinė bendrovė',
            'MB'  => 'Mažoji bendrija',
            'IĮ', 'II' => 'Individuali įmonė',
            'IND V', 'INDV', 'IV' => 'Individuali veikla',
            'VŠĮ', 'VSĮ', 'VSI' => 'Viešoji įstaiga',
            default => $tipas,
        };
    }

    private function formatManagerFullName(?string $firstName, ?string $lastName): string
    {
        $parts = array_filter([trim((string) $firstName), trim((string) $lastName)]);
        return implode(' ', $parts);
    }

    private function resolveGender(string $managerType): string
    {
        $type = mb_strtolower(trim($managerType));
        $female = ['vadovė', 'direktorė'];
        $male   = ['vadovas', 'direktorius'];
        if (in_array($type, $female, true)) return 'Moteris';
        if (in_array($type, $male, true)) return 'Vyras';
        if (str_ends_with($type, 'ė')) return 'Moteris';
        return 'Vyras';
    }

    /**
     * Pritaiko savavališkus pakeitimus iš $replacements.
     *
     * @param array<string, string>|array<int, array{0: string, 1: string}> $replacements
     */
    private function applyReplacements(TemplateProcessor $processor, mixed $replacements): void
    {
        if (!is_array($replacements)) {
            return;
        }

        $pairs = [];
        foreach ($replacements as $key => $value) {
            if (is_int($key) && is_array($value) && count($value) >= 2) {
                $pairs[(string) $value[0]] = (string) $value[1];
            } elseif (is_string($key)) {
                $pairs[$key] = (string) $value;
            }
        }

        foreach ($pairs as $placeholder => $replacement) {
            if (trim($placeholder) !== '') {
                $processor->setValue($placeholder, $replacement);
            }
        }
    }
}
