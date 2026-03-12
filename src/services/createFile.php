<?php

declare (strict_types = 1);

namespace App\Services;

use App\Services\Metadata\DocxMetadataService;
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
 * ${tipas}, ${tipasPilnas}, ${TIPASPILNAS}, ${adresas}, ${vadovas}, ${lytis},
 * ${vadovo} (vadovo kilm.), ${vardo} (vardas kilm.), ${pavardes} (pavardė kilm.),
 * ${varde} (vardas šauksm.), ${pavardeS} (pavardė šauksm.)
 *
 * Savavališki pakeitimai (replacements): objektas arba masyvas porų.
 * Randa šablone ${placeholder} ir pakeičia į nurodytą vertę.
 *
 * Kintamųjų raidžių dydis nesvarbus: ${vadovas}, ${VADOVAS}, ${Vadovas} – visi sutampa.
 */
final class CreateFile
{
    public function __construct(
        private readonly string $projectDir,
        private readonly Namer $namer,
        private readonly DocxMetadataService $docxMetadataService,
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

        $directory    = (string) ($data['directory'] ?? '');
        $template     = (string) ($data['template'] ?? '');
        $companyName  = (string) ($data['kompanija'] ?? $data['companyName'] ?? '');
        $code         = (string) ($data['kodas'] ?? $data['code'] ?? '');
        $documentDate = (string) ($data['data'] ?? $data['documentDate'] ?? '');
        $role         = (string) ($data['role'] ?? '');
        $vardas       = (string) ($data['vardas'] ?? $data['managerFirstName'] ?? '');
        $pavarde      = (string) ($data['pavarde'] ?? $data['managerLastName'] ?? '');
        $tipas        = (string) ($data['tipas'] ?? $data['companyType'] ?? '');
        $tipasPilnas  = (string) ($data['tipasPilnas'] ?? $data['companyType'] ?? '');
        $adresas      = (string) ($data['adresas'] ?? $data['address'] ?? '');

        $companyId   = (string) ($data['companyId'] ?? '');
        $userId      = (string) ($data['userId'] ?? '');
        $userName    = (string) ($data['userName'] ?? $data['firstName'] ?? '');
        $userSurname = (string) ($data['userSurname'] ?? $data['lastName'] ?? '');
        $createdBy   = trim($userName . ' ' . $userSurname);

        if ($tipasPilnas === '') {
            $tipasPilnas = $this->mapTipasPilnas($tipas);
        }

        $templatePath = $this->resolveTemplatePath($directory, $template);
        if ($templatePath === null || ! is_readable($templatePath)) {
            throw new \InvalidArgumentException("Šablonas nerastas: {$directory}/{$template}");
        }

        $companySlug = $this->sanitizeForFilename($companyName) ?: $code;
        $tipasSlug   = $this->sanitizeForFilename($tipas) ?: 'Kita';
        $outputDir   = $this->getGeneratedDir() . '/' . $tipasSlug . '/' . $companySlug;
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $baseName   = pathinfo($template, PATHINFO_FILENAME);
        $outputName = $baseName . '_' . $companySlug . '.docx';
        $outputPath = $outputDir . '/' . $outputName;

        $processor = new TemplateProcessor($templatePath);

        $vadovas = $this->formatManagerFullName(
            $data['managerFirstName'] ?? $data['vardas'] ?? null,
            $data['managerLastName'] ?? $data['pavarde'] ?? null
        );
        $managerType = (string) ($data['managerType'] ?? '');
        $lytis       = trim((string) ($data['managerGender'] ?? $data['lytis'] ?? ''));
        if ($lytis === '') {
            $lytis = $this->resolveGender($managerType);
        }

        $vadovo   = $managerType !== '' ? $this->namer->vadovo($managerType) : '';
        $vardo    = $vardas !== '' ? $this->namer->vardo($vardas, $lytis) : '';
        $pavardes = $pavarde !== '' ? $this->namer->pavardes($pavarde, $lytis) : '';
        $varde    = $vardas !== '' ? $this->namer->vardoSauksmininkas($vardas, $lytis) : '';
        $pavardeS = $pavarde !== '' ? $this->namer->pavardesSauksmininkas($pavarde, $lytis) : '';

        $this->setValueCaseInsensitive($processor, 'kompanija', $companyName);
        $this->setValueCaseInsensitive($processor, 'kodas', $code);
        $this->setValueCaseInsensitive($processor, 'data', $documentDate);
        $this->setValueCaseInsensitive($processor, 'role', $role);
        $this->setValueCaseInsensitive($processor, 'vardas', $vardas);
        $this->setValueCaseInsensitive($processor, 'pavarde', $pavarde);
        $this->setValueCaseInsensitive($processor, 'tipas', $tipas);
        $this->setValueCaseInsensitive($processor, 'tipasPilnas', $tipasPilnas);
        $this->setValueCaseInsensitive($processor, 'adresas', $adresas);
        $this->setValueCaseInsensitive($processor, 'vadovas', $vadovas);
        $this->setValueCaseInsensitive($processor, 'lytis', $lytis);
        $this->setValueCaseInsensitive($processor, 'vadovo', $vadovo);
        $this->setValueCaseInsensitive($processor, 'vardo', $vardo);
        $this->setValueCaseInsensitive($processor, 'pavardes', $pavardes);
        $this->setValueCaseInsensitive($processor, 'varde', $varde);
        $this->setValueCaseInsensitive($processor, 'pavardeS', $pavardeS);
        $this->setValueCaseInsensitive($processor, 'pavardo', $pavardes);
        $this->setValueCaseInsensitive($processor, 'vardes', $vardo);
        $this->setValueCaseInsensitive($processor, 'companyName', $companyName);
        $this->setValueCaseInsensitive($processor, 'code', $code);
        $this->setValueCaseInsensitive($processor, 'documentDate', $documentDate);

        $this->applyReplacements($processor, $data['replacements'] ?? []);

        $processor->saveAs($outputPath);

        $templateMetadata = $this->docxMetadataService->readDocxCustomProperties($templatePath);
        $templateId       = (string) ($templateMetadata['templateId'] ?? '');

        $this->docxMetadataService->setDocxCustomProperties($outputPath, [
            'templateId' => $templateId,
            'documentId' => $this->generateUuidV4(),
            'created'    => $documentDate,
            'createdBy'  => $createdBy,
            'userId'     => $userId,
            'type'       => $tipas,
            'company'    => $companyName,
            'companyId'  => $companyId,
        ]);
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
        if (empty($companyName) || ! is_string($companyName) || trim($companyName) === '') {
            throw new \InvalidArgumentException('Būtinas laukas "kompanija" arba "companyName"');
        }
        if (empty($code) || ! is_string($code) || trim($code) === '') {
            throw new \InvalidArgumentException('Būtinas laukas "kodas" arba "code"');
        }
        if (empty($data['template']) || ! is_string($data['template']) || trim($data['template']) === '') {
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
            'UAB'   => 'Uždaroji akcinė bendrovė',
            'AB'    => 'Akcinė bendrovė',
            'MB'    => 'Mažoji bendrija',
            'IĮ', 'II' => 'Individuali įmonė',
            'IND V', 'INDV', 'IV', 'IND. V.' => 'Individuali veikla',
            'VŠĮ', 'VSĮ', 'VSI' => 'Viešoji įstaiga',
            default => $tipas,
        };
    }
    /**
     * Pavers įmonės pavadinimą į saugų failų sistemos identifikatorių.
     */
    private function sanitizeForFilename(string $name): string
    {
        $s = trim($name);
        $s = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $s) ?? $s;
        $s = preg_replace('/\s+/', '_', trim($s)) ?? $s;
        return $s !== '' ? $s : '';
    }

    private function formatManagerFullName(?string $firstName, ?string $lastName): string
    {
        $parts = array_filter([trim((string) $firstName), trim((string) $lastName)]);
        return implode(' ', $parts);
    }

    private function resolveGender(string $managerType): string
    {
        $type   = mb_strtolower(trim($managerType));
        $female = ['vadovė', 'direktorė'];
        $male   = ['vadovas', 'direktorius'];
        if (in_array($type, $female, true)) {
            return 'Moteris';
        }

        if (in_array($type, $male, true)) {
            return 'Vyras';
        }

        if (str_ends_with($type, 'ė')) {
            return 'Moteris';
        }

        return 'Vyras';
    }

    /**
     * Pritaiko savavališkus pakeitimus iš $replacements.
     *
     * @param array<string, string>|array<int, array{0: string, 1: string}> $replacements
     */
    private function applyReplacements(TemplateProcessor $processor, mixed $replacements): void
    {
        if (! is_array($replacements)) {
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
                $this->setValueCaseInsensitive($processor, $placeholder, $replacement);
            }
        }
    }

    /**
     * Nustato vertę visiems raidžių dydžio variantams – ${vadovas}, ${VADOVAS}, ${Vadovas} sutampa.
     */
    private function setValueCaseInsensitive(TemplateProcessor $processor, string $placeholder, string $value): void
    {
        $variants = array_unique([
            $placeholder,
            mb_strtolower($placeholder, 'UTF-8'),
            mb_strtoupper($placeholder, 'UTF-8'),
            mb_convert_case($placeholder, MB_CASE_TITLE, 'UTF-8'),
        ]);
        foreach ($variants as $v) {
            if ($v !== '') {
                $processor->setValue($v, $value);
            }
        }
    }
    private function generateUuidV4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
