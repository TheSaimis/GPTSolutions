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
 * Sąrašas (pasirinktinai): lentelė su ${pareigybe}/${pareigybes}, ${priemones}, ${terminas} ir cloneRow;
 *   jei lentelės nėra — užpildoma tik ${sarasas_turinys} arba ${sarasas_duomenys} arba ${aap_sarasas} (laisvas tekstas).
 * Be DB grupių — viena eilutė vienam darbuotojų tipui; su grupėmis — viena eilutė vienai grupei.
 * Kelios reikšmės langelyje — \\n (Word lūžis per PhpWord).
 * Kortelės: ${pareigybes} + lentelė ${priemones}, ${terminas}, ${kiekis}, ${vnt} arba ${korteles_turinys}/${aap_korteles}.
 * Įmonė: ${Kompanija}, ${TIPAS}, ${data}.
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

    public function __construct(
        private readonly string $projectDir,
        private readonly CreateEquipmentDocument $createEquipmentDocument,
        private readonly ConvertDocToDocx $convertDocToDocx,
        private readonly EntityManagerInterface $em,
        private readonly AapEquipmentWordTemplateRepository $aapEquipmentWordTemplateRepository,
    ) {}

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

    public function hasFilesystemTemplate(string $kind): bool
    {
        [$docxRel, $docRel] = $kind === self::OUTPUT_SARASAS
            ? [self::TEMPLATE_SARASAS_DOCX, self::TEMPLATE_SARASAS_DOC]
            : [self::TEMPLATE_KORTELES_DOCX, self::TEMPLATE_KORTELES_DOC];

        $docx = $this->projectDir . '/' . $docxRel;
        if (is_file($docx) && is_readable($docx)) {
            return true;
        }
        $doc = $this->projectDir . '/' . $docRel;

        return is_file($doc) && is_readable($doc);
    }

    /**
     * @param list<self::OUTPUT_*> $outputs
     *
     * @return array{path: string, filename: string, mime: string}
     */
    public function generate(int $companyId, array $outputs): array
    {
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
        $tableRows = $this->buildEquipmentTableRows($payload);

        if (count($list) === 1) {
            return $this->singleOutput($list[0], $company, $tableRows, $payload);
        }

        $paths = [];
        foreach ($list as $kind) {
            $paths[$kind] = $this->renderToTempPath($kind, $company, $tableRows, $payload);
        }

        $slug = $this->companySlug($payload);
        $zipDir = $this->projectDir . '/generated/equipment/' . $slug;
        if (! is_dir($zipDir) && ! mkdir($zipDir, 0775, true) && ! is_dir($zipDir)) {
            throw new \RuntimeException('Nepavyko sukurti katalogo: ' . $zipDir);
        }

        $zipPath = $zipDir . '/AAP_Korteles_Ziniarasciai_' . date('Ymd_His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            foreach ($paths as $p) {
                @unlink($p);
            }
            throw new \RuntimeException('Nepavyko sukurti ZIP archyvo');
        }

        if (isset($paths[self::OUTPUT_SARASAS])) {
            $zip->addFile($paths[self::OUTPUT_SARASAS], 'AAP_sarasas.docx');
        }
        if (isset($paths[self::OUTPUT_KORTELES])) {
            $zip->addFile($paths[self::OUTPUT_KORTELES], 'AAP_korteles_ziniarasciai.docx');
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
    private function singleOutput(string $kind, CompanyRequisite $company, array $tableRows, array $payload): array
    {
        $path = $this->renderToFinalPath($kind, $company, $tableRows, $payload);

        return [
            'path' => $path,
            'filename' => basename($path),
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{pareigybe: string, priemones: string, terminas: string, unitOfMeasurement: string}>
     */
    private function buildEquipmentTableRows(array $payload): array
    {
        $groups = $payload['groups'] ?? null;
        if (is_array($groups) && $groups !== []) {
            return $this->buildEquipmentTableRowsFromGroups($groups);
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
                ];

                continue;
            }
            // Viena lentelės eilutė vienam darbuotojų tipui: visos priemonės ir terminai toje pačioje eilutėje.
            $priemonesParts = [];
            $terminasParts = [];
            $units = [];
            foreach ($eqList as $eq) {
                if (! is_array($eq)) {
                    continue;
                }
                $priemonesParts[] = trim((string) ($eq['name'] ?? '')) ?: '-';
                $terminasParts[] = trim((string) ($eq['expirationDate'] ?? '')) ?: '-';
                $unit = trim((string) ($eq['unitOfMeasurement'] ?? 'vnt'));
                $units[] = $unit !== '' ? Equipment::normalizeUnitOfMeasurement($unit) : 'vnt';
            }
            $rows[] = [
                'pareigybe' => $name !== '' ? $name : '-',
                'priemones' => $priemonesParts === [] ? '-' : implode(self::CELL_LIST_SEPARATOR, $priemonesParts),
                'terminas' => $terminasParts === [] ? '-' : implode(self::CELL_LIST_SEPARATOR, $terminasParts),
                'unitOfMeasurement' => $units === [] ? 'vnt' : $units[0],
            ];
        }

        if ($rows === []) {
            $rows[] = [
                'pareigybe' => '-',
                'priemones' => '-',
                'terminas' => '-',
                'unitOfMeasurement' => 'vnt',
            ];
        }

        return $rows;
    }

    /**
     * Viena lentelės eilutė vienai grupei: visi grupės darbuotojai, visos priemonės ir terminai toje pačioje eilutėje.
     *
     * @param list<array<string, mixed>> $groups
     *
     * @return list<array{pareigybe: string, priemones: string, terminas: string, unitOfMeasurement: string}>
     */
    private function buildEquipmentTableRowsFromGroups(array $groups): array
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
            $units = [];
            foreach ($g['equipment'] ?? [] as $eq) {
                if (! is_array($eq)) {
                    continue;
                }
                $priemonesParts[] = trim((string) ($eq['name'] ?? '')) ?: '-';
                $terminasParts[] = trim((string) ($eq['expirationDate'] ?? '')) ?: '-';
                $unit = trim((string) ($eq['unitOfMeasurement'] ?? 'vnt'));
                $units[] = $unit !== '' ? Equipment::normalizeUnitOfMeasurement($unit) : 'vnt';
            }

            $priemones = $priemonesParts === [] ? '-' : implode(self::CELL_LIST_SEPARATOR, $priemonesParts);
            $terminas = $terminasParts === [] ? '-' : implode(self::CELL_LIST_SEPARATOR, $terminasParts);
            $unitOfMeasurement = $units === [] ? 'vnt' : $units[0];

            $rows[] = [
                'pareigybe' => $pareigybe,
                'priemones' => $priemones,
                'terminas' => $terminas,
                'unitOfMeasurement' => $unitOfMeasurement,
            ];
        }

        if ($rows === []) {
            $rows[] = [
                'pareigybe' => '-',
                'priemones' => '-',
                'terminas' => '-',
                'unitOfMeasurement' => 'vnt',
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function companySlug(array $payload): string
    {
        $name = (string) ($payload['company']['companyName'] ?? 'imone');
        $slug = preg_replace('/[^\w]+/', '_', $name) ?: 'imone';

        return $slug;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderToFinalPath(string $kind, CompanyRequisite $company, array $tableRows, array $payload): string
    {
        $slug = $this->companySlug($payload);
        $outDir = $this->projectDir . '/generated/equipment/' . $slug;
        if (! is_dir($outDir) && ! mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
            throw new \RuntimeException('Nepavyko sukurti katalogo: ' . $outDir);
        }

        $prefix = $kind === self::OUTPUT_SARASAS ? 'AAP_sarasas_' : 'AAP_korteles_ziniarasciai_';
        $outPath = $outDir . '/' . $prefix . date('Ymd_His') . '.docx';
        $this->renderTemplate($kind, $company, $tableRows, $outPath);

        return $outPath;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderToTempPath(string $kind, CompanyRequisite $company, array $tableRows, array $payload): string
    {
        $tmpDir = $this->projectDir . '/var/aap-word-tmp';
        if (! is_dir($tmpDir) && ! mkdir($tmpDir, 0775, true) && ! is_dir($tmpDir)) {
            throw new \RuntimeException('Nepavyko sukurti laikino katalogo');
        }

        $prefix = $kind === self::OUTPUT_SARASAS ? 'sarasas_' : 'korteles_';
        $outPath = $tmpDir . '/' . $prefix . bin2hex(random_bytes(8)) . '.docx';
        $this->renderTemplate($kind, $company, $tableRows, $outPath);

        return $outPath;
    }

    /**
     * @param list<array{pareigybe: string, priemones: string, terminas: string}> $tableRows
     */
    private function renderTemplate(string $kind, CompanyRequisite $company, array $tableRows, string $outputPath): void
    {
        $working = $this->resolveWorkingTemplatePath($kind);

        $processor = new TemplateProcessor($working);

        if ($kind === self::OUTPUT_KORTELES) {
            foreach ($this->buildCompanyPlaceholdersKorteles($company) as $macro => $value) {
                $processor->setValue($macro, $value);
            }

            $pareigybesText = $this->buildPareigybesHeaderText($tableRows);
            $processor->setValue('pareigybes', $pareigybesText);

            $kortelesTableRows = [];
            foreach ($tableRows as $r) {
                $kortelesTableRows[] = [
                    'priemones' => $r['priemones'],
                    'terminas' => $r['terminas'],
                    'kiekis' => '1',
                    'vnt' => Equipment::documentUnitLabel($r['unitOfMeasurement'] ?? 'vnt'),
                ];
            }
            try {
                $processor->cloneRowAndSetValues('priemones', $kortelesTableRows);
            } catch (\Throwable) {
                $this->applyOptionalMacroIfPresent(
                    $processor,
                    ['korteles_turinys', 'korteles_duomenys', 'aap_korteles'],
                    $this->buildKortelesFreeformText($tableRows, $pareigybesText)
                );
            }
        } else {
            foreach ($this->buildCompanyPlaceholdersSarasas($company) as $macro => $value) {
                $processor->setValue($macro, $value);
            }
            $sarasasRows = [];
            foreach ($tableRows as $r) {
                $sarasasRows[] = [
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
                    foreach ($tableRows as $r) {
                        $alt[] = [
                            'pareigybes' => $r['pareigybe'],
                            'priemones' => $r['priemones'],
                            'terminas' => $r['terminas'],
                        ];
                    }
                    $processor = new TemplateProcessor($working);
                    foreach ($this->buildCompanyPlaceholdersSarasas($company) as $macro => $value) {
                        $processor->setValue($macro, $value);
                    }
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

        $processor->saveAs($outputPath);
    }

    private function resolveWorkingTemplatePath(string $kind): string
    {
        $fromDb = $this->aapEquipmentWordTemplateRepository->findOneBy(['templateKind' => $kind]);
        if ($fromDb instanceof AapEquipmentWordTemplate) {
            $bytes = $fromDb->getContent();
            if ($bytes !== '') {
                return $this->materializeDbTemplateDocx($fromDb, $bytes);
            }
        }

        [$docxRel, $docRel] = $kind === self::OUTPUT_SARASAS
            ? [self::TEMPLATE_SARASAS_DOCX, self::TEMPLATE_SARASAS_DOC]
            : [self::TEMPLATE_KORTELES_DOCX, self::TEMPLATE_KORTELES_DOC];

        $docx = $this->projectDir . '/' . $docxRel;
        if (is_file($docx) && is_readable($docx)) {
            return $docx;
        }

        $doc = $this->projectDir . '/' . $docRel;
        if (is_file($doc) && is_readable($doc)) {
            return $this->convertDocToDocx->ensureDocxForTemplate($doc);
        }

        $label = $kind === self::OUTPUT_SARASAS ? 'sarasas-aap' : 'korteles-ziniarasciai';

        throw new \InvalidArgumentException(
            'Nerastas Word šablonas „' . $label . '“ (.docx arba .doc). '
            . 'Įkelkite į templates/otherTemplates/aap-korteles-ziniarasciai/ arba administratoriaus skiltyje „Šablonas“.'
        );
    }

    /**
     * Absoliutus kelias iki .docx (DB arba diskas) — PDF peržiūrai ir generavimui.
     *
     * @param self::OUTPUT_SARASAS|self::OUTPUT_KORTELES $kind
     */
    public function getTemplateDocxAbsolutePath(string $kind): string
    {
        if ($kind !== self::OUTPUT_SARASAS && $kind !== self::OUTPUT_KORTELES) {
            throw new \InvalidArgumentException('Netinkamas šablono tipas');
        }

        return $this->resolveWorkingTemplatePath($kind);
    }

    private function materializeDbTemplateDocx(AapEquipmentWordTemplate $entity, string $blob): string
    {
        $dir = $this->projectDir . '/var/aap-db-templates';
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Nepavyko sukurti katalogo: ' . $dir);
        }

        $key = md5($entity->getTemplateKind() . $blob . $entity->getUpdatedAt()->format(DATE_ATOM));
        $path = $dir . '/' . $entity->getTemplateKind() . '_' . $key . '.docx';
        if (is_file($path) && is_readable($path)) {
            return $path;
        }
        if (file_put_contents($path, $blob) === false) {
            throw new \RuntimeException('Nepavyko išsaugoti šablono iš DB: ' . $path);
        }

        return $path;
    }

    /**
     * @return array<string, string>
     */
    private function buildCompanyPlaceholdersSarasas(CompanyRequisite $company): array
    {
        $companyName = (string) $company->getCompanyName();
        $tipas = (string) ($company->getCompanyType() ?? '');
        $tipasPilnas = (string) $company->resolveTipasPilnasForDocuments();
        $tipasKompaktiskas = mb_strlen($companyName, 'UTF-8') > 14 ? $tipas : $tipasPilnas;
        $docDate = $company->getDocumentDate() ?? (new \DateTimeImmutable())->format('Y-m-d');

        return [
            'TIPASKOMPAKTISKAS' => $tipasKompaktiskas,
            'Kompanija' => $companyName,
            'kompanija' => $companyName,
            'kodas' => (string) $company->getCode(),
            'tipas' => $tipas,
            'role' => (string) ($company->getRole() ?? ''),
            'data' => (string) $docDate,
        ];
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
     * Šablonui „aap KORTELĖS+ŽINIARAŠČIAI“: ${Kompanija}, ${TIPAS}, ${data}.
     *
     * @return array<string, string>
     */
    private function buildCompanyPlaceholdersKorteles(CompanyRequisite $company): array
    {
        $companyName = (string) $company->getCompanyName();
        $tipas = (string) ($company->getCompanyType() ?? '');
        $docDate = $company->getDocumentDate() ?? (new \DateTimeImmutable())->format('Y-m-d');

        return [
            'Kompanija' => $companyName,
            'TIPAS' => $tipas,
            'data' => (string) $docDate,
        ];
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
        foreach ($tableRows as $r) {
            $lines[] = trim($r['pareigybe']) . ' | ' . trim($r['priemones']) . ' | ' . trim($r['terminas']);
        }

        return $lines === [] ? '-' : implode("\n", $lines);
    }

    /**
     * @param list<array{pareigybe: string, priemones: string, terminas: string, unitOfMeasurement?: string}> $tableRows
     */
    private function buildKortelesFreeformText(array $tableRows, string $pareigybesHeader): string
    {
        $lines = ['Pareigybės: ' . $pareigybesHeader, ''];
        foreach ($tableRows as $r) {
            $vnt = Equipment::documentUnitLabel($r['unitOfMeasurement'] ?? 'vnt');
            $lines[] = trim($r['priemones']) . ' | ' . trim($r['terminas']) . ' | 1 | ' . $vnt;
        }

        return implode("\n", $lines);
    }
}
