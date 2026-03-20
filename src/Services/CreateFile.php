<?php

declare (strict_types = 1);

namespace App\Services;

use App\Services\Metadata\DocxMetadataService;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
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
    public function createWordDocument(array $data, ?string $name = null): string
    {
        $this->validateData($data);

        $template = (string) ($data['template'] ?? '');
        $ext = strtolower(pathinfo($template, PATHINFO_EXTENSION));

        if (in_array($ext, ['xls', 'xlsx'], true)) {
            return $this->createSpreadsheetDocument($data, $name);
        }

        return $this->createDocxDocument($data, $name);
    }

    private function createDocxDocument(array $data, ?string $name = null): string
    {
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

        $companySlug = $this->sanitizeForFilename($companyName) ?: ($code !== '' ? $code : 'be_kodo');
        $tipasSlug   = $this->sanitizeForFilename($tipas) ?: 'Kita';
        $outputDir   = $this->getGeneratedDir() . '/' . $tipasSlug . '/' . $companySlug;
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $baseName   = pathinfo($template, PATHINFO_FILENAME);
        $outputName = $name ?? $baseName . '_' . $companySlug . '.docx';
        $outputPath = $outputDir . '/' . $outputName;

        $lang = $this->detectLanguage($template);

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

        if ($lang === 'LT') {
            $vadovo   = $managerType !== '' ? $this->namer->vadovo($managerType) : '';
            $vardo    = $vardas !== '' ? $this->namer->vardo($vardas, $lytis) : '';
            $pavardes = $pavarde !== '' ? $this->namer->pavardes($pavarde, $lytis) : '';
            $varde    = $vardas !== '' ? $this->namer->vardoSauksmininkas($vardas, $lytis) : '';
            $pavardeS = $pavarde !== '' ? $this->namer->pavardesSauksmininkas($pavarde, $lytis) : '';

            $this->setValueCaseInsensitive($processor, 'role', $role);
            $this->setValueCaseInsensitive($processor, 'tipas', $tipas);
            $this->setValueCaseInsensitive($processor, 'tipasPilnas', $tipasPilnas);
            $this->setValueCaseInsensitive($processor, 'lytis', $lytis);
            $this->setValueCaseInsensitive($processor, 'vadovo', $vadovo);
            $this->setValueCaseInsensitive($processor, 'vardo', $vardo);
            $this->setValueCaseInsensitive($processor, 'pavardes', $pavardes);
            $this->setValueCaseInsensitive($processor, 'varde', $varde);
            $this->setValueCaseInsensitive($processor, 'pavardeS', $pavardeS);
            $this->setValueCaseInsensitive($processor, 'pavardo', $pavardes);
            $this->setValueCaseInsensitive($processor, 'vardes', $vardo);
        } else {
            $roleTr        = $this->translateRole($managerType, $lang);
            $tipasTr       = $this->translateTipas($tipas, $lang);
            $tipasPilnasTr = $this->translateTipasPilnas($tipas, $tipasPilnas, $lang);
            $lytisTr       = $this->translateGender($lytis, $lang);

            $this->setValueCaseInsensitive($processor, 'role', $roleTr);
            $this->setValueCaseInsensitive($processor, 'tipas', $tipasTr);
            $this->setValueCaseInsensitive($processor, 'tipasPilnas', $tipasPilnasTr);
            $this->setValueCaseInsensitive($processor, 'lytis', $lytisTr);
            $this->setValueCaseInsensitive($processor, 'vadovo', $roleTr);
            $this->setValueCaseInsensitive($processor, 'vardo', $vardas);
            $this->setValueCaseInsensitive($processor, 'pavardes', $pavarde);
            $this->setValueCaseInsensitive($processor, 'varde', $vardas);
            $this->setValueCaseInsensitive($processor, 'pavardeS', $pavarde);
            $this->setValueCaseInsensitive($processor, 'pavardo', $pavarde);
            $this->setValueCaseInsensitive($processor, 'vardes', $vardas);
        }

        $this->setValueCaseInsensitive($processor, 'kompanija', $companyName);
        $this->setValueCaseInsensitive($processor, 'kodas', $code);
        $this->setValueCaseInsensitive($processor, 'data', $documentDate);
        $this->setValueCaseInsensitive($processor, 'vardas', $vardas);
        $this->setValueCaseInsensitive($processor, 'pavarde', $pavarde);
        $this->setValueCaseInsensitive($processor, 'adresas', $adresas);
        $this->setValueCaseInsensitive($processor, 'vadovas', $vadovas);
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
            'created'    => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'modifiedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'createdBy'  => $createdBy,
            'userId'     => $userId,
            'type'       => $tipas,
            'company'    => $companyName,
            'companyId'  => $companyId,
            'language'   => $lang,
        ]);
        return $outputPath;
    }

    private function createSpreadsheetDocument(array $data, ?string $name = null): string
    {
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

        $ext = strtolower(pathinfo($template, PATHINFO_EXTENSION));
        $baseName   = pathinfo($template, PATHINFO_FILENAME);
        $outputName = $name ?? $baseName . '_' . $companySlug . '.' . $ext;
        $outputPath = $outputDir . '/' . $outputName;

        $lang = $this->detectLanguage($template);
        $managerType = (string) ($data['managerType'] ?? '');
        $lytis       = trim((string) ($data['managerGender'] ?? $data['lytis'] ?? ''));
        if ($lytis === '') {
            $lytis = $this->resolveGender($managerType);
        }

        $vadovas = $this->formatManagerFullName(
            $data['managerFirstName'] ?? $data['vardas'] ?? null,
            $data['managerLastName'] ?? $data['pavarde'] ?? null
        );

        $replacements = [
            'kompanija'    => $companyName,
            'companyName'  => $companyName,
            'kodas'        => $code,
            'code'         => $code,
            'data'         => $documentDate,
            'documentDate' => $documentDate,
            'role'         => $role,
            'vardas'       => $vardas,
            'pavarde'      => $pavarde,
            'tipas'        => $tipas,
            'tipasPilnas'  => $tipasPilnas,
            'adresas'      => $adresas,
            'vadovas'      => $vadovas,
            'lytis'        => $lytis,
        ];

        if ($lang === 'LT') {
            $vadovo   = $managerType !== '' ? $this->namer->vadovo($managerType) : '';
            $vardo    = $vardas !== '' ? $this->namer->vardo($vardas, $lytis) : '';
            $pavardes = $pavarde !== '' ? $this->namer->pavardes($pavarde, $lytis) : '';
            $varde    = $vardas !== '' ? $this->namer->vardoSauksmininkas($vardas, $lytis) : '';
            $pavardeS = $pavarde !== '' ? $this->namer->pavardesSauksmininkas($pavarde, $lytis) : '';

            $replacements += [
                'vadovo'   => $vadovo,
                'vardo'    => $vardo,
                'pavardes' => $pavardes,
                'varde'    => $varde,
                'pavardeS' => $pavardeS,
                'pavardo'  => $pavardes,
                'vardes'   => $vardo,
            ];
        }

        $customReplacements = $data['replacements'] ?? [];
        if (is_array($customReplacements)) {
            foreach ($customReplacements as $key => $value) {
                if (is_int($key) && is_array($value) && count($value) >= 2) {
                    $replacements[(string) $value[0]] = (string) $value[1];
                } elseif (is_string($key)) {
                    $replacements[$key] = (string) $value;
                }
            }
        }

        $spreadsheet = SpreadsheetIOFactory::load($templatePath);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $cellValue = $cell->getValue();
                    if (! is_string($cellValue) || ! str_contains($cellValue, '${')) {
                        continue;
                    }

                    $newValue = $cellValue;
                    foreach ($replacements as $placeholder => $replacement) {
                        $variants = array_unique([
                            $placeholder,
                            mb_strtolower($placeholder, 'UTF-8'),
                            mb_strtoupper($placeholder, 'UTF-8'),
                            mb_convert_case($placeholder, MB_CASE_TITLE, 'UTF-8'),
                        ]);
                        foreach ($variants as $v) {
                            $newValue = str_replace('${' . $v . '}', $replacement, $newValue);
                        }
                    }

                    if ($newValue !== $cellValue) {
                        $cell->setValue($newValue);
                    }
                }
            }
        }

        $writer = new XlsxWriter($spreadsheet);
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();

        return $outputPath;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateData(array $data): void
    {
        $companyName = $data['kompanija'] ?? $data['companyName'] ?? '';
        if (empty($companyName) || ! is_string($companyName) || trim($companyName) === '') {
            throw new \InvalidArgumentException('Būtinas laukas "kompanija" arba "companyName"');
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
        return $this->projectDir . '/generated';
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
     * Jei kintamasis visiškai didžiosiomis (VADOVAS) – vertė taip pat didžiosiomis.
     * Jei tik pirmoji didžioji (Vadovas) – vertė lieka normali.
     */
    private function setValueCaseInsensitive(TemplateProcessor $processor, string $placeholder, string $value): void
    {
        $lower = mb_strtolower($placeholder, 'UTF-8');
        $upper = mb_strtoupper($placeholder, 'UTF-8');
        $title = mb_convert_case($placeholder, MB_CASE_TITLE, 'UTF-8');

        $variants = [
            $upper => mb_strtoupper($value, 'UTF-8'),
            $lower => mb_strtolower($value, 'UTF-8'),
            $title => $value,
        ];
        if ($placeholder !== $upper && $placeholder !== $lower && $placeholder !== $title) {
            $variants[$placeholder] = $value;
        }

        foreach ($variants as $v => $val) {
            if ($v !== '') {
                $processor->setValue($v, $val);
            }
        }
    }
    private function detectLanguage(string $templateFilename): string
    {
        $baseName = pathinfo($templateFilename, PATHINFO_FILENAME);

        if (preg_match('/\s(RU|EN)$/i', $baseName, $matches)) {
            return mb_strtoupper($matches[1]);
        }

        return 'LT';
    }

    private function translateRole(string $managerType, string $lang): string
    {
        $type = mb_strtolower(trim($managerType));

        if ($lang === 'RU') {
            return match ($type) {
                'direktorius', 'direktorė'                          => 'Директор',
                'vadovas', 'vadovė'                                 => 'Руководитель',
                'generalinis direktorius', 'generalinė direktorė'   => 'Генеральный директор',
                'pirmininkas', 'pirmininkė'                         => 'Председатель',
                'prezidentas', 'prezidentė'                         => 'Президент',
                'administratorius', 'administratorė'                => 'Администратор',
                'savininkas', 'savininkė'                           => 'Владелец',
                default                                             => $managerType,
            };
        }

        return match ($type) {
            'direktorius', 'direktorė'                          => 'Director',
            'vadovas', 'vadovė'                                 => 'Manager',
            'generalinis direktorius', 'generalinė direktorė'   => 'General Director',
            'pirmininkas', 'pirmininkė'                         => 'Chairman',
            'prezidentas', 'prezidentė'                         => 'President',
            'administratorius', 'administratorė'                => 'Administrator',
            'savininkas', 'savininkė'                           => 'Owner',
            default                                             => $managerType,
        };
    }

    private function translateTipas(string $tipas, string $lang): string
    {
        $t = mb_strtoupper(trim($tipas));
        $t = str_replace('.', '', $t);
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        if ($lang === 'RU') {
            return match ($t) {
                'UAB'                              => 'ЗАО',
                'AB'                               => 'АО',
                'MB'                               => 'МТ',
                'IĮ', 'II'                        => 'ИП',
                'IND V', 'INDV', 'IV', 'IND. V.' => 'ИД',
                'VŠĮ', 'VSĮ', 'VSI'              => 'ГУ',
                default                            => $tipas,
            };
        }

        return match ($t) {
            'UAB'                              => 'LLC',
            'AB'                               => 'JSC',
            'MB'                               => 'SP',
            'IĮ', 'II'                        => 'IE',
            'IND V', 'INDV', 'IV', 'IND. V.' => 'IA',
            'VŠĮ', 'VSĮ', 'VSI'              => 'PI',
            default                            => $tipas,
        };
    }

    private function translateTipasPilnas(string $tipas, string $tipasPilnas, string $lang): string
    {
        $t = mb_strtoupper(trim($tipas));
        $t = str_replace('.', '', $t);
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        if ($lang === 'RU') {
            return match ($t) {
                'UAB'                              => 'Закрытое акционерное общество',
                'AB'                               => 'Акционерное общество',
                'MB'                               => 'Малое товарищество',
                'IĮ', 'II'                        => 'Индивидуальное предприятие',
                'IND V', 'INDV', 'IV', 'IND. V.' => 'Индивидуальная деятельность',
                'VŠĮ', 'VSĮ', 'VSI'              => 'Государственное учреждение',
                default                            => $tipasPilnas,
            };
        }

        return match ($t) {
            'UAB'                              => 'Private Limited Liability Company',
            'AB'                               => 'Joint Stock Company',
            'MB'                               => 'Small Partnership',
            'IĮ', 'II'                        => 'Individual Enterprise',
            'IND V', 'INDV', 'IV', 'IND. V.' => 'Individual Activity',
            'VŠĮ', 'VSĮ', 'VSI'              => 'Public Institution',
            default                            => $tipasPilnas,
        };
    }

    private function translateGender(string $lytis, string $lang): string
    {
        if ($lang === 'RU') {
            return match (mb_strtolower(trim($lytis))) {
                'vyras'   => 'Мужской',
                'moteris' => 'Женский',
                default   => $lytis,
            };
        }

        return match (mb_strtolower(trim($lytis))) {
            'vyras'   => 'Male',
            'moteris' => 'Female',
            default   => $lytis,
        };
    }

    private function generateUuidV4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
