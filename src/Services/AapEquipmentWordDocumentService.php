<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\AapEquipmentWordTemplate;
use App\Entity\CompanyRequisite;
use App\Entity\Equipment;
use App\Repository\AapEquipmentWordTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

/**
 * Word šablonai „AAP sąrašas“ ir „AAP kortelės + žiniaraščiai“ pagal
 * otherTemplates/aap-korteles-ziniarasciai/*.docx (arba .doc → LibreOffice).
 *
 * Sąrašas (pasirinktinai): lentelė su ${pareigybe}/${pareigybes}, ${priemones}, ${terminas}, ${eilNr} ir cloneRow;
 *   jei lentelės nėra — užpildoma tik ${sarasas_turinys} arba ${sarasas_duomenys} arba ${aap_sarasas} (laisvas tekstas).
 * Be DB grupių — viena eilutė vienam darbuotojų tipui; su grupėmis — viena eilutė vienai grupei.
 * Kelios reikšmės langelyje — \\n (Word lūžis per PhpWord).
 * Kortelės: ${pareigybes} + lentelė ${priemones}, ${terminas}, ${kiekis}, ${vnt}, ${pagrindas}, ${eilNr} (eilės Nr. 1, 2, 3… — šablone rašyti be #1, klonavimas prideda) arba ${korteles_turinys}/${aap_korteles}.
 * Po lentelės generavimo „Pagrindas išduoti“ stulpelis su tuo pačiu tekstu visose eilutėse automatiškai sujungiamas vertikaliai (w:vMerge).
 * Įmonės rekvizitai ir bendri šablono laukai užpildomi per CreateFile (tarpinis .docx saugomas templates/_aap_temp/).
 */
final class AapEquipmentWordDocumentService
{
    public const OUTPUT_SARASAS = 'sarasas';

    public const OUTPUT_KORTELES = 'korteles';

    private const TEMPLATE_SARASAS_DOCX = 'templates/otherTemplates/aap-korteles-ziniarasciai/sarasas-aap.docx';

    private const TEMPLATE_SARASAS_DOC = 'templates/otherTemplates/aap-korteles-ziniarasciai/sarasas-aap.doc';

    private const TEMPLATE_KORTELES_DOCX = 'templates/otherTemplates/aap-korteles-ziniarasciai/korteles-ziniarasciai.docx';

    private const TEMPLATE_KORTELES_DOC = 'templates/otherTemplates/aap-korteles-ziniarasciai/korteles-ziniarasciai.doc';

    /** Kelios reikšmės viename lentelės langelyje — TemplateProcessor paverčia į Word eilučių lūžius. */
    private const CELL_LIST_SEPARATOR = "\n";

    private const OOXML_W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Stulpelio „Pagrindas išduoti“ aptikimui (fiksuotas šablono tekstas visose klonuotose eilutėse). */
    private const PAGRINDAS_MERGE_SNIPPET_A = 'vadovaujantis';

    private const PAGRINDAS_MERGE_SNIPPET_B = 'nemokamai';

    public function __construct(
        private readonly string $projectDir,
        private readonly CreateEquipmentDocument $createEquipmentDocument,
        private readonly ConvertDocToDocx $convertDocToDocx,
        private readonly EntityManagerInterface $em,
        private readonly AapEquipmentWordTemplateRepository $aapEquipmentWordTemplateRepository,
        private readonly CreateFile $createFile,
    ) {}

    private function normalizeAapLocale(?string $raw): string
    {
        $l = mb_strtolower(trim((string) $raw));

        return in_array($l, ['en', 'ru', 'lt'], true) ? $l : 'lt';
    }

    /**
     * @param array<string, mixed> $eq
     */
    private function localizedEquipmentName(array $eq, string $documentLocale): string
    {
        $l = $this->normalizeAapLocale($documentLocale);
        if ($l === 'en') {
            $t = trim((string) ($eq['nameEn'] ?? ''));

            return $t !== '' ? $t : (string) ($eq['name'] ?? '');
        }
        if ($l === 'ru') {
            $t = trim((string) ($eq['nameRu'] ?? ''));

            return $t !== '' ? $t : (string) ($eq['name'] ?? '');
        }

        return (string) ($eq['name'] ?? '');
    }

    /**
     * @param array<string, mixed> $eq
     */
    private function localizedEquipmentExpiration(array $eq, string $documentLocale): string
    {
        $l = $this->normalizeAapLocale($documentLocale);
        if ($l === 'en') {
            $t = trim((string) ($eq['expirationDateEn'] ?? ''));

            return $t !== '' ? $t : (string) ($eq['expirationDate'] ?? '');
        }
        if ($l === 'ru') {
            $t = trim((string) ($eq['expirationDateRu'] ?? ''));

            return $t !== '' ? $t : (string) ($eq['expirationDate'] ?? '');
        }

        return (string) ($eq['expirationDate'] ?? '');
    }

    /**
     * @param self::OUTPUT_* $kind
     */
    private function documentLanguageUpper(string $documentLocale): string
    {
        return match ($this->normalizeAapLocale($documentLocale)) {
            'en' => 'EN',
            'ru' => 'RU',
            default => 'LT',
        };
    }

