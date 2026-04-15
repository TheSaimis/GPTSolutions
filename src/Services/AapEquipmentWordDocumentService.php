<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\AapEquipmentWordTemplate;
use App\Entity\CompanyRequisite;
use App\Entity\Equipment;
use App\Services\Metadata\FlowMacroIgnores;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

/**
 * Word šablonai „AAP sąrašas“ ir „AAP kortelės + žiniaraščiai“ — generavimas skaito tik .docx po templates/AAP/
 * (konstantos TEMPLATE_SARASAS_DOCX, TEMPLATE_KORTELES_DOCX ir EN/RU variantai tame pačiame aplanke).
 * Admin įkėlimas saugo kopiją DB, bet vientisas šaltinis generavimui yra failas templates/AAP (žr. syncDbAapTemplateToDisk).
 *
 * Sąrašas (pasirinktinai): lentelė su ${pareigybe}/${pareigybes}, ${priemones}, ${terminas} ir cloneRow;
 *   jei lentelės nėra — užpildoma tik ${sarasas_turinys} arba ${sarasas_duomenys} arba ${aap_sarasas} (laisvas tekstas).
 * Be DB grupių — viena eilutė vienam darbuotojų tipui; su grupėmis — viena eilutė vienai grupei.
 * Kortelėse — viena lentelės eilutė vienai priemonei (sąraše galima sujungti kelias į vieną eilutę).
 * Kelios reikšmės langelyje — \\n (Word lūžis per PhpWord).
 * Kortelės: ${pareigybes} + lentelė ${priemones}, ${terminas}, ${kiekis}, ${vnt}, ${pagrindas} arba ${korteles_turinys}/${aap_korteles}.
 * Kelios AAP grupės (kortelėms): kiekvienai grupei generuojamas visas šablonas iš naujo ir sujungiamas su puslapio lūžiu (ne viena bendra lentelė ir ne antras puslapis rankiniu kopijavimu — PhpWord užpildo tik pirmą kintamųjų sritį).
 * Po lentelės generavimo „Pagrindas išduoti“ stulpelis su tuo pačiu tekstu visose eilutėse automatiškai sujungiamas vertikaliai (w:vMerge).
 * Įmonės rekvizitai ir bendri šablono laukai užpildomi per CreateFile (tarpinis .docx — laikinas katalogas, ne šablonas).
 * Abu dokumentai — abu .docx lieka generated/ įmonės aplane; ZIP atsisiuntimui kuriamas tik laikinai (var/) ir ištrinamas po siuntimo.
 */
final class AapEquipmentWordDocumentService
{
    public const OUTPUT_SARASAS = 'sarasas';

    public const OUTPUT_KORTELES = 'korteles';

    /** Kanoniniai šablonų vardai diske (templates/AAP/) — LT be priesagos, EN/RU: „ … EN.docx“ / „ … RU.docx“. */
    private const TEMPLATE_SARASAS_DOCX = 'templates/AAP/AAP sąrašas.docx';

    private const TEMPLATE_KORTELES_DOCX = 'templates/AAP/AAP kortelės + žiniaraščiai.docx';

    /** Kelios reikšmės viename lentelės langelyje — TemplateProcessor paverčia į Word eilučių lūžius. */
    private const CELL_LIST_SEPARATOR = "\n";

    /** Kelios pareigybės ${pareigybes} antraštėje (kortelės / laisvas tekstas). */
    private const PAREIGYBES_DISPLAY_SEPARATOR = ' / ';

    private const OOXML_W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Stulpelio „Pagrindas išduoti“ aptikimui (fiksuotas šablono tekstas visose klonuotose eilutėse). */
    private const PAGRINDAS_MERGE_SNIPPET_A = 'vadovaujantis';

    private const PAGRINDAS_MERGE_SNIPPET_B = 'nemokamai';