    private function tryResolveFilesystemTemplate(string $kind, string $locale): ?string
    {
        [$docxRel, $docRel] = $kind === self::OUTPUT_SARASAS
            ? [self::TEMPLATE_SARASAS_DOCX, self::TEMPLATE_SARASAS_DOC]
            : [self::TEMPLATE_KORTELES_DOCX, self::TEMPLATE_KORTELES_DOC];

        $dir = pathinfo($docxRel, PATHINFO_DIRNAME);
        $base = pathinfo($docxRel, PATHINFO_FILENAME);
        $ext = pathinfo($docxRel, PATHINFO_EXTENSION);
        $suffix = $locale === 'en' ? ' EN' : ($locale === 'ru' ? ' RU' : '');
        $tryDocx = $this->projectDir . '/' . $dir . '/' . $base . $suffix . '.' . $ext;
        if (is_file($tryDocx) && is_readable($tryDocx)) {
            return $tryDocx;
        }
        if ($suffix !== '') {
            $fallbackDocx = $this->projectDir . '/' . $docxRel;
            if (is_file($fallbackDocx) && is_readable($fallbackDocx)) {
                return $fallbackDocx;
            }
        } else {
            $fallbackDocx = $this->projectDir . '/' . $docxRel;
            if (is_file($fallbackDocx) && is_readable($fallbackDocx)) {
                return $fallbackDocx;
            }
        }

        $docDir = pathinfo($docRel, PATHINFO_DIRNAME);
        $docBase = pathinfo($docRel, PATHINFO_FILENAME);
        $docExt = pathinfo($docRel, PATHINFO_EXTENSION);
        $tryDoc = $this->projectDir . '/' . $docDir . '/' . $docBase . $suffix . '.' . $docExt;
        if (is_file($tryDoc) && is_readable($tryDoc)) {
            return $this->convertDocToDocx->ensureDocxForTemplate($tryDoc);
        }
        if ($suffix !== '') {
            $fallbackDoc = $this->projectDir . '/' . $docRel;
            if (is_file($fallbackDoc) && is_readable($fallbackDoc)) {
                return $this->convertDocToDocx->ensureDocxForTemplate($fallbackDoc);
            }
        } else {
            $fallbackDoc = $this->projectDir . '/' . $docRel;
            if (is_file($fallbackDoc) && is_readable($fallbackDoc)) {
                return $this->convertDocToDocx->ensureDocxForTemplate($fallbackDoc);
            }
        }

        return null;
    }

    /**
     * Išvalo iš disko iš DB išmaterializuotus .docx (po įkėlimo / trynimo).
     */
    public function clearMaterializedDbTemplates(?string $kind = null): void
    {
        $dir = $this->projectDir . '/var/aap-db-templates';
        if (! is_dir($dir)) {
            return;
        }
        $pattern = $kind !== null
            ? $dir . '/' . preg_quote($kind, '/') . '_*.docx'
            : $dir . '/*.docx';
        foreach (glob($pattern) ?: [] as $file) {
            @unlink($file);
        }
    }

    public function hasFilesystemTemplate(string $kind, ?string $locale = null): bool
    {
        $loc = $this->normalizeAapLocale($locale ?? 'lt');

        return $this->tryResolveFilesystemTemplate($kind, $loc) !== null;
    }

    /**
     * @param list<self::OUTPUT_*> $outputs
     * @param string|null         $kortelesPagrindasOverride ne null ir ne tuščia — perrašo ${pagrindas} tik kortelių dokumente (vienkartinis generavimas)
     *
     * @return array{path: string, filename: string, mime: string}
     */
    public function generate(int $companyId, array $outputs, ?string $kortelesPagrindasOverride = null, ?string $documentLocale = null): array
    {
        $documentLocale = $this->normalizeAapLocale($documentLocale ?? 'lt');
        $normalized = [];
        foreach ($outputs as $o) {
            $o = is_string($o) ? trim($o) : '';
            if ($o === self::OUTPUT_SARASAS || $o === self::OUTPUT_KORTELES) {
                $normalized[$o] = true;
            }
        }
        $list = array_keys($normalized);
        if ($list === []) {
            throw new \InvalidArgumentException('Pasirinkite bent vieną dokumentą (sarasas arba korteles).');
        }

        /** @var CompanyRequisite|null $company */
        $company = $this->em->getRepository(CompanyRequisite::class)->find($companyId);
        if (! $company instanceof CompanyRequisite) {
            throw new \InvalidArgumentException('Imone nerasta');
        }

        $payload = $this->createEquipmentDocument->buildDataByCompanyId($companyId);
        $tableRows = $this->buildEquipmentTableRows($payload, $documentLocale);

        if (count($list) === 1) {
            return $this->singleOutput($list[0], $company, $tableRows, $payload, $kortelesPagrindasOverride, $documentLocale);
        }

        $paths = [];
        foreach ($list as $kind) {
            $paths[$kind] = $this->renderToTempPath(
                $kind,
                $company,
                $tableRows,
                $payload,
                $kind === self::OUTPUT_KORTELES ? $kortelesPagrindasOverride : null,
                $documentLocale
            );
        }

        $zipDir = $this->resolveGeneratedAbsoluteOutputDir($company);
        if (! is_dir($zipDir) && ! mkdir($zipDir, 0775, true) && ! is_dir($zipDir)) {
            throw new \RuntimeException('Nepavyko sukurti katalogo: ' . $zipDir);
        }

        $zipLoc = $documentLocale !== 'lt' ? '_' . mb_strtoupper($documentLocale) : '';
        $zipPath = $zipDir . '/AAP_dokumentai' . $zipLoc . '_' . date('Ymd_His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            foreach ($paths as $p) {
                @unlink($p);
            }
            throw new \RuntimeException('Nepavyko sukurti ZIP archyvo');
        }

        $zipInnerSuffix = $documentLocale !== 'lt' ? '_' . mb_strtoupper($documentLocale) : '';
        if (isset($paths[self::OUTPUT_SARASAS])) {
            $zip->addFile($paths[self::OUTPUT_SARASAS], 'AAP_sarasas' . $zipInnerSuffix . '.docx');
        }
        if (isset($paths[self::OUTPUT_KORTELES])) {
            $zip->addFile($paths[self::OUTPUT_KORTELES], 'AAP_korteles_ziniarasciai' . $zipInnerSuffix . '.docx');
        }
        $zip->close();

        foreach ($paths as $p) {
            @unlink($p);
        }

        return [
            'path' => $zipPath,
            'filename' => basename($zipPath),
            'mime' => 'application/zip',
        ];
    }

    /**
     * @param list<self::OUTPUT_*> $outputs
     *
     * @return array{path: string, filename: string, mime: string}
     */
    private function singleOutput(string $kind, CompanyRequisite $company, array $tableRows, array $payload, ?string $kortelesPagrindasOverride, string $documentLocale): array
    {
        $path = $this->renderToFinalPath(
            $kind,
            $company,
            $tableRows,
            $payload,
            $kind === self::OUTPUT_KORTELES ? $kortelesPagrindasOverride : null,
            $documentLocale
        );

        return [
            'path' => $path,
            'filename' => basename($path),
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{pareigybe: string, priemones: string, terminas: string, unitOfMeasurement: string, kiekis: string}>
     */
    private function buildEquipmentTableRows(array $payload, string $documentLocale = 'lt'): array
    {
        $groups = $payload['groups'] ?? null;
        if (is_array($groups) && $groups !== []) {
            return $this->buildEquipmentTableRowsFromGroups($groups, $documentLocale);
        }

        $rows = [];
        foreach ($payload['workers'] as $w) {
            if (! is_array($w)) {
                continue;
            }
            $name = trim((string) ($w['workerName'] ?? ''));
            $eqList = $w['equipment'] ?? [];
            if (! is_array($eqList) || $eqList === []) {
                $rows[] = [
                    'pareigybe' => $name !== '' ? $name : '-',
                    'priemones' => '-',
                    'terminas' => '-',
                    'unitOfMeasurement' => 'vnt',
                    'kiekis' => '-',
                ];

                continue;
            }
            // Viena lentelės eilutė vienam darbuotojų tipui: visos priemonės ir terminai toje pačioje eilutėje.
            $priemonesParts = [];
            $terminasParts = [];
            $kiekisParts = [];
            $units = [];
            foreach ($eqList as $eq) {
                if (! is_array($eq)) {
                    continue;
                }
                $priemonesParts[] = trim($this->localizedEquipmentName($eq, $documentLocale)) ?: '-';
                $terminasParts[] = trim($this->localizedEquipmentExpiration($eq, $documentLocale)) ?: '-';
                $kiekisParts[] = (string) Equipment::normalizeDocumentQuantity($eq['quantity'] ?? 1);
                $unit = trim((string) ($eq['unitOfMeasurement'] ?? 'vnt'));
                $units[] = $unit !== '' ? Equipment::normalizeUnitOfMeasurement($unit) : 'vnt';
            }
            $rows[] = [
                'pareigybe' => $name !== '' ? $name : '-',
                'priemones' => $priemonesParts === [] ? '-' : implode(self::CELL_LIST_SEPARATOR, $priemonesParts),
                'terminas' => $terminasParts === [] ? '-' : implode(self::CELL_LIST_SEPARATOR, $terminasParts),
                'unitOfMeasurement' => $units === [] ? 'vnt' : $units[0],
                'kiekis' => $kiekisParts === [] ? '-' : implode(self::CELL_LIST_SEPARATOR, $kiekisParts),
            ];
        }

        if ($rows === []) {
            $rows[] = [
                'pareigybe' => '-',
                'priemones' => '-',
                'terminas' => '-',
                'unitOfMeasurement' => 'vnt',
                'kiekis' => '-',
            ];
        }

        return $rows;
    }

    /**
     * Viena lentelės eilutė vienai grupei: visi grupės darbuotojai, visos priemonės ir terminai toje pačioje eilutėje.
     *
     * @param list<array<string, mixed>> $groups
     *
     * @return list<array{pareigybe: string, priemones: string, terminas: string, unitOfMeasurement: string, kiekis: string}>
     */
    private function buildEquipmentTableRowsFromGroups(array $groups, string $documentLocale = 'lt'): array
    {
        $rows = [];
        foreach ($groups as $g) {
            if (! is_array($g)) {
                continue;
            }
            $workerNames = [];
            foreach ($g['workers'] ?? [] as $w) {
                if (! is_array($w)) {
                    continue;
                }
                $n = trim((string) ($w['workerName'] ?? ''));
                if ($n !== '') {
                    $workerNames[] = $n;
                }
            }
            $pareigybe = $workerNames === [] ? '-' : implode(self::CELL_LIST_SEPARATOR, $workerNames);

            $priemonesParts = [];
            $terminasParts = [];
            $kiekisParts = [];
            $units = [];
            foreach ($g['equipment'] ?? [] as $eq) {
                if (! is_array($eq)) {
                    continue;
                }
                $priemonesParts[] = trim($this->localizedEquipmentName($eq, $documentLocale)) ?: '-';
                $terminasParts[] = trim($this->localizedEquipmentExpiration($eq, $documentLocale)) ?: '-';
                $kiekisParts[] = (string) Equipment::normalizeDocumentQuantity($eq['quantity'] ?? 1);
                $unit = trim((string) ($eq['unitOfMeasurement'] ?? 'vnt'));
                $units[] = $unit !== '' ? Equipment::normalizeUnitOfMeasurement($unit) : 'vnt';
            }

            $priemones = $priemonesParts === [] ? '-' : implode(self::CELL_LIST_SEPARATOR, $priemonesParts);
            $terminas = $terminasParts === [] ? '-' : implode(self::CELL_LIST_SEPARATOR, $terminasParts);
            $kiekis = $kiekisParts === [] ? '-' : implode(self::CELL_LIST_SEPARATOR, $kiekisParts);
            $unitOfMeasurement = $units === [] ? 'vnt' : $units[0];

            $rows[] = [
                'pareigybe' => $pareigybe,
                'priemones' => $priemones,
                'terminas' => $terminas,
                'unitOfMeasurement' => $unitOfMeasurement,
                'kiekis' => $kiekis,
            ];
        }

        if ($rows === []) {
            $rows[] = [
                'pareigybe' => '-',
                'priemones' => '-',
                'terminas' => '-',
                'unitOfMeasurement' => 'vnt',
                'kiekis' => '-',
            ];
        }

        return $rows;
    }

    /**
     * Kaip CreateFile::sanitizeForFilename — sutampa su išvesties aplanko pavadinimu.
     */
    private function sanitizeForFilenameLikeCreateFile(string $name): string
    {
        $s = trim($name);
        $s = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $s) ?? $s;
        $s = preg_replace('/\s+/', '_', trim($s)) ?? $s;

        return $s !== '' ? $s : '';
    }

    /**
     * Santykinis kelias po generated/, kaip CreateFile::createDocxDocument:
     * outputDirectory iš DB (įmonės kategorija) arba tipas/įmonė.
     */
    private function resolveRelativeOutputDirectoryForCreateFile(CompanyRequisite $company): string
    {
        $rel = trim(str_replace('\\', '/', (string) ($company->getDirectory() ?? '')), '/');
        if ($rel !== '') {
            return $rel;
        }

        $companyName = (string) $company->getCompanyName();
        $code = (string) $company->getCode();
        $tipas = (string) ($company->getCompanyType() ?? '');
        $companySlug = $this->sanitizeForFilenameLikeCreateFile($companyName) ?: ($code !== '' ? $code : 'be_kodo');
        $tipasSlug = $this->sanitizeForFilenameLikeCreateFile($tipas) ?: 'Kita';

        return $tipasSlug . '/' . $companySlug;
    }

    private function resolveGeneratedAbsoluteOutputDir(CompanyRequisite $company): string
    {
        return $this->projectDir . '/generated/' . $this->resolveRelativeOutputDirectoryForCreateFile($company);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderToFinalPath(
        string $kind,
        CompanyRequisite $company,
        array $tableRows,
        array $payload,
        ?string $kortelesPagrindasOverride = null,
        string $documentLocale = 'lt'
    ): string {
        $outDir = $this->resolveGeneratedAbsoluteOutputDir($company);
        if (! is_dir($outDir) && ! mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
            throw new \RuntimeException('Nepavyko sukurti katalogo: ' . $outDir);
        }

        $normLoc = $this->normalizeAapLocale($documentLocale);
        $locSuffix = $normLoc !== 'lt' ? '_' . mb_strtoupper($normLoc) : '';
        $prefix = $kind === self::OUTPUT_SARASAS ? 'AAP_sarasas_' : 'AAP_korteles_ziniarasciai_';
        $outBasename = $prefix . date('Ymd_His') . $locSuffix . '.docx';
        $outPath = $outDir . '/' . $outBasename;

        $stagingPath = $this->createStagingTemplatePath();
        try {
            $this->renderTemplate($kind, $company, $tableRows, $stagingPath, $kortelesPagrindasOverride, $documentLocale);
            $generatedPath = $this->finalizeAapThroughCreateFile(
                $stagingPath,
                $company,
                $kind,
                $kortelesPagrindasOverride,
                $outBasename,
                $documentLocale
            );
            if ($generatedPath !== $outPath) {
                throw new \RuntimeException(
                    'Sugeneruotas kelias neatitinka laukto: ' . $generatedPath . ' (laukta ' . $outPath . ')'
                );
            }
            $this->fixLegacyEilNrPlaceholdersInDocx($outPath);
        } finally {
            @unlink($stagingPath);
        }

        return $outPath;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderToTempPath(
        string $kind,
        CompanyRequisite $company,
        array $tableRows,
        array $payload,
        ?string $kortelesPagrindasOverride = null,
        string $documentLocale = 'lt'
    ): string {
        $tmpDir = $this->projectDir . '/var/aap-word-tmp';
        if (! is_dir($tmpDir) && ! mkdir($tmpDir, 0775, true) && ! is_dir($tmpDir)) {
            throw new \RuntimeException('Nepavyko sukurti laikino katalogo');
        }

        $prefix = $kind === self::OUTPUT_SARASAS ? 'sarasas_' : 'korteles_';
        $tmpBasename = $prefix . bin2hex(random_bytes(8)) . '.docx';
        $tmpPath = $tmpDir . '/' . $tmpBasename;

        $stagingPath = $this->createStagingTemplatePath();
        try {
            $this->renderTemplate($kind, $company, $tableRows, $stagingPath, $kortelesPagrindasOverride, $documentLocale);
            $genBasename = $prefix . bin2hex(random_bytes(4)) . '_zip.docx';
            $generatedPath = $this->finalizeAapThroughCreateFile(
                $stagingPath,
                $company,
                $kind,
                $kortelesPagrindasOverride,
                $genBasename,
                $documentLocale
            );
            $this->fixLegacyEilNrPlaceholdersInDocx($generatedPath);
            if (! @copy($generatedPath, $tmpPath)) {
                @unlink($generatedPath);
                throw new \RuntimeException('Nepavyko nukopijuoti ZIP dalies dokumento');
            }
            @unlink($generatedPath);
        } finally {
            @unlink($stagingPath);
        }

        return $tmpPath;
    }

    /**
     * Tik AAP lentelės / laisvas tekstas — įmonės laukai užpildomi vėliau per CreateFile.
     *
     * @param list<array{pareigybe: string, priemones: string, terminas: string}> $tableRows
     */
    private function renderTemplate(
        string $kind,
        CompanyRequisite $company,
        array $tableRows,
        string $stagingOutputPath,
        ?string $kortelesPagrindasOverride = null,
        string $documentLocale = 'lt'
    ): void {
        $working = $this->resolveWorkingTemplatePath($kind, $documentLocale);
        $langUpper = $this->documentLanguageUpper($documentLocale);

        $processor = new TemplateProcessor($working);

        $mergeKortelesPagrindasColumn = false;
        $kortelesPagrindasForMerge = '';

        if ($kind === self::OUTPUT_KORTELES) {
            $ov = $kortelesPagrindasOverride !== null ? trim($kortelesPagrindasOverride) : '';
            $pagrindasText = $ov !== '' ? $ov : $company->resolveAapKortelesPagrindas();
            $kortelesPagrindasForMerge = $pagrindasText;

            $pareigybesText = $this->buildPareigybesHeaderText($tableRows);
            $processor->setValue('pareigybes', $pareigybesText);

            $kortelesTableRows = [];
            foreach ($tableRows as $rowIndex => $r) {
                $kiekisCell = trim((string) ($r['kiekis'] ?? ''));
                if ($kiekisCell === '') {
                    $kiekisCell = '1';
                }
                $kortelesTableRows[] = [
                    'eilNr' => (string) ($rowIndex + 1),
                    'priemones' => $r['priemones'],
                    'terminas' => $r['terminas'],
                    'kiekis' => $kiekisCell,
                    'vnt' => Equipment::documentUnitLabel($r['unitOfMeasurement'] ?? 'vnt', $langUpper),
                    'pagrindas' => $pagrindasText,
                ];
            }
            try {
                $processor->cloneRowAndSetValues('priemones', $kortelesTableRows);
                $mergeKortelesPagrindasColumn = true;
            } catch (\Throwable) {
                $this->applyOptionalMacroIfPresent(
                    $processor,
                    ['korteles_turinys', 'korteles_duomenys', 'aap_korteles'],
                    $this->buildKortelesFreeformText($tableRows, $pareigybesText, $documentLocale)
                );
            }
        } else {
            $sarasasRows = [];
            foreach ($tableRows as $rowIndex => $r) {
                $sarasasRows[] = [
                    'eilNr' => (string) ($rowIndex + 1),
                    'pareigybe' => $r['pareigybe'],
                    'priemones' => $r['priemones'],
                    'terminas' => $r['terminas'],
                ];
            }
            try {
                $processor->cloneRowAndSetValues('pareigybe', $sarasasRows);
            } catch (\Throwable) {
                try {
                    $alt = [];
                    foreach ($tableRows as $rowIndex => $r) {
                        $alt[] = [
                            'eilNr' => (string) ($rowIndex + 1),
                            'pareigybes' => $r['pareigybe'],
                            'priemones' => $r['priemones'],
                            'terminas' => $r['terminas'],
                        ];
                    }
                    $processor = new TemplateProcessor($working);
                    $processor->cloneRowAndSetValues('pareigybes', $alt);
                } catch (\Throwable) {
                    $this->applyOptionalMacroIfPresent(
                        $processor,
                        ['sarasas_turinys', 'sarasas_duomenys', 'aap_sarasas'],
                        $this->buildSarasasFreeformText($tableRows)
                    );
                }
            }
        }

        $processor->saveAs($stagingOutputPath);

        if ($mergeKortelesPagrindasColumn && $kortelesPagrindasForMerge !== '') {
            try {
                $this->mergeKortelesPagrindasColumnInDocx($stagingOutputPath, $kortelesPagrindasForMerge);
            } catch (\Throwable) {
                // Best-effort: dokumentas vis tiek tinkamas, tik be vertikalaus suliejimo.
            }
        }
    }

    private function createStagingTemplatePath(): string
    {
        $dir = $this->projectDir . '/templates/_aap_temp';
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Nepavyko sukurti katalogo: ' . $dir);
        }

        return $dir . '/stage_' . bin2hex(random_bytes(8)) . '.docx';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreateFileDataForAap(
        CompanyRequisite $company,
        string $kind,
        ?string $kortelesPagrindasOverride,
        string $documentLocale = 'lt'
    ): array {
        $documentDate = $company->getDocumentDate() ?? (new \DateTimeImmutable())->format('Y-m-d');

        $replacements = [];
        if ($kind === self::OUTPUT_KORTELES) {
            $ov = $kortelesPagrindasOverride !== null ? trim($kortelesPagrindasOverride) : '';
            $pagrindasText = $ov !== '' ? $ov : $company->resolveAapKortelesPagrindas();
            $replacements['pagrindas'] = $pagrindasText;
        }

        return [
            'language' => $this->documentLanguageUpper($documentLocale),
            'kompanija' => (string) $company->getCompanyName(),
            'kodas' => (string) $company->getCode(),
            'data' => (string) $documentDate,
            'role' => (string) ($company->getRole() ?? ''),
            'tipas' => (string) ($company->getCompanyType() ?? ''),
            'tipasPilnas' => (string) $company->resolveTipasPilnasForDocuments(),
            'adresas' => (string) ($company->getAddress() ?? ''),
            'miestas' => (string) ($company->getCityOrDistrict() ?? ''),
            'managerType' => (string) ($company->getManagerType() ?? ''),
            'vardas' => (string) ($company->getManagerFirstName() ?? ''),
            'pavarde' => (string) ($company->getManagerLastName() ?? ''),
            'companyId' => (string) $company->getId(),
            'outputDirectory' => (string) ($company->getDirectory() ?? ''),
            'replacements' => $replacements,
        ];
    }

    private function finalizeAapThroughCreateFile(
        string $stagingAbsolutePath,
        CompanyRequisite $company,
        string $kind,
        ?string $kortelesPagrindasOverride,
        string $outputBasename,
        string $documentLocale = 'lt'
    ): string {
        $data = $this->buildCreateFileDataForAap($company, $kind, $kortelesPagrindasOverride, $documentLocale);
        $data['directory'] = '_aap_temp';
        $data['template'] = basename($stagingAbsolutePath);

        return $this->createFile->createWordDocument($data, $outputBasename);
    }

    /**
     * Senesniuose šablonuose kartais įrašyta ${eil Nr# 1} — PhpWord klonuodamas paverčia į ${eil Nr# 1#2} ir nebepakeičia.
     * Pakeičiame į gryną eilės numerį pagal paskutinį indeksą (#2 → „2“).
     */
    private function fixLegacyEilNrPlaceholdersInDocx(string $docxPath): void
    {
        if (! is_file($docxPath) || ! is_readable($docxPath)) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false || $xml === '') {
            $zip->close();

            return;
        }

        $step1 = preg_replace('/\$\{eil\s+Nr\s*#\s*\d+\s*#\s*(\d+)\}/u', '$1', $xml);
        $fixed = is_string($step1) ? $step1 : $xml;
        $step2 = preg_replace('/\$\{eilNr\s*#\s*\d+\s*#\s*(\d+)\}/u', '$1', $fixed);
        $fixed = is_string($step2) ? $step2 : $fixed;
        if ($fixed === $xml) {
            $zip->close();

            return;
        }

        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $fixed);
        $zip->close();
    }

    private function resolveWorkingTemplatePath(string $kind, string $documentLocale = 'lt'): string
    {
        $loc = $this->normalizeAapLocale($documentLocale);
        $localeCandidates = $loc !== 'lt' ? [$loc, 'lt'] : ['lt'];

        foreach ($localeCandidates as $tryLoc) {
            $fromDb = $this->aapEquipmentWordTemplateRepository->findOneByKindAndLocale($kind, $tryLoc);
            if ($fromDb instanceof AapEquipmentWordTemplate) {
                $bytes = $fromDb->getContent();
                if ($bytes !== '') {
                    return $this->materializeDbTemplateDocx($fromDb, $bytes);
                }
            }
        }

        foreach ($localeCandidates as $tryLoc) {
            $fs = $this->tryResolveFilesystemTemplate($kind, $tryLoc);
            if ($fs !== null) {
                return $fs;
            }
        }

        $label = $kind === self::OUTPUT_SARASAS ? 'sarasas-aap' : 'korteles-ziniarasciai';

        throw new \InvalidArgumentException(
            'Nerastas Word šablonas „' . $label . '“ (.docx arba .doc) kalbai „' . $loc . '“ (arba LT atsarginis). '
            . 'Įkelkite į templates/otherTemplates/aap-korteles-ziniarasciai/ (pvz. „' . $label . ' EN.docx“) arba administratoriaus skiltyje „Šablonas“.'
        );
    }

    /**
     * Absoliutus kelias iki .docx (DB arba diskas) — PDF peržiūrai ir generavimui.
     *
     * @param self::OUTPUT_SARASAS|self::OUTPUT_KORTELES $kind
     */
    public function getTemplateDocxAbsolutePath(string $kind, ?string $locale = null): string
    {
        if ($kind !== self::OUTPUT_SARASAS && $kind !== self::OUTPUT_KORTELES) {
            throw new \InvalidArgumentException('Netinkamas šablono tipas');
        }

        return $this->resolveWorkingTemplatePath($kind, $this->normalizeAapLocale($locale ?? 'lt'));
    }

    private function materializeDbTemplateDocx(AapEquipmentWordTemplate $entity, string $blob): string
    {
        $dir = $this->projectDir . '/var/aap-db-templates';
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Nepavyko sukurti katalogo: ' . $dir);
        }

        $key = md5(
            $entity->getTemplateKind() . '|' . $entity->getTemplateLocale() . '|' . $blob . '|' . $entity->getUpdatedAt()->format(DATE_ATOM)
        );
        $path = $dir . '/' . $entity->getTemplateKind() . '_' . $entity->getTemplateLocale() . '_' . $key . '.docx';
        if (is_file($path) && is_readable($path)) {
            return $path;
        }
        if (file_put_contents($path, $blob) === false) {
            throw new \RuntimeException('Nepavyko išsaugoti šablono iš DB: ' . $path);
        }

        return $path;
    }

    /**
     * Viena ${pareigybes} žyma šablone — keli darbuotojai sujungiami per kablelį.
     *
     * @param list<array{pareigybe: string, priemones: string, terminas: string}> $tableRows
     */
    private function buildPareigybesHeaderText(array $tableRows): string
    {
        $names = [];
        foreach ($tableRows as $r) {
            $n = trim($r['pareigybe']);
            if ($n !== '' && $n !== '-') {
                $names[$n] = true;
            }
        }
        $list = array_keys($names);

        return $list === [] ? '-' : (count($list) === 1 ? $list[0] : implode(', ', $list));
    }

    /**
     * @param list<string> $macroNamesLowercase
     */
    private function applyOptionalMacroIfPresent(TemplateProcessor $processor, array $macroNamesLowercase, string $body): bool
    {
        $allowed = array_flip($macroNamesLowercase);
        foreach ($processor->getVariables() as $name) {
            if (isset($allowed[strtolower((string) $name)])) {
                $processor->setValue($name, $body);

                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{pareigybe: string, priemones: string, terminas: string}> $tableRows
     */
    private function buildSarasasFreeformText(array $tableRows): string
    {
        $lines = [];
        foreach ($tableRows as $i => $r) {
            $lines[] = (string) ($i + 1) . '. ' . trim($r['pareigybe']) . ' | ' . trim($r['priemones']) . ' | ' . trim($r['terminas']);
        }

        return $lines === [] ? '-' : implode("\n", $lines);
    }

    /**
     * @param list<array{pareigybe: string, priemones: string, terminas: string, unitOfMeasurement?: string}> $tableRows
     */
    private function buildKortelesFreeformText(array $tableRows, string $pareigybesHeader, string $documentLocale = 'lt'): string
    {
        $langUpper = $this->documentLanguageUpper($documentLocale);
        $posLabel = match ($this->normalizeAapLocale($documentLocale)) {
            'en' => 'Job titles / positions: ',
            'ru' => 'Должности: ',
            default => 'Pareigybės: ',
        };
        $lines = [$posLabel . $pareigybesHeader, ''];
        foreach ($tableRows as $i => $r) {
            $vnt = Equipment::documentUnitLabel($r['unitOfMeasurement'] ?? 'vnt', $langUpper);
            $kCell = trim((string) ($r['kiekis'] ?? '1'));
            if ($kCell === '' || $kCell === '-') {
                $kCell = '1';
            }
            $kShow = str_replace(self::CELL_LIST_SEPARATOR, ' / ', $kCell);
            $lines[] = (string) ($i + 1) . '. ' . trim($r['priemones']) . ' | ' . trim($r['terminas']) . ' | ' . $kShow . ' | ' . $vnt;
        }

        return implode("\n", $lines);
    }

    /**
     * Sujungia „Pagrindas išduoti“ stulpelį vertikaliai per visas duomenų eilutes (w:vMerge),
     * kai visose duomenų eilutėse tas pats tekstas kaip ${pagrindas} (arba senasis fiksuotas šablonas).
     */
    private function mergeKortelesPagrindasColumnInDocx(string $docxPath, string $resolvedPagrindasText): void
    {
        if (! is_file($docxPath) || ! is_readable($docxPath)) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false || $xml === '') {
            return;
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        if (@$dom->loadXML($xml) !== true) {
            return;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::OOXML_W_NS);

        $tables = $xpath->query('//w:tbl');
        if ($tables === false) {
            return;
        }

        foreach ($tables as $tbl) {
            if (! $tbl instanceof \DOMElement) {
                continue;
            }
            $rows = $xpath->query('w:tr', $tbl);
            if ($rows === false || $rows->length < 2) {
                continue;
            }

            /** @var list<\DOMElement> $rowEls */
            $rowEls = [];
            foreach ($rows as $r) {
                if ($r instanceof \DOMElement) {
                    $rowEls[] = $r;
                }
            }

            $dataRows = array_slice($rowEls, 1);
            if ($dataRows === []) {
                continue;
            }

            $colIndex = $this->findPagrindasMergeColumnIndex($dataRows, $resolvedPagrindasText);
            if ($colIndex === null) {
                continue;
            }

            $cells = [];
            foreach ($dataRows as $tr) {
                $tc = $this->getTableCellAtLogicalColumn($tr, $colIndex);
                if (! $tc instanceof \DOMElement) {
                    $cells = [];
                    break;
                }
                $cells[] = $tc;
            }

            if ($cells === []) {
                continue;
            }

            $this->applyVerticalMergeToTableCells($dom, $cells);
        }

        $out = $dom->saveXML();
        if ($out === false || $out === '') {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }
        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $out);
        $zip->close();
    }

    /**
     * @param list<\DOMElement> $dataRows
     */
    private function findPagrindasMergeColumnIndex(array $dataRows, string $resolvedPagrindasText): ?int
    {
        $maxCol = 0;
        foreach ($dataRows as $tr) {
            $maxCol = max($maxCol, $this->countLogicalColumnsInRow($tr));
        }
        if ($maxCol < 1) {
            return null;
        }

        $needle = $this->normalizePagrindasTextForMergeMatch($resolvedPagrindasText);
        if ($needle !== '') {
            for ($c = 0; $c < $maxCol; ++$c) {
                $match = true;
                foreach ($dataRows as $tr) {
                    $tc = $this->getTableCellAtLogicalColumn($tr, $c);
                    if (! $tc instanceof \DOMElement) {
                        $match = false;
                        break;
                    }
                    $cellNorm = $this->normalizePagrindasTextForMergeMatch($this->extractPlainTextFromTableCell($tc));
                    if ($cellNorm !== $needle) {
                        $match = false;
                        break;
                    }
                }
                if ($match) {
                    return $c;
                }
            }
        }

        for ($c = 0; $c < $maxCol; ++$c) {
            $texts = [];
            foreach ($dataRows as $tr) {
                $tc = $this->getTableCellAtLogicalColumn($tr, $c);
                if (! $tc instanceof \DOMElement) {
                    continue 2;
                }
                $texts[] = mb_strtolower($this->extractPlainTextFromTableCell($tc), 'UTF-8');
            }

            if ($texts === []) {
                continue;
            }

            $first = $texts[0];
            if ($first === '') {
                continue;
            }
            if (! str_contains($first, self::PAGRINDAS_MERGE_SNIPPET_A) || ! str_contains($first, self::PAGRINDAS_MERGE_SNIPPET_B)) {
                continue;
            }

            $unique = array_unique($texts);
            if (count($unique) !== 1) {
                continue;
            }

            return $c;
        }

        return null;
    }

    private function normalizePagrindasTextForMergeMatch(string $plain): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $plain) ?? '');

        return mb_strtolower($s, 'UTF-8');
    }

    private function countLogicalColumnsInRow(\DOMElement $tr): int
    {
        $sum = 0;
        foreach ($this->trDirectTableCells($tr) as $tc) {
            $sum += $this->tableCellGridSpan($tc);
        }

        return $sum;
    }

    /**
     * @return list<\DOMElement>
     */
    private function trDirectTableCells(\DOMElement $tr): array
    {
        $out = [];
        foreach ($tr->childNodes as $n) {
            if ($n instanceof \DOMElement && $n->namespaceURI === self::OOXML_W_NS && $n->localName === 'tc') {
                $out[] = $n;
            }
        }

        return $out;
    }

    private function tableCellGridSpan(\DOMElement $tc): int
    {
        foreach ($tc->childNodes as $n) {
            if (! $n instanceof \DOMElement || $n->namespaceURI !== self::OOXML_W_NS || $n->localName !== 'tcPr') {
                continue;
            }
            foreach ($n->getElementsByTagNameNS(self::OOXML_W_NS, 'gridSpan') as $gs) {
                if ($gs->parentNode !== $n) {
                    continue;
                }
                $v = $gs->getAttributeNS(self::OOXML_W_NS, 'val');
                if ($v === '') {
                    $v = $gs->getAttribute('w:val');
                }
                $iv = (int) $v;

                return $iv > 1 ? $iv : 1;
            }
            break;
        }

        return 1;
    }

    private function getTableCellAtLogicalColumn(\DOMElement $tr, int $logicalIndex): ?\DOMElement
    {
        $pos = 0;
        foreach ($this->trDirectTableCells($tr) as $tc) {
            $span = $this->tableCellGridSpan($tc);
            if ($logicalIndex >= $pos && $logicalIndex < $pos + $span) {
                return $tc;
            }
            $pos += $span;
        }

        return null;
    }

    private function extractPlainTextFromTableCell(\DOMElement $tc): string
    {
        $doc = $tc->ownerDocument;
        if (! $doc instanceof \DOMDocument) {
            return '';
        }
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('w', self::OOXML_W_NS);
        $parts = [];
        $nodes = $xpath->query('.//w:t', $tc);
        if ($nodes !== false) {
            foreach ($nodes as $t) {
                $parts[] = $t->textContent;
            }
        }

        $s = trim(preg_replace('/\s+/u', ' ', implode('', $parts)) ?? '');

        return $s;
    }

    /**
     * @param list<\DOMElement> $cells Pirmas — restart, kiti — continue.
     */
    private function applyVerticalMergeToTableCells(\DOMDocument $dom, array $cells): void
    {
        if (count($cells) < 1) {
            return;
        }

        foreach ($cells as $i => $tc) {
            $isFirst = $i === 0;
            $this->ensureTableCellVerticalMerge($dom, $tc, $isFirst);
            if (! $isFirst) {
                $this->stripTableCellToEmptyParagraph($dom, $tc);
            }
        }
    }

    private function ensureTableCellVerticalMerge(\DOMDocument $dom, \DOMElement $tc, bool $restart): void
    {
        $tcPr = null;
        foreach ($tc->childNodes as $n) {
            if ($n instanceof \DOMElement && $n->namespaceURI === self::OOXML_W_NS && $n->localName === 'tcPr') {
                $tcPr = $n;
                break;
            }
        }
        if (! $tcPr instanceof \DOMElement) {
            $tcPr = $dom->createElementNS(self::OOXML_W_NS, 'w:tcPr');
            $tc->insertBefore($tcPr, $tc->firstChild);
        }

        $existing = null;
        foreach ($tcPr->getElementsByTagNameNS(self::OOXML_W_NS, 'vMerge') as $vm) {
            if ($vm->parentNode === $tcPr) {
                $existing = $vm;
                break;
            }
        }

        if ($existing instanceof \DOMElement) {
            $tcPr->removeChild($existing);
        }

        $vMerge = $dom->createElementNS(self::OOXML_W_NS, 'w:vMerge');
        if ($restart) {
            $vMerge->setAttribute('w:val', 'restart');
        }
        $tcPr->insertBefore($vMerge, $tcPr->firstChild);
    }

    private function stripTableCellToEmptyParagraph(\DOMDocument $dom, \DOMElement $tc): void
    {
        $remove = [];
        foreach ($tc->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }
            if ($child->namespaceURI === self::OOXML_W_NS && $child->localName === 'p') {
                $remove[] = $child;
            }
        }
        foreach ($remove as $p) {
            $tc->removeChild($p);
        }

        $p = $dom->createElementNS(self::OOXML_W_NS, 'w:p');
        $tc->appendChild($p);
    }
}