    public function __construct(
        private readonly string $projectDir,
        private readonly CreateEquipmentDocument $createEquipmentDocument,
        private readonly EntityManagerInterface $em,
        private readonly CreateFile $createFile,
        private readonly AddWordDocument $addWordDocument,
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

    /**
     * Kanoninis .docx šablonas templates/AAP (tik šis katalogas; be .doc ir be otherTemplates).
     */
    private function tryResolveFilesystemTemplate(string $kind, string $locale): ?string
    {
        $docxRel = $kind === self::OUTPUT_SARASAS ? self::TEMPLATE_SARASAS_DOCX : self::TEMPLATE_KORTELES_DOCX;

        return $this->tryResolveAapDocxTemplate($docxRel, $locale);
    }

    private function tryResolveAapDocxTemplate(string $docxRel, string $locale): ?string
    {
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

        return null;
    }

    /**
     * @param self::OUTPUT_SARASAS|self::OUTPUT_KORTELES $kind
     */
    public function getAapFilesystemTemplateAbsolutePath(string $kind, ?string $locale = null): ?string
    {
        if ($kind !== self::OUTPUT_SARASAS && $kind !== self::OUTPUT_KORTELES) {
            return null;
        }

        return $this->tryResolveFilesystemTemplate($kind, $this->normalizeAapLocale($locale ?? 'lt'));
    }

    /**
     * Išvalo iš disko šablonus templates/AAP (po įkėlimo / trynimo).
     * Kanoniniai vardai: {@see materializedDbAapTemplateFilename}; taip pat senesni techniniai ir UTF-8 legacy vardai.
     *
     * @param self::OUTPUT_SARASAS|self::OUTPUT_KORTELES|null $kind
     * @param 'lt'|'en'|'ru'|null                             $templateLocale jei nustatyta kartu su $kind — trinamas tik tas vienas failas
     */
    public function clearMaterializedDbTemplates(?string $kind = null, ?string $templateLocale = null): void
    {
        $dir = $this->projectDir . '/templates/AAP';
        if (! is_dir($dir)) {
            return;
        }

        $kinds = $kind !== null ? [$kind] : [self::OUTPUT_SARASAS, self::OUTPUT_KORTELES];
        $locales = $templateLocale !== null
            ? [$this->normalizeAapLocale($templateLocale)]
            : ['lt', 'en', 'ru'];

        foreach ($kinds as $k) {
            if ($k !== self::OUTPUT_SARASAS && $k !== self::OUTPUT_KORTELES) {
                continue;
            }
            foreach ($locales as $loc) {
                $file = $dir . '/' . $this->materializedDbAapTemplateFilename($k, $loc);
                if (is_file($file)) {
                    @unlink($file);
                }
                $legacyName = $this->legacyMaterializedDbAapTemplateFilename($k, $loc);
                if ($legacyName !== null) {
                    $legacyPath = $dir . '/' . $legacyName;
                    if (is_file($legacyPath)) {
                        @unlink($legacyPath);
                    }
                }
                $technicalLegacy = $this->legacyTechnicalAapTemplateFilename($k, $loc);
                if ($technicalLegacy !== null) {
                    $technicalPath = $dir . '/' . $technicalLegacy;
                    if (is_file($technicalPath)) {
                        @unlink($technicalPath);
                    }
                }
            }
        }

        if ($kind === null && $templateLocale === null) {
            foreach (['sarasas_*.docx', 'korteles_*.docx'] as $pat) {
                foreach (glob($dir . '/' . $pat) ?: [] as $file) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Po įkėlimo į DB — iškart užrašo blob į templates/AAP (be PDF peržiūros).
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function syncDbAapTemplateToDisk(AapEquipmentWordTemplate $entity): string
    {
        $bytes = $entity->getContent();
        if ($bytes === null || $bytes === '') {
            throw new \InvalidArgumentException('Tuščias AAP šablono turinys');
        }

        $path = $this->materializeDbTemplateDocx($entity, $bytes);
        $this->addWordDocument->ensureTemplateCustomMetadata(
            $path,
            $entity->getOriginalFilename() !== '' ? $entity->getOriginalFilename() : basename($path),
            null,
            FlowMacroIgnores::aapEquipmentWord()
        );

        return $path;
    }

    /**
     * DB šablono failo pavadinimas po templates/AAP/ — sutampa su {@see tryResolveFilesystemTemplate} pirminiais vardais.
     *
     * @param self::OUTPUT_SARASAS|self::OUTPUT_KORTELES $kind
     */
    private function materializedDbAapTemplateFilename(string $kind, string $locale): string
    {
        $loc = $this->normalizeAapLocale($locale);
        $suffix = $loc === 'en' ? ' EN' : ($loc === 'ru' ? ' RU' : '');
        $relDocx = $kind === self::OUTPUT_SARASAS ? self::TEMPLATE_SARASAS_DOCX : self::TEMPLATE_KORTELES_DOCX;
        $base = pathinfo($relDocx, PATHINFO_FILENAME);
        $base = is_string($base) && $base !== ''
            ? $base
            : ($kind === self::OUTPUT_SARASAS ? 'AAP sąrašas' : 'AAP kortelės + žiniaraščiai');

        return $base . $suffix . '.docx';
    }

    /**
     * Senas DB išmaterialinimo vardas (prieš kanoninį sutapatinimą) — pašalinamas, kad neliktų dviejų skirtingų templateId.
     *
     * @param self::OUTPUT_SARASAS|self::OUTPUT_KORTELES $kind
     */
    private function legacyMaterializedDbAapTemplateFilename(string $kind, string $locale): ?string
    {
        $loc = $this->normalizeAapLocale($locale);
        $suffix = $loc === 'en' ? ' EN' : ($loc === 'ru' ? ' RU' : '');
        if ($kind === self::OUTPUT_SARASAS) {
            return 'aap S' . "\xC4\x84" . 'RA' . "\xC5\xA0" . 'AS' . $suffix . '.docx';
        }
        if ($kind === self::OUTPUT_KORTELES) {
            return 'aap KORTEL' . "\xC4\x96" . 'S+' . "\xC5\xBD" . 'INIARA' . "\xC5\xA0" . "\xC4\x8C" . 'IAI' . $suffix . '.docx';
        }

        return null;
    }

    /**
     * Seni techniniai vardai (sarasas-aap / korteles-ziniarasciai) — pašalinami įrašant naują kanoninį failą.
     *
     * @param self::OUTPUT_SARASAS|self::OUTPUT_KORTELES $kind
     */
    private function legacyTechnicalAapTemplateFilename(string $kind, string $locale): ?string
    {
        $loc = $this->normalizeAapLocale($locale);
        $suffix = $loc === 'en' ? ' EN' : ($loc === 'ru' ? ' RU' : '');
        if ($kind === self::OUTPUT_SARASAS) {
            return 'sarasas-aap' . $suffix . '.docx';
        }
        if ($kind === self::OUTPUT_KORTELES) {
            return 'korteles-ziniarasciai' . $suffix . '.docx';
        }

        return null;
    }

    public function hasFilesystemTemplate(string $kind, ?string $locale = null): bool
    {
        $loc = $this->normalizeAapLocale($locale ?? 'lt');

        return $this->tryResolveFilesystemTemplate($kind, $loc) !== null;
    }

    /**
     * @param list<self::OUTPUT_*> $outputs
     * @param string|null         $kortelesPagrindasOverride ne null ir ne tuščia — perrašo ${pagrindas} tik kortelių dokumente (vienkartinis generavimas)
     * @param array<string, mixed> $customReplacements Papildomi custom/replacements laukai iš API.
     *
     * @return array{path: string, filename: string, mime: string, deleteAfterSend?: bool} deleteAfterSend — tik laikinam ZIP atsisiuntimui (nebėra saugoma generated/)
     */
    public function generate(
        int $companyId,
        array $outputs,
        ?string $kortelesPagrindasOverride = null,
        ?string $documentLocale = null,
        array $customReplacements = []
    ): array
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
        $tableRowsSarasas = $this->buildEquipmentTableRows($payload, $documentLocale, true);
        $tableRowsKorteles = $this->buildEquipmentTableRows($payload, $documentLocale, true);

        if (count($list) === 1) {
            $kind = $list[0];
            $rows = $kind === self::OUTPUT_KORTELES ? $tableRowsKorteles : $tableRowsSarasas;

            return $this->singleOutput(
                $kind,
                $company,
                $rows,
                $payload,
                $kortelesPagrindasOverride,
                $documentLocale,
                $customReplacements
            );
        }

        $paths = [];
        foreach ($list as $kind) {
            $rows = $kind === self::OUTPUT_KORTELES ? $tableRowsKorteles : $tableRowsSarasas;
            $paths[$kind] = $this->renderToTempPath(
                $kind,
                $company,
                $rows,
                $payload,
                $kind === self::OUTPUT_KORTELES ? $kortelesPagrindasOverride : null,
                $documentLocale,
                $customReplacements
            );
        }

        // ZIP tik atsisiuntimui — laikinas kelias; generated/ lieka tik abu .docx (žr. renderToTempPath).
        $zipTmpDir = $this->projectDir . '/var/aap-word-tmp';
        if (! is_dir($zipTmpDir) && ! mkdir($zipTmpDir, 0775, true) && ! is_dir($zipTmpDir)) {
            throw new \RuntimeException('Nepavyko sukurti laikino katalogo ZIP archyvui');
        }

        $zipLoc = $documentLocale !== 'lt' ? '_' . mb_strtoupper($documentLocale) : '';
        $zipCompanySlug = $this->sanitizeForFilenameLikeCreateFile((string) $company->getCompanyName())
            ?: ((string) $company->getCode() !== '' ? (string) $company->getCode() : 'be_kodo');
        $zipPath = $zipTmpDir . '/aap_bundle_' . bin2hex(random_bytes(12)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            foreach ($paths as $p) {
                @unlink($p);
            }
            throw new \RuntimeException('Nepavyko sukurti ZIP archyvo');
        }

        if (isset($paths[self::OUTPUT_SARASAS])) {
            $zip->addFile(
                $paths[self::OUTPUT_SARASAS],
                $this->buildAapGeneratedDocxBasename($company, self::OUTPUT_SARASAS, $documentLocale)
            );
        }
        if (isset($paths[self::OUTPUT_KORTELES])) {
            $zip->addFile(
                $paths[self::OUTPUT_KORTELES],
                $this->buildAapGeneratedDocxBasename($company, self::OUTPUT_KORTELES, $documentLocale)
            );
        }
        $zip->close();

        foreach ($paths as $p) {
            @unlink($p);
        }

        return [
            'path' => $zipPath,
            'filename' => 'AAP_dokumentai_' . $zipCompanySlug . $zipLoc . '.zip',
            'mime' => 'application/zip',
            'deleteAfterSend' => true,
        ];
    }

    /**
     * @param list<self::OUTPUT_*> $outputs
     *
     * @return array{path: string, filename: string, mime: string, deleteAfterSend: false}
     */
    private function singleOutput(
        string $kind,
        CompanyRequisite $company,
        array $tableRows,
        array $payload,
        ?string $kortelesPagrindasOverride,
        string $documentLocale,
        array $customReplacements = []
    ): array
    {
        $path = $this->renderToFinalPath(
            $kind,
            $company,
            $tableRows,
            $payload,
            $kind === self::OUTPUT_KORTELES ? $kortelesPagrindasOverride : null,
            $documentLocale,
            $customReplacements
        );

        return [
            'path' => $path,
            'filename' => basename($path),
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'deleteAfterSend' => false,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param bool                   $oneRowPerEquipmentPiece true — kortelėms: viena eilutė vienai priemonei; false — sąrašui: sujungtos eilutės kaip anksčiau
     *
     * @return list<array{pareigybe: string, priemones: string, terminas: string, unitOfMeasurement: string, kiekis: string}>
     */
    private function buildEquipmentTableRows(array $payload, string $documentLocale = 'lt', bool $oneRowPerEquipmentPiece = false): array
    {
        $groups = $payload['groups'] ?? null;
        if (is_array($groups) && $groups !== []) {
            return $this->buildEquipmentTableRowsFromGroups($groups, $documentLocale, $oneRowPerEquipmentPiece);
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
            if ($oneRowPerEquipmentPiece) {
                $expanded = 0;
                foreach ($eqList as $eq) {
                    if (! is_array($eq)) {
                        continue;
                    }
                    $unit = trim((string) ($eq['unitOfMeasurement'] ?? 'vnt'));
                    $unitNorm = $unit !== '' ? Equipment::normalizeUnitOfMeasurement($unit) : 'vnt';
                    $rows[] = [
                        'pareigybe' => $name !== '' ? $name : '-',
                        'priemones' => trim($this->localizedEquipmentName($eq, $documentLocale)) ?: '-',
                        'terminas' => trim($this->localizedEquipmentExpiration($eq, $documentLocale)) ?: '-',
                        'unitOfMeasurement' => $unitNorm,
                        'kiekis' => (string) Equipment::normalizeDocumentQuantity($eq['quantity'] ?? 1),
                    ];
                    ++$expanded;
                }
                if ($expanded === 0) {
                    $rows[] = [
                        'pareigybe' => $name !== '' ? $name : '-',
                        'priemones' => '-',
                        'terminas' => '-',
                        'unitOfMeasurement' => 'vnt',
                        'kiekis' => '-',
                    ];
                }

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
     * Viena lentelės eilutė vienai grupei (sąrašas): pareigybių stulpelis iš grupės darbuotojų tipų (${pareigybes}); jei jų nėra — grupės pavadinimas.
     * Kortelėms — galima išskleisti į kelias eilutes (po vieną priemonei).
     *
     * @param list<array<string, mixed>> $groups
     *
     * @return list<array{pareigybe: string, priemones: string, terminas: string, unitOfMeasurement: string, kiekis: string}>
     */
    private function buildEquipmentTableRowsFromGroups(array $groups, string $documentLocale = 'lt', bool $oneRowPerEquipmentPiece = false): array
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
            $groupName = trim((string) ($g['groupName'] ?? ''));
            if ($workerNames !== []) {
                $pareigybe = implode(self::CELL_LIST_SEPARATOR, $workerNames);
            } elseif ($groupName !== '') {
                $pareigybe = $groupName;
            } else {
                $pareigybe = '-';
            }

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

            if ($oneRowPerEquipmentPiece && $priemonesParts !== []) {
                foreach ($g['equipment'] ?? [] as $eq) {
                    if (! is_array($eq)) {
                        continue;
                    }
                    $unit = trim((string) ($eq['unitOfMeasurement'] ?? 'vnt'));
                    $unitNorm = $unit !== '' ? Equipment::normalizeUnitOfMeasurement($unit) : 'vnt';
                    $rows[] = [
                        'pareigybe' => $pareigybe,
                        'priemones' => trim($this->localizedEquipmentName($eq, $documentLocale)) ?: '-',
                        'terminas' => trim($this->localizedEquipmentExpiration($eq, $documentLocale)) ?: '-',
                        'unitOfMeasurement' => $unitNorm,
                        'kiekis' => (string) Equipment::normalizeDocumentQuantity($eq['quantity'] ?? 1),
                    ];
                }

                continue;
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
     * Galutinis .docx kelias po generated/, sutampantis su {@see CreateFile} (įmonės katalogas + AAP + failas).
     */
    private function resolveGeneratedAbsoluteAapDocumentPath(CompanyRequisite $company, string $outBasename): string
    {
        $seg = trim(str_replace('\\', '/', CreateFile::TEMPLATE_CATALOGUE_AAP), '/');

        return $this->resolveGeneratedAbsoluteOutputDir($company) . '/' . $seg . '/' . ltrim(str_replace('\\', '/', $outBasename), '/');
    }

    /**
     * Kaip {@see CreateFile::createDocxDocument}: `{šablonoVardasBePlėtinio}_{įmonėsSlug}.docx`.
     */
    private function buildAapGeneratedDocxBasename(CompanyRequisite $company, string $kind, string $documentLocale): string
    {
        $stem = $this->resolveAapOutputTemplateStem($kind, $documentLocale);
        $companySlug = $this->sanitizeForFilenameLikeCreateFile((string) $company->getCompanyName())
            ?: ((string) $company->getCode() !== '' ? (string) $company->getCode() : 'be_kodo');

        return $stem . '_' . $companySlug . '.docx';
    }

    /**
     * Šablono bazinis vardas (kaip CreateFile imą iš pathinfo(template)), ne stage_*.docx.
     */
    private function resolveAapOutputTemplateStem(string $kind, string $documentLocale): string
    {
        $loc = $this->normalizeAapLocale($documentLocale);
        $localeCandidates = $loc !== 'lt' ? [$loc, 'lt'] : ['lt'];

        foreach ($localeCandidates as $tryLoc) {
            $fs = $this->tryResolveFilesystemTemplate($kind, $tryLoc);
            if ($fs !== null) {
                $stem = pathinfo($fs, PATHINFO_FILENAME);

                return is_string($stem) && $stem !== '' ? $stem : $this->defaultAapTemplateStem($kind);
            }
        }

        return $this->defaultAapTemplateStem($kind);
    }

    private function defaultAapTemplateStem(string $kind): string
    {
        return $kind === self::OUTPUT_SARASAS ? 'AAP sąrašas' : 'AAP kortelės + žiniaraščiai';
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
        string $documentLocale = 'lt',
        array $customReplacements = []
    ): string {
        $outDir = $this->resolveGeneratedAbsoluteOutputDir($company);
        if (! is_dir($outDir) && ! mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
            throw new \RuntimeException('Nepavyko sukurti katalogo: ' . $outDir);
        }

        $outBasename = $this->buildAapGeneratedDocxBasename($company, $kind, $documentLocale);
        $outPath = $this->resolveGeneratedAbsoluteAapDocumentPath($company, $outBasename);
        $aapDir = dirname($outPath);
        if (! is_dir($aapDir) && ! mkdir($aapDir, 0775, true) && ! is_dir($aapDir)) {
            throw new \RuntimeException('Nepavyko sukurti katalogo: ' . $aapDir);
        }

        if ($kind === self::OUTPUT_KORTELES && $this->shouldMergeKortelesPerGroup($payload)) {
            $groups = $this->filterPayloadGroups($payload);
            $this->renderKortelesMergedToAbsolutePath(
                $company,
                $groups,
                $kortelesPagrindasOverride,
                $documentLocale,
                $outPath,
                $customReplacements
            );

            return $outPath;
        }

        $stagingPath = $this->createStagingTemplatePath();
        try {
            $templateMetaSource = $this->renderTemplate(
                $kind,
                $company,
                $tableRows,
                $stagingPath,
                $kortelesPagrindasOverride,
                $documentLocale
            );
            $generatedPath = $this->finalizeAapThroughCreateFile(
                $stagingPath,
                $company,
                $kind,
                $kortelesPagrindasOverride,
                $outBasename,
                $documentLocale,
                $templateMetaSource,
                $customReplacements
            );
            if ($generatedPath !== $outPath) {
                throw new \RuntimeException(
                    'Sugeneruotas kelias neatitinka laukto: ' . $generatedPath . ' (laukta ' . $outPath . ')'
                );
            }
        } finally {
            @unlink($stagingPath);
        }

        return $outPath;
    }

    /**
     * Kaip {@see renderToFinalPath}, bet papildomai kopijuoja į temp ZIP sujungimui.
     * Galutinis .docx lieka generated/ (anksčiau buvo ištrinamas — vartotojas nerado failų diske).
     *
     * @param array<string, mixed> $payload
     */
    private function renderToTempPath(
        string $kind,
        CompanyRequisite $company,
        array $tableRows,
        array $payload,
        ?string $kortelesPagrindasOverride = null,
        string $documentLocale = 'lt',
        array $customReplacements = []
    ): string {
        $tmpDir = $this->projectDir . '/var/aap-word-tmp';
        if (! is_dir($tmpDir) && ! mkdir($tmpDir, 0775, true) && ! is_dir($tmpDir)) {
            throw new \RuntimeException('Nepavyko sukurti laikino katalogo');
        }

        $tmpKindPrefix = $kind === self::OUTPUT_SARASAS ? 'sarasas_' : 'korteles_';
        $tmpBasename = $tmpKindPrefix . bin2hex(random_bytes(8)) . '.docx';
        $tmpPath = $tmpDir . '/' . $tmpBasename;

        $outDir = $this->resolveGeneratedAbsoluteOutputDir($company);
        if (! is_dir($outDir) && ! mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
            throw new \RuntimeException('Nepavyko sukurti katalogo: ' . $outDir);
        }

        $outBasename = $this->buildAapGeneratedDocxBasename($company, $kind, $documentLocale);
        $outPath = $this->resolveGeneratedAbsoluteAapDocumentPath($company, $outBasename);
        $aapDir = dirname($outPath);
        if (! is_dir($aapDir) && ! mkdir($aapDir, 0775, true) && ! is_dir($aapDir)) {
            throw new \RuntimeException('Nepavyko sukurti katalogo: ' . $aapDir);
        }

        if ($kind === self::OUTPUT_KORTELES && $this->shouldMergeKortelesPerGroup($payload)) {
            $groups = $this->filterPayloadGroups($payload);
            $this->renderKortelesMergedToAbsolutePath(
                $company,
                $groups,
                $kortelesPagrindasOverride,
                $documentLocale,
                $outPath,
                $customReplacements
            );
            if (! @copy($outPath, $tmpPath)) {
                throw new \RuntimeException('Nepavyko nukopijuoti ZIP dalies dokumento');
            }

            return $tmpPath;
        }

        $stagingPath = $this->createStagingTemplatePath();
        try {
            $templateMetaSource = $this->renderTemplate(
                $kind,
                $company,
                $tableRows,
                $stagingPath,
                $kortelesPagrindasOverride,
                $documentLocale
            );
            $generatedPath = $this->finalizeAapThroughCreateFile(
                $stagingPath,
                $company,
                $kind,
                $kortelesPagrindasOverride,
                $outBasename,
                $documentLocale,
                $templateMetaSource,
                $customReplacements
            );
            if ($generatedPath !== $outPath) {
                throw new \RuntimeException(
                    'Sugeneruotas kelias neatitinka laukto: ' . $generatedPath . ' (laukta ' . $outPath . ')'
                );
            }
            if (! @copy($generatedPath, $tmpPath)) {
                throw new \RuntimeException('Nepavyko nukopijuoti ZIP dalies dokumento');
            }
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
    /**
     * @return string Absoliutus kelias iki šablono (templateMetadataSourcePath CreateFile).
     */
    private function renderTemplate(
        string $kind,
        CompanyRequisite $company,
        array $tableRows,
        string $stagingOutputPath,
        ?string $kortelesPagrindasOverride = null,
        string $documentLocale = 'lt'
    ): string {
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
                    'priemones' => $r['priemones'],
                    'terminas' => $r['terminas'],
                    'kiekis' => $kiekisCell,
                    'vnt' => Equipment::documentUnitLabel($r['unitOfMeasurement'] ?? 'vnt', $langUpper),
                    'pagrindas' => $pagrindasText,
                    'Pagrindas' => $pagrindasText,
                    'PAGRINDAS' => mb_strtoupper($pagrindasText, 'UTF-8'),
                ];
            }
            try {
                $clonedAny = false;
                for ($region = 0; $region < 50; $region++) {
                    try {
                        $processor->cloneRowAndSetValues('priemones', $kortelesTableRows);
                        $clonedAny = true;
                    } catch (\Throwable) {
                        break;
                    }
                }
                if (! $clonedAny) {
                    throw new \RuntimeException('Nerasta ${priemones} eilutė kortelių šablone');
                }
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
            foreach ($tableRows as $r) {
                $sarasasRows[] = [
                    'pareigybe' => $r['pareigybe'],
                    'priemones' => $r['priemones'],
                    'terminas' => $r['terminas'],
                ];
            }
            try {
                if (! $this->cloneSarasasRowsWithMarkerVariants($processor, $sarasasRows)) {
                    throw new \RuntimeException('Nerasta sąrašo lentelės markerio eilutė');
                }
            } catch (\Throwable) {
                $this->applyOptionalMacroIfPresent(
                    $processor,
                    ['sarasas_turinys', 'sarasas_duomenys', 'aap_sarasas'],
                    $this->buildSarasasFreeformText($tableRows)
                );
            }
        }

        $processor->saveAs($stagingOutputPath);
        $this->convertPareigybeCellBreaksToParagraphsInDocx($stagingOutputPath);
        if ($kind === self::OUTPUT_SARASAS) {
            $this->mergeSarasasPareigybeColumnInDocx($stagingOutputPath);
            $this->normalizeSarasasRowBordersInDocx($stagingOutputPath);
        }

        if ($kind === self::OUTPUT_KORTELES) {
            $this->removeTableCellNoWrapFromDocx($stagingOutputPath);
        }

        if ($mergeKortelesPagrindasColumn && $kortelesPagrindasForMerge !== '') {
            try {
                $this->mergeKortelesPagrindasColumnInDocx($stagingOutputPath, $kortelesPagrindasForMerge);
            } catch (\Throwable) {
                // Best-effort: dokumentas vis tiek tinkamas, tik be vertikalaus suliejimo.
            }
        }

        return $working;
    }

    /**
     * @param list<array{pareigybe:string, priemones:string, terminas:string}> $rows
     */
    private function cloneSarasasRowsWithMarkerVariants(TemplateProcessor $processor, array $rows): bool
    {
        $baseMarkers = ['pareigybe', 'pareigybes', 'priemones'];
        $markers = [];
        foreach ($baseMarkers as $m) {
            $markers[] = $m;
            $markers[] = mb_strtolower($m, 'UTF-8');
            $markers[] = mb_strtoupper($m, 'UTF-8');
            $markers[] = mb_convert_case($m, MB_CASE_TITLE, 'UTF-8');
        }
        $markers = array_values(array_unique($markers));

        foreach ($markers as $marker) {
            try {
                $processor->cloneRowAndSetValues($marker, $rows);
                $this->applyIndexedSarasasValuesWithCaseVariants($processor, $rows);

                return true;
            } catch (\Throwable) {
            }
        }

        foreach ($markers as $marker) {
            try {
                $processor->cloneRow($marker, count($rows));
                $this->applyIndexedSarasasValuesWithCaseVariants($processor, $rows);

                return true;
            } catch (\Throwable) {
            }
        }

        return false;
    }

    /**
     * @param list<array{pareigybe:string, priemones:string, terminas:string}> $rows
     */
    private function applyIndexedSarasasValuesWithCaseVariants(TemplateProcessor $processor, array $rows): void
    {
        $idx = 1;
        foreach ($rows as $row) {
            $this->setIndexedSarasasPlaceholderWithCaseVariants($processor, 'pareigybe', $idx, $row['pareigybe']);
            $this->setIndexedSarasasPlaceholderWithCaseVariants($processor, 'pareigybes', $idx, $row['pareigybe']);
            $this->setIndexedSarasasPlaceholderWithCaseVariants($processor, 'priemones', $idx, $row['priemones']);
            $this->setIndexedSarasasPlaceholderWithCaseVariants($processor, 'terminas', $idx, $row['terminas']);
            $idx++;
        }
    }

    private function setIndexedSarasasPlaceholderWithCaseVariants(
        TemplateProcessor $processor,
        string $placeholder,
        int $index,
        string $value
    ): void {
        $variants = [
            $placeholder,
            mb_strtolower($placeholder, 'UTF-8'),
            mb_strtoupper($placeholder, 'UTF-8'),
            mb_convert_case($placeholder, MB_CASE_TITLE, 'UTF-8'),
        ];
        foreach (array_unique($variants) as $variant) {
            $val = $variant !== '' && $variant === mb_strtoupper($variant, 'UTF-8')
                ? mb_strtoupper($value, 'UTF-8')
                : $value;
            $processor->setValue($variant . '#' . $index, $val);
        }
    }

    private function createStagingTemplatePath(): string
    {
        $tmp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR . '/\\') . DIRECTORY_SEPARATOR
            . 'lpsk_aap_stage_' . bin2hex(random_bytes(8)) . '.docx';

        return $tmp;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function shouldMergeKortelesPerGroup(array $payload): bool
    {
        return count($this->filterPayloadGroups($payload)) >= 2;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    private function filterPayloadGroups(array $payload): array
    {
        $groups = $payload['groups'] ?? null;
        if (! is_array($groups)) {
            return [];
        }
        $out = [];
        foreach ($groups as $g) {
            if (is_array($g)) {
                $out[] = $g;
            }
        }

        return $out;
    }

    /**
     * Kortelėms su 2+ grupėmis: kiekvienai grupei pilnas šablonas + CreateFile, tada sujungiama į vieną .docx su puslapio lūžiu.
     *
     * @param list<array<string, mixed>> $groups
     */
    private function renderKortelesMergedToAbsolutePath(
        CompanyRequisite $company,
        array $groups,
        ?string $kortelesPagrindasOverride,
        string $documentLocale,
        string $absoluteOutputPath,
        array $customReplacements = []
    ): void {
        $outDir = dirname($absoluteOutputPath);
        $token = bin2hex(random_bytes(4));
        $partPaths = [];
        $stagingPath = null;

        try {
            foreach ($groups as $idx => $g) {
                $groupRows = $this->buildEquipmentTableRowsFromGroups([$g], $documentLocale, true);
                $stagingPath = $this->createStagingTemplatePath();
                $templateMetaSource = $this->renderTemplate(
                    self::OUTPUT_KORTELES,
                    $company,
                    $groupRows,
                    $stagingPath,
                    $kortelesPagrindasOverride,
                    $documentLocale
                );
                $partBase = 'aap_kort_part_' . $token . '_' . $idx . '.docx';
                $generatedPath = $this->finalizeAapThroughCreateFile(
                    $stagingPath,
                    $company,
                    self::OUTPUT_KORTELES,
                    $kortelesPagrindasOverride,
                    $partBase,
                    $documentLocale,
                    $templateMetaSource,
                    $customReplacements
                );
                @unlink($stagingPath);
                $stagingPath = null;
                if (! is_file($generatedPath) || ! is_readable($generatedPath)) {
                    throw new \RuntimeException('Nepavyko sugeneruoti AAP kortelių dalies: ' . $partBase);
                }
                $partPaths[] = $generatedPath;
            }

            if ($partPaths === []) {
                throw new \RuntimeException('Nėra grupių AAP kortelių sujungimui');
            }

            if (is_file($absoluteOutputPath)) {
                @unlink($absoluteOutputPath);
            }

            if (count($partPaths) === 1) {
                if (! @copy($partPaths[0], $absoluteOutputPath)) {
                    throw new \RuntimeException('Nepavyko išsaugoti AAP kortelių dokumento');
                }
                @unlink($partPaths[0]);
            } else {
                if (! @copy($partPaths[0], $absoluteOutputPath)) {
                    throw new \RuntimeException('Nepavyko išsaugoti AAP kortelių dokumento');
                }
                @unlink($partPaths[0]);
                for ($i = 1, $n = count($partPaths); $i < $n; $i++) {
                    $this->appendDocxBodyAfterPageBreak($absoluteOutputPath, $partPaths[$i]);
                    @unlink($partPaths[$i]);
                }
            }
        } finally {
            if ($stagingPath !== null) {
                @unlink($stagingPath);
            }
        }

    }

    /**
     * Prideda antrojo .docx turinį (word/document.xml body be paskutinio w:sectPr) po puslapio lūžio.
     * Tinka lentelėms ir tekstui; sudėtingi įterpti objektai / unikalūs relationship ID gali reikalauti papildomo sujungimo.
     */
    private function appendDocxBodyAfterPageBreak(string $intoPath, string $fromPath): void
    {
        $W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

        $zipInto = new ZipArchive();
        $zipFrom = new ZipArchive();
        if ($zipInto->open($intoPath) !== true) {
            throw new \RuntimeException('Nepavyko atidaryti DOCX sujungimui: ' . $intoPath);
        }
        if ($zipFrom->open($fromPath) !== true) {
            $zipInto->close();
            throw new \RuntimeException('Nepavyko atidaryti DOCX sujungimui: ' . $fromPath);
        }

        $xmlA = $zipInto->getFromName('word/document.xml');
        $xmlB = $zipFrom->getFromName('word/document.xml');
        $zipFrom->close();
        if ($xmlA === false || $xmlB === false || $xmlA === '' || $xmlB === '') {
            $zipInto->close();
            throw new \RuntimeException('Trūksta word/document.xml DOCX sujungimui');
        }

        $domA = new \DOMDocument();
        $domA->preserveWhiteSpace = false;
        if (@$domA->loadXML($xmlA, LIBXML_NONET) !== true) {
            $zipInto->close();
            throw new \RuntimeException('Netinkamas word/document.xml (pirmas dokumentas)');
        }
        $domB = new \DOMDocument();
        $domB->preserveWhiteSpace = false;
        if (@$domB->loadXML($xmlB, LIBXML_NONET) !== true) {
            $zipInto->close();
            throw new \RuntimeException('Netinkamas word/document.xml (antras dokumentas)');
        }

        $bodyA = $domA->getElementsByTagNameNS($W, 'body')->item(0);
        $bodyB = $domB->getElementsByTagNameNS($W, 'body')->item(0);
        if (! $bodyA instanceof \DOMElement || ! $bodyB instanceof \DOMElement) {
            $zipInto->close();
            throw new \RuntimeException('Nerastas w:body');
        }

        $sectPrA = null;
        foreach ($bodyA->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === $W && $child->localName === 'sectPr') {
                $sectPrA = $child;
            }
        }
        if (! $sectPrA instanceof \DOMElement) {
            $zipInto->close();
            throw new \RuntimeException('Nerastas w:sectPr pirmame dokumente');
        }

        $bChildren = [];
        foreach ($bodyB->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === $W && $child->localName === 'sectPr') {
                continue;
            }
            $bChildren[] = $child;
        }

        $bodyA->removeChild($sectPrA);

        $pageP = $domA->createElementNS($W, 'w:p');
        $pageR = $domA->createElementNS($W, 'w:r');
        $pageBr = $domA->createElementNS($W, 'w:br');
        $pageBr->setAttributeNS($W, 'w:type', 'page');
        $pageR->appendChild($pageBr);
        $pageP->appendChild($pageR);
        $bodyA->appendChild($pageP);

        foreach ($bChildren as $child) {
            $bodyA->appendChild($domA->importNode($child, true));
        }

        $bodyA->appendChild($sectPrA);

        $root = $domA->documentElement;
        if (! $root instanceof \DOMElement) {
            $zipInto->close();
            throw new \RuntimeException('Nepavyko suformuoti sujungto document.xml');
        }
        $mergedXml = $domA->saveXML($root);
        if (! is_string($mergedXml) || $mergedXml === '') {
            $zipInto->close();
            throw new \RuntimeException('Nepavyko serializuoti sujungto document.xml');
        }

        $zipInto->deleteName('word/document.xml');
        if ($zipInto->addFromString('word/document.xml', $mergedXml) !== true) {
            $zipInto->close();
            throw new \RuntimeException('Nepavyko įrašyti sujungto word/document.xml');
        }
        $zipInto->close();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreateFileDataForAap(
        CompanyRequisite $company,
        string $kind,
        ?string $kortelesPagrindasOverride,
        string $documentLocale = 'lt',
        array $customReplacements = []
    ): array {
        $documentDate = $company->getDocumentDate() ?? (new \DateTimeImmutable())->format('Y-m-d');

        $replacements = [];
        if ($kind === self::OUTPUT_KORTELES) {
            $ov = $kortelesPagrindasOverride !== null ? trim($kortelesPagrindasOverride) : '';
            $pagrindasText = $ov !== '' ? $ov : $company->resolveAapKortelesPagrindas();
            $replacements['pagrindas'] = $pagrindasText;
        }
        if ($customReplacements !== []) {
            $replacements = array_merge($replacements, $customReplacements);
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
        string $documentLocale = 'lt',
        ?string $templateMetadataSourcePath = null,
        array $customReplacements = [],
    ): string {
        $data = $this->buildCreateFileDataForAap(
            $company,
            $kind,
            $kortelesPagrindasOverride,
            $documentLocale,
            $customReplacements
        );
        $data['directory']                      = CreateFile::TEMPLATE_CATALOGUE_AAP;
        $data['template']                       = basename($stagingAbsolutePath);
        $data['templateAbsolutePath']           = $stagingAbsolutePath;
        $data['skipMirrorTemplatePathToOutput'] = false;
        $data['forceOutputCatalogueSegment']   = CreateFile::TEMPLATE_CATALOGUE_AAP;
        if ($templateMetadataSourcePath !== null && $templateMetadataSourcePath !== '') {
            $data['templateMetadataSourcePath'] = str_replace('\\', '/', $templateMetadataSourcePath);
        }

        return $this->createFile->createWordDocument($data, $outputBasename);
    }

    /**
     * Pašalina w:noWrap iš lentelių langelių — Word kitaip plečia stulpelį horizontaliai vietoj teksto perkėlimo į naują eilutę.
     * Apdoroja word/document.xml ir antraštes / poraštes (jei yra).
     */
    private function removeTableCellNoWrapFromDocx(string $docxPath): void
    {
        if (! is_file($docxPath) || ! is_readable($docxPath)) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $n = $zip->getNameIndex($i);
            if (is_string($n)) {
                $names[] = $n;
            }
        }

        foreach ($names as $name) {
            if ($name !== 'word/document.xml'
                && ! preg_match('#^word/header\\d+\\.xml$#', $name)
                && ! preg_match('#^word/footer\\d+\\.xml$#', $name)) {
                continue;
            }
            $xml = $zip->getFromName($name);
            if ($xml === false || $xml === '') {
                continue;
            }
            $fixed = preg_replace('#<w:noWrap(?:\\s[^>]*)?/>#u', '', $xml);
            $fixed = is_string($fixed) ? $fixed : $xml;
            $fixed = preg_replace('#<w:noWrap(?:\\s[^>]*)?></w:noWrap>#u', '', $fixed);
            $fixed = is_string($fixed) ? $fixed : $xml;
            if ($fixed !== $xml) {
                $zip->deleteName($name);
                $zip->addFromString($name, $fixed);
            }
        }

        $zip->close();
    }

    private function resolveWorkingTemplatePath(string $kind, string $documentLocale = 'lt'): string
    {
        $loc = $this->normalizeAapLocale($documentLocale);
        $localeCandidates = $loc !== 'lt' ? [$loc, 'lt'] : ['lt'];

        foreach ($localeCandidates as $tryLoc) {
            $fs = $this->tryResolveFilesystemTemplate($kind, $tryLoc);
            if ($fs !== null) {
                $this->addWordDocument->ensureTemplateCustomMetadata(
                    $fs,
                    basename($fs),
                    null,
                    FlowMacroIgnores::aapEquipmentWord()
                );

                return $fs;
            }
        }

        $label = $kind === self::OUTPUT_SARASAS ? 'AAP sąrašas' : 'AAP kortelės + žiniaraščiai';
        $kindLt = $kind === self::OUTPUT_SARASAS ? 'AAP sąrašas (sarasas)' : 'AAP kortelės + žiniaraščiai (korteles)';

        throw new \InvalidArgumentException(
            'Nerastas Word šablonas „' . $label . '.docx“ kalbai „' . $loc . '“ (tik templates/AAP/), '
            . 'tipui „' . $kindLt . '“. Įkelkite per admin „Šablonas“ (įrašys į templates/AAP) arba padėkite .docx rankiniu būdu į templates/AAP '
            . '(pvz. „' . $label . '.docx“ arba „' . $label . ' EN.docx“). '
            . 'Jei generuojate tik korteles, dokumentų kūrime nežymėkite „AAP sąrašas“.'
        );
    }

    /**
     * Absoliutus kelias iki .docx templates/AAP — PDF peržiūrai ir generavimui.
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
        $dir = $this->projectDir . '/templates/AAP';
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Nepavyko sukurti katalogo: ' . $dir);
        }

        $filename = $this->materializedDbAapTemplateFilename(
            $entity->getTemplateKind(),
            $entity->getTemplateLocale()
        );
        $path = $dir . '/' . $filename;

        $legacyName = $this->legacyMaterializedDbAapTemplateFilename(
            $entity->getTemplateKind(),
            $entity->getTemplateLocale()
        );
        if ($legacyName !== null) {
            $legacyPath = $dir . '/' . $legacyName;
            if ($legacyPath !== $path && is_file($legacyPath)) {
                @unlink($legacyPath);
            }
        }

        $technicalLegacy = $this->legacyTechnicalAapTemplateFilename(
            $entity->getTemplateKind(),
            $entity->getTemplateLocale()
        );
        if ($technicalLegacy !== null) {
            $technicalPath = $dir . '/' . $technicalLegacy;
            if ($technicalPath !== $path && is_file($technicalPath)) {
                @unlink($technicalPath);
            }
        }

        if (is_file($path) && is_readable($path)) {
            $existing = @file_get_contents($path);
            if ($existing !== false && $existing === $blob) {
                return $path;
            }
        }

        if (file_put_contents($path, $blob) === false) {
            throw new \RuntimeException('Nepavyko išsaugoti šablono iš DB: ' . $path);
        }

        return $path;
    }

    /**
     * Viena ${pareigybes} žyma šablone — kelios unikalios pareigybės sujungiamos per „ / “ (eilutės langelis gali turėti kelis tipus per eilutės lūžį).
     *
     * @param list<array{pareigybe: string, priemones: string, terminas: string}> $tableRows
     */
    private function buildPareigybesHeaderText(array $tableRows): string
    {
        $names = [];
        foreach ($tableRows as $r) {
            $raw = trim($r['pareigybe']);
            if ($raw === '' || $raw === '-') {
                continue;
            }
            $parts = preg_split('/\r\n|\r|\n/', $raw) ?: [];
            $parts = array_values(array_filter(array_map('trim', $parts), static fn (string $p): bool => $p !== '' && $p !== '-'));
            if ($parts === []) {
                continue;
            }
            foreach ($parts as $p) {
                $names[$p] = true;
            }
        }
        $list = array_keys($names);

        return $list === [] ? '-' : (count($list) === 1 ? $list[0] : implode(self::PAREIGYBES_DISPLAY_SEPARATOR, $list));
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

    /**
     * In some templates `${PAREIGYBE}` values contain multiple worker types in one cell.
     * TemplateProcessor inserts `\n` as `<w:br/>` (line break inside one paragraph), which
     * does not use paragraph spacing. Convert these breaks to real `<w:p>` paragraphs.
     */
    private function convertPareigybeCellBreaksToParagraphsInDocx(string $docxPath): void
    {
        if (! is_file($docxPath) || ! is_readable($docxPath)) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }
        $xml = $zip->getFromName('word/document.xml');
        if (! is_string($xml) || $xml === '') {
            $zip->close();
            return;
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        if (@$dom->loadXML($xml) !== true) {
            $zip->close();
            return;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::OOXML_W_NS);

        $tables = $xpath->query('//w:tbl');
        if ($tables === false) {
            $zip->close();
            return;
        }

        foreach ($tables as $tbl) {
            if (! $tbl instanceof \DOMElement) {
                continue;
            }

            $rowsNode = $xpath->query('w:tr', $tbl);
            if ($rowsNode === false || $rowsNode->length < 2) {
                continue;
            }

            $rows = [];
            foreach ($rowsNode as $tr) {
                if ($tr instanceof \DOMElement) {
                    $rows[] = $tr;
                }
            }
            if (count($rows) < 2) {
                continue;
            }

            $header = $rows[0];
            $pareigybeCol = $this->findPareigybeHeaderColumn($header);
            if ($pareigybeCol === null) {
                continue;
            }

            for ($i = 1, $n = count($rows); $i < $n; $i++) {
                $cell = $this->getTableCellAtLogicalColumn($rows[$i], $pareigybeCol);
                if (! $cell instanceof \DOMElement) {
                    continue;
                }
                $this->convertLineBreaksToParagraphsInTableCell($dom, $cell);
            }
        }

        $out = $dom->saveXML();
        if (! is_string($out) || $out === '') {
            $zip->close();
            return;
        }

        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $out);
        $zip->close();
    }

    private function findPareigybeHeaderColumn(\DOMElement $headerRow): ?int
    {
        $cells = $this->trDirectTableCells($headerRow);
        $logicalCol = 0;
        foreach ($cells as $cell) {
            $text = mb_strtolower(trim($this->extractPlainTextFromTableCell($cell)), 'UTF-8');
            if ($text !== '' && (
                str_contains($text, 'pareigybe')
                || str_contains($text, 'pareigybė')
                || str_contains($text, 'pareigos')
                || str_contains($text, 'worker type')
            )) {
                return $logicalCol;
            }
            $logicalCol += $this->tableCellGridSpan($cell);
        }

        return null;
    }

    private function convertLineBreaksToParagraphsInTableCell(\DOMDocument $dom, \DOMElement $tc): void
    {
        $paragraphs = $this->tcDirectParagraphs($tc);
        if ($paragraphs === []) {
            return;
        }

        $firstRunProps = null;
        foreach ($paragraphs as $p) {
            foreach ($p->childNodes as $child) {
                if (! $child instanceof \DOMElement || $child->namespaceURI !== self::OOXML_W_NS || $child->localName !== 'r') {
                    continue;
                }
                foreach ($child->childNodes as $rn) {
                    if ($rn instanceof \DOMElement && $rn->namespaceURI === self::OOXML_W_NS && $rn->localName === 'rPr') {
                        $firstRunProps = $rn;
                        break 3;
                    }
                }
            }
        }

        $segments = [];
        foreach ($paragraphs as $p) {
            $line = '';
            foreach ($p->childNodes as $child) {
                if (! $child instanceof \DOMElement || $child->namespaceURI !== self::OOXML_W_NS || $child->localName !== 'r') {
                    continue;
                }
                foreach ($child->childNodes as $rn) {
                    if (! $rn instanceof \DOMElement || $rn->namespaceURI !== self::OOXML_W_NS) {
                        continue;
                    }
                    if ($rn->localName === 't') {
                        $line .= $rn->textContent;
                    } elseif ($rn->localName === 'br') {
                        $segments[] = $line;
                        $line = '';
                    }
                }
            }
            $segments[] = $line;
        }

        $hasBreaks = false;
        foreach ($paragraphs as $p) {
            if ($p->getElementsByTagNameNS(self::OOXML_W_NS, 'br')->length > 0) {
                $hasBreaks = true;
                break;
            }
        }
        if (! $hasBreaks) {
            return;
        }

        $normalized = [];
        foreach ($segments as $s) {
            $normalized[] = trim((string) $s);
        }
        $normalized = array_values(array_filter($normalized, static fn (string $v): bool => $v !== ''));
        if ($normalized === []) {
            return;
        }

        $firstPPr = null;
        foreach ($paragraphs[0]->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === self::OOXML_W_NS && $child->localName === 'pPr') {
                $firstPPr = $child;
                break;
            }
        }

        foreach ($paragraphs as $p) {
            $tc->removeChild($p);
        }

        foreach ($normalized as $lineText) {
            $p = $dom->createElementNS(self::OOXML_W_NS, 'w:p');
            if ($firstPPr instanceof \DOMElement) {
                $p->appendChild($firstPPr->cloneNode(true));
            }
            $r = $dom->createElementNS(self::OOXML_W_NS, 'w:r');
            if ($firstRunProps instanceof \DOMElement) {
                $r->appendChild($firstRunProps->cloneNode(true));
            }
            $t = $dom->createElementNS(self::OOXML_W_NS, 'w:t');
            if (preg_match('/^\s|\s$/u', $lineText) === 1) {
                $t->setAttribute('xml:space', 'preserve');
            }
            $t->appendChild($dom->createTextNode($lineText));
            $r->appendChild($t);
            $p->appendChild($r);
            $tc->appendChild($p);
        }
    }

    /**
     * @return list<\DOMElement>
     */
    private function tcDirectParagraphs(\DOMElement $tc): array
    {
        $out = [];
        foreach ($tc->childNodes as $n) {
            if ($n instanceof \DOMElement && $n->namespaceURI === self::OOXML_W_NS && $n->localName === 'p') {
                $out[] = $n;
            }
        }

        return $out;
    }

    /**
     * For AAP "sarasas" layout: keep `${priemones}` / `${terminas}` as separate rows, but merge
     * `${PAREIGYBE}` vertically so neighboring columns can list items row-by-row.
     * Also merge the immediate left column with the same span so both cells keep equal height.
     */
    private function mergeSarasasPareigybeColumnInDocx(string $docxPath): void
    {
        if (! is_file($docxPath) || ! is_readable($docxPath)) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }
        $xml = $zip->getFromName('word/document.xml');
        if (! is_string($xml) || $xml === '') {
            $zip->close();
            return;
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        if (@$dom->loadXML($xml) !== true) {
            $zip->close();
            return;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::OOXML_W_NS);
        $tables = $xpath->query('//w:tbl');
        if ($tables === false) {
            $zip->close();
            return;
        }

        foreach ($tables as $tbl) {
            if (! $tbl instanceof \DOMElement) {
                continue;
            }

            $rowsNode = $xpath->query('w:tr', $tbl);
            if ($rowsNode === false || $rowsNode->length < 2) {
                continue;
            }

            $rows = [];
            foreach ($rowsNode as $tr) {
                if ($tr instanceof \DOMElement) {
                    $rows[] = $tr;
                }
            }
            if (count($rows) < 2) {
                continue;
            }

            $pareigybeCol = $this->findPareigybeHeaderColumn($rows[0]);
            if ($pareigybeCol === null) {
                continue;
            }
            $leftCol = $pareigybeCol > 0 ? $pareigybeCol - 1 : null;
            $dataRows = array_slice($rows, 1);
            $runPareigybeCells = [];
            $runLeftCells = [];
            $runKey = null;

            $flush = function () use (&$runPareigybeCells, &$runLeftCells, $dom): void {
                if ($runPareigybeCells === []) {
                    $runPareigybeCells = [];
                    $runLeftCells = [];
                    return;
                }
                if (count($runPareigybeCells) > 1) {
                    $this->applyVerticalMergeToTableCells($dom, $runPareigybeCells);
                }
                if (count($runLeftCells) > 1) {
                    $this->applyVerticalMergeToTableCells($dom, $runLeftCells);
                }
                $runPareigybeCells = [];
                $runLeftCells = [];
            };

            foreach ($dataRows as $tr) {
                $tc = $this->getTableCellAtLogicalColumn($tr, $pareigybeCol);
                if (! $tc instanceof \DOMElement) {
                    $flush();
                    $runKey = null;
                    continue;
                }

                $text = trim($this->extractPlainTextFromTableCell($tc));
                // Merge groups must follow PAREIGYBE block spans only.
                $key = mb_strtolower($text, 'UTF-8');
                if ($key === '' || $key === '-') {
                    $flush();
                    $runKey = null;
                    continue;
                }

                if ($runKey === null || $runKey !== $key) {
                    $flush();
                    $runKey = $key;
                    $runPareigybeCells = [$tc];
                    if ($leftCol !== null) {
                        $leftCell = $this->getTableCellAtLogicalColumn($tr, $leftCol);
                        if ($leftCell instanceof \DOMElement) {
                            $runLeftCells = [$leftCell];
                        }
                    }
                    continue;
                }

                $runPareigybeCells[] = $tc;
                if ($leftCol !== null) {
                    $leftCell = $this->getTableCellAtLogicalColumn($tr, $leftCol);
                    if ($leftCell instanceof \DOMElement) {
                        $runLeftCells[] = $leftCell;
                    }
                }
            }
            $flush();
        }

        $out = $dom->saveXML();
        if (! is_string($out) || $out === '') {
            $zip->close();
            return;
        }

        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $out);
        $zip->close();
    }

    /**
     * For AAP sarasas rows, remove per-cell top/bottom borders only in PRIEMONES/TERMINAS inner data rows.
     * Keep outer table border (first-row top, last-row bottom) and do not touch PAREIGYBE column borders.
     */
    private function normalizeSarasasRowBordersInDocx(string $docxPath): void
    {
        if (! is_file($docxPath) || ! is_readable($docxPath)) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }
        $xml = $zip->getFromName('word/document.xml');
        if (! is_string($xml) || $xml === '') {
            $zip->close();
            return;
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        if (@$dom->loadXML($xml) !== true) {
            $zip->close();
            return;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::OOXML_W_NS);
        $tables = $xpath->query('//w:tbl');
        if ($tables === false) {
            $zip->close();
            return;
        }

        foreach ($tables as $tbl) {
            if (! $tbl instanceof \DOMElement) {
                continue;
            }
            $rowsNode = $xpath->query('w:tr', $tbl);
            if ($rowsNode === false || $rowsNode->length < 2) {
                continue;
            }

            $rows = [];
            foreach ($rowsNode as $tr) {
                if ($tr instanceof \DOMElement) {
                    $rows[] = $tr;
                }
            }
            if (count($rows) < 2) {
                continue;
            }

            $header = $rows[0];
            $priemonesCol = $this->findHeaderColumnByKeywords($header, ['priemones', 'priemonės', 'aap']);
            $terminasCol = $this->findHeaderColumnByKeywords($header, ['terminas', 'terminai']);

            if ($priemonesCol === null && $terminasCol === null) {
                continue;
            }

            $pareigybeCol = $this->findPareigybeHeaderColumn($header);
            if ($pareigybeCol === null) {
                continue;
            }

            // Group rows by PAREIGYBE merged blocks: row with non-empty PAREIGYBE starts a group.
            $groups = [];
            $groupStart = null;
            for ($i = 1, $n = count($rows); $i < $n; $i++) {
                $pCell = $this->getTableCellAtLogicalColumn($rows[$i], $pareigybeCol);
                $pText = $pCell instanceof \DOMElement ? trim($this->extractPlainTextFromTableCell($pCell)) : '';
                if ($pText !== '') {
                    if ($groupStart !== null) {
                        $groups[] = [$groupStart, $i - 1];
                    }
                    $groupStart = $i;
                }
            }
            if ($groupStart !== null) {
                $groups[] = [$groupStart, count($rows) - 1];
            }
            if ($groups === []) {
                continue;
            }

            foreach ($groups as [$groupFrom, $groupTo]) {
                $targets = array_filter([$priemonesCol, $terminasCol], static fn ($v): bool => $v !== null);
                for ($i = $groupFrom; $i <= $groupTo; $i++) {
                    foreach ($targets as $col) {
                        $tc = $this->getTableCellAtLogicalColumn($rows[$i], (int) $col);
                        if (! $tc instanceof \DOMElement) {
                            continue;
                        }
                        if ($i > $groupFrom) {
                            $this->setTableCellBorder($dom, $tc, 'top', 'nil');
                        }
                        if ($i < $groupTo) {
                            $this->setTableCellBorder($dom, $tc, 'bottom', 'nil');
                        } else {
                            // End of each worker group keeps visible bottom border.
                            $this->setTableCellBorder($dom, $tc, 'bottom', 'single');
                        }
                    }
                }
            }
        }

        $out = $dom->saveXML();
        if (! is_string($out) || $out === '') {
            $zip->close();
            return;
        }

        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $out);
        $zip->close();
    }

    private function findHeaderColumnByKeywords(\DOMElement $headerRow, array $keywords): ?int
    {
        $cells = $this->trDirectTableCells($headerRow);
        $logicalCol = 0;
        foreach ($cells as $cell) {
            $text = mb_strtolower(trim($this->extractPlainTextFromTableCell($cell)), 'UTF-8');
            foreach ($keywords as $kw) {
                if ($text !== '' && str_contains($text, mb_strtolower($kw, 'UTF-8'))) {
                    return $logicalCol;
                }
            }
            $logicalCol += $this->tableCellGridSpan($cell);
        }

        return null;
    }


    private function setTableCellBorder(\DOMDocument $dom, \DOMElement $tc, string $side, string $value): void
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

        $tcBorders = null;
        foreach ($tcPr->childNodes as $n) {
            if ($n instanceof \DOMElement && $n->namespaceURI === self::OOXML_W_NS && $n->localName === 'tcBorders') {
                $tcBorders = $n;
                break;
            }
        }
        if (! $tcBorders instanceof \DOMElement) {
            $tcBorders = $dom->createElementNS(self::OOXML_W_NS, 'w:tcBorders');
            $tcPr->appendChild($tcBorders);
        }

        $edge = null;
        foreach ($tcBorders->childNodes as $n) {
            if ($n instanceof \DOMElement && $n->namespaceURI === self::OOXML_W_NS && $n->localName === $side) {
                $edge = $n;
                break;
            }
        }
        if (! $edge instanceof \DOMElement) {
            $edge = $dom->createElementNS(self::OOXML_W_NS, 'w:' . $side);
            $tcBorders->appendChild($edge);
        }
        $edge->setAttribute('w:val', $value);
    }

}