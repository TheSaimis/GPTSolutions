<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\CompanyRequisite;
use App\Entity\Worker;
use App\Entity\WorkerRisk;
use App\Services\Metadata\DocxMetadataService;
use App\Services\Metadata\FlowMacroIgnores;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Atskiro dokumento:
 * "DARBUOTOJU DARBO VIETU KENKSMINGU FAKTORIU NUSTATYMO PAZYMA"
 * generavimo logika.
 *
 * Custom property `documentData` stores only values used to fill template placeholders (check periods,
 * user context, name, custom replacements, `workerRows`). Kind is `templateType`; template file path is
 * `templatePath` — both separate OOXML properties. Company letterhead still comes from {@see CompanyRequisite}.
 */
final class WorkplaceFactorsCertificateService
{
    /** Stored in custom property `templateType` (not inside `documentData` JSON). */
    public const TEMPLATE_TYPE = 'healthCertificate';

    /** @deprecated Use {@see TEMPLATE_TYPE} */
    public const DOCUMENT_TYPE = self::TEMPLATE_TYPE;

    private const HEALTH_ROW_MARKER_KEYS = [
        'eilNr',
        'pareigybe',
        'veiksniai',
        'sifrai',
        'veiksniaiSuSifrais',
        'veiksniaiPilnas',
        'periodiskumas',
        'workerType',
        'riskFactors',
        'riskCodes',
        'riskFactorsWithCodes',
        'checkPeriod',
    ];

    /** Numatytasis sugeneruoto .docx vardas (be plėtinio) — taip pat fillFileBulk, kai šablonas pažymos .docx. */
    public const OUTPUT_DOCUMENT_STEM = 'Sveikatos tikrinimo pazyma + knyga';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CreateFile $createFile,
        private readonly DocxMetadataService $docxMetadataService,
        private readonly AddWordDocument $addWordDocument,
        private readonly string $projectDir,
    ) {}

    /**
     * @param array<string, mixed> $userContext
     * @param array<string, mixed> $customReplacements
     * @param array<string, mixed>|null $documentDataOverride Optional fill-only replay JSON. Non-empty `workerRows`
     *        skips WorkerRisk queries. Older blobs may still include `companyId` / `templatePath` / `documentType`; those are respected.
     */
    public function createDocument(
        int $companyId,
        string $templatePath,
        array $checkPeriodsByWorkerId = [],
        array $userContext = [],
        ?string $name = null,
        array $customReplacements = [],
        ?array $documentDataOverride = null,
    ): string {
        $effective = $this->resolveEffectiveCertificateArguments(
            $companyId,
            $templatePath,
            $checkPeriodsByWorkerId,
            $userContext,
            $name,
            $customReplacements,
            $documentDataOverride
        );

        /** @var CompanyRequisite|null $company */
        $company = $this->em->getRepository(CompanyRequisite::class)->find($effective['companyId']);
        if (! $company instanceof CompanyRequisite) {
            throw new \InvalidArgumentException('Įmonė nerasta');
        }

        // rawurldecode — ne urldecode: urldecode „+“ paverčia tarpu ir sugadina kelius su „+“ faile.
        $templatePathNorm = str_replace('\\', '/', rawurldecode(trim((string) $effective['templatePath'])));
        if ($templatePathNorm === '' || str_contains($templatePathNorm, '..') || str_starts_with($templatePathNorm, '/')) {
            throw new \InvalidArgumentException('Invalid template path');
        }

        $template = basename($templatePathNorm);
        $templatesRoot    = str_replace('\\', '/', rtrim($this->projectDir, '/\\')) . '/templates';
        $fullTemplatePath = $templatesRoot . '/' . $templatePathNorm;
        if (! is_file($fullTemplatePath) || ! is_readable($fullTemplatePath)) {
            throw new \InvalidArgumentException('Šablonas nerastas: templates/' . $templatePathNorm);
        }

        $this->addWordDocument->ensureTemplateCustomMetadata(
            $fullTemplatePath,
            $template,
            null,
            FlowMacroIgnores::healthCertificate()
        );

        $workerRowsDb = null;
        if ($effective['useWorkerSnapshot']) {
            $numberedRows = $this->numberedRowsFromWorkerSnapshot($effective['workerRowsSnapshot']);
        } else {
            $workerRowsDb = $this->buildWorkerRows($company, $effective['checkPeriodsByWorkerId']);
            $numberedRows = [];
            foreach ($workerRowsDb as $index => $row) {
                $numberedRows[] = [
                    'eilNr' => (string) ($index + 1),
                    'workerName' => $row['workerName'],
                    'riskNames' => $row['riskNames'],
                    'riskCodes' => $row['riskCodes'],
                    'riskWithCodes' => $row['riskWithCodes'],
                    'checkPeriod' => $row['checkPeriod'],
                ];
            }
        }

        $healthReplacements = $this->buildHealthReplacementsFromNumberedRows($numberedRows);
        $createFileReplacements = array_diff_key($healthReplacements, array_flip(self::HEALTH_ROW_MARKER_KEYS));

        $companyData = $this->buildCompanyData($company, $effective['userContext']);
        $companyData['directory']                 = CreateFile::TEMPLATE_CATALOGUE_AAP;
        $companyData['template']                  = $template;
        $companyData['templateAbsolutePath']      = $fullTemplatePath;
        $companyData['templateMetadataSourcePath'] = $fullTemplatePath;
        $companyData['forceOutputCatalogueSegment'] = CreateFile::TEMPLATE_CATALOGUE_AAP;
        $companyData['language']                  = $this->createFile->inferLanguageFromTemplatesRelativePath($templatePathNorm);
        $companyData['replacements']              = array_merge($createFileReplacements, $effective['customReplacements']);

        $documentDataPayload = [
            'checkPeriodsByWorkerId' => $effective['checkPeriodsByWorkerId'],
            'userContext' => $effective['userContext'],
            'name' => $effective['name'],
            'customReplacements' => $effective['customReplacements'],
            'workerRows' => $effective['useWorkerSnapshot']
                ? $effective['workerRowsSnapshot']
                : $this->workerDbRowsToSnapshotRows($workerRowsDb ?? []),
        ];

        $outputPath = $this->createFile->createWordDocument($companyData, $effective['name']);
        $this->applyWorkerRowsToOutput($outputPath, $numberedRows, $healthReplacements);

        $documentDataJson = json_encode(
            $documentDataPayload,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION
        );
        $this->docxMetadataService->setDocxCustomProperties($outputPath, [
            'documentData' => $documentDataJson,
            'templateType' => self::TEMPLATE_TYPE,
            'templatePath' => $effective['templatePath'],
        ]);

        return $outputPath;
    }

    /**
     * @return array{
     *   companyId:int,
     *   templatePath:string,
     *   checkPeriodsByWorkerId:array<int, string>,
     *   userContext:array<string, mixed>,
     *   name:?string,
     *   customReplacements:array<string, mixed>,
     *   useWorkerSnapshot:bool,
     *   workerRowsSnapshot:array<int, array<string, mixed>>|null
     * }
     */
    private function resolveEffectiveCertificateArguments(
        int $companyId,
        string $templatePath,
        array $checkPeriodsByWorkerId,
        array $userContext,
        ?string $name,
        array $customReplacements,
        ?array $documentDataOverride,
    ): array {
        if ($documentDataOverride === null) {
            $templateBasename = basename(str_replace('\\', '/', $templatePath));

            return [
                'companyId' => $companyId,
                'templatePath' => $templatePath,
                'checkPeriodsByWorkerId' => $this->normalizeWorkerIdStringMap($checkPeriodsByWorkerId),
                'userContext' => $userContext,
                'name' => $this->resolveHealthCertificateOutputStem($name, $templateBasename),
                'customReplacements' => $customReplacements,
                'useWorkerSnapshot' => false,
                'workerRowsSnapshot' => null,
            ];
        }

        $o = $documentDataOverride;
        $effCompanyId = isset($o['companyId']) ? (int) $o['companyId'] : $companyId;
        if ($effCompanyId !== $companyId) {
            throw new \InvalidArgumentException('documentData.companyId must match the request companyId');
        }

        $workerRowsRaw = $o['workerRows'] ?? null;
        $useWorkerSnapshot = is_array($workerRowsRaw) && $workerRowsRaw !== [];

        $effName = $name;
        if (array_key_exists('name', $o)) {
            $n = $o['name'];
            $effName = ($n === null || is_string($n)) ? $n : $name;
        }

        $effTemplatePath = isset($o['templatePath']) ? (string) $o['templatePath'] : $templatePath;
        $templateBasename = basename(str_replace('\\', '/', $effTemplatePath));

        return [
            'companyId' => $effCompanyId,
            'templatePath' => $effTemplatePath,
            'checkPeriodsByWorkerId' => isset($o['checkPeriodsByWorkerId']) && is_array($o['checkPeriodsByWorkerId'])
                ? $this->normalizeWorkerIdStringMap($o['checkPeriodsByWorkerId'])
                : $this->normalizeWorkerIdStringMap($checkPeriodsByWorkerId),
            'userContext' => isset($o['userContext']) && is_array($o['userContext'])
                ? $o['userContext']
                : $userContext,
            'name' => $this->resolveHealthCertificateOutputStem(
                is_string($effName) || $effName === null ? $effName : null,
                $templateBasename
            ),
            'customReplacements' => isset($o['customReplacements']) && is_array($o['customReplacements'])
                ? $o['customReplacements']
                : $customReplacements,
            'useWorkerSnapshot' => $useWorkerSnapshot,
            'workerRowsSnapshot' => $useWorkerSnapshot ? array_values($workerRowsRaw) : null,
        ];
    }

    /**
     * Jei `documentData.name` ar kitur lieka šablono vardas (pvz. „pazyma.docx“), CreateFile išvestį pavadintų
     * „pazyma.docx“ — todėl tokius atvejus laikome tuščiais ir naudojame {@see OUTPUT_DOCUMENT_STEM}.
     *
     * @param non-empty-string $templateFileBasename pvz. „pazyma.docx“
     */
    private function resolveHealthCertificateOutputStem(?string $name, string $templateFileBasename): string
    {
        $templateStem = pathinfo($templateFileBasename, PATHINFO_FILENAME);
        $templateStem = is_string($templateStem) ? $templateStem : '';

        $t = $name !== null ? trim($name) : '';
        if ($t === '') {
            return self::OUTPUT_DOCUMENT_STEM;
        }

        $candidateStem = pathinfo(str_replace('\\', '/', $t), PATHINFO_FILENAME);
        $candidateStem = is_string($candidateStem) ? $candidateStem : '';

        if ($candidateStem === '' || ($templateStem !== '' && strcasecmp($candidateStem, $templateStem) === 0)) {
            return self::OUTPUT_DOCUMENT_STEM;
        }

        return $t;
    }

    /**
     * @param array<int|string, mixed> $raw
     * @return array<int, string>
     */
    private function normalizeWorkerIdStringMap(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            if (! is_numeric((string) $k)) {
                continue;
            }
            $out[(int) $k] = trim((string) $v);
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function workerDbRowsToSnapshotRows(array $rows): array
    {
        return array_values(array_map(static function (array $row): array {
            return [
                'workerId' => $row['workerId'] ?? null,
                'workerName' => $row['workerName'],
                'riskNames' => $row['riskNames'],
                'riskCodes' => $row['riskCodes'],
                'riskWithCodes' => $row['riskWithCodes'],
                'checkPeriod' => $row['checkPeriod'],
            ];
        }, $rows));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{
     *   eilNr:string,
     *   workerName:string,
     *   riskNames:string,
     *   riskCodes:string,
     *   riskWithCodes:string,
     *   checkPeriod:string
     * }>
     */
    private function numberedRowsFromWorkerSnapshot(array $rows): array
    {
        $numberedRows = [];
        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                throw new \InvalidArgumentException('Each documentData.workerRows entry must be an object');
            }

            $workerName = trim((string) ($row['workerName'] ?? $row['pareigybe'] ?? $row['pareigybė'] ?? ''));
            if ($workerName === '') {
                throw new \InvalidArgumentException('Each workerRows entry must include workerName');
            }

            $riskNames = trim((string) ($row['riskNames'] ?? $row['veiksniai'] ?? ''));
            $riskCodes = trim((string) ($row['riskCodes'] ?? $row['sifrai'] ?? ''));
            $riskWithCodes = trim((string) ($row['riskWithCodes'] ?? $row['veiksniaiSuSifrais'] ?? ''));
            $checkPeriod = trim((string) ($row['checkPeriod'] ?? $row['periodiskumas'] ?? ''));

            if ($riskNames === '') {
                $riskNames = '-';
            }
            if ($riskCodes === '') {
                $riskCodes = '-';
            }
            if ($riskWithCodes === '') {
                $riskWithCodes = '-';
            }
            $numberedRows[] = [
                'eilNr' => (string) ($index + 1),
                'workerName' => $workerName,
                'riskNames' => $riskNames,
                'riskCodes' => $riskCodes,
                'riskWithCodes' => $riskWithCodes,
                'checkPeriod' => $checkPeriod,
            ];
        }

        return $numberedRows;
    }

    /**
     * @param array<int, array{
     *   eilNr:string,
     *   workerName:string,
     *   riskNames:string,
     *   riskCodes:string,
     *   riskWithCodes:string,
     *   checkPeriod:string
     * }> $numberedRows
     * @return array<string, string>
     */
    private function buildHealthReplacementsFromNumberedRows(array $numberedRows): array
    {
        $eilNrLines = array_map(
            static fn (array $row): string => (string) $row['eilNr'],
            $numberedRows
        );
        $pareigybeLines = array_map(
            static fn (array $row): string => (string) $row['workerName'],
            $numberedRows
        );
        $veiksniaiLines = array_map(
            static fn (array $row): string => (string) $row['riskNames'],
            $numberedRows
        );
        $sifraiLines = array_map(
            static fn (array $row): string => (string) $row['riskCodes'],
            $numberedRows
        );
        $veiksniaiSuSifraisLines = array_map(
            static fn (array $row): string => (string) $row['riskWithCodes'],
            $numberedRows
        );
        $periodiskumasLines = array_map(
            static fn (array $row): string => (string) $row['checkPeriod'],
            $numberedRows
        );

        $tableRows = array_map(
            static fn (array $row): string => implode(' | ', [
                $row['eilNr'],
                $row['workerName'],
                $row['riskNames'],
                $row['riskCodes'],
                $row['checkPeriod'],
            ]),
            $numberedRows
        );

        $factorsPerWorker = array_map(
            static fn (array $row): string => $row['workerName'] . ': ' . $row['riskNames'],
            $numberedRows
        );
        $codesPerWorker = array_map(
            static fn (array $row): string => $row['workerName'] . ': ' . $row['riskCodes'],
            $numberedRows
        );
        $factorsWithCodesPerWorker = array_map(
            static fn (array $row): string => $row['workerName'] . ': ' . $row['riskWithCodes'],
            $numberedRows
        );
        $periodsPerWorker = array_map(
            static fn (array $row): string => $row['workerName'] . ': ' . $row['checkPeriod'],
            $numberedRows
        );

        return [
            'eilNr'                     => implode("\n", $eilNrLines),
            'pareigybe'                 => implode("\n", $pareigybeLines),
            'veiksniai'                 => implode("\n", $veiksniaiLines),
            'sifrai'                    => implode("\n", $sifraiLines),
            'veiksniaiSuSifrais'        => implode("\n", $veiksniaiSuSifraisLines),
            'veiksniaiPilnas'           => implode("\n", $factorsWithCodesPerWorker),
            'periodiskumas'             => implode("\n", $periodiskumasLines),

            'workerType'                => implode("\n", $pareigybeLines),
            'riskFactors'               => implode("\n", $veiksniaiLines),
            'riskCodes'                 => implode("\n", $sifraiLines),
            'riskFactorsWithCodes'      => implode("\n", $veiksniaiSuSifraisLines),
            'checkPeriod'               => implode("\n", $periodiskumasLines),

            'sveikatosRizikosVeiksniai'  => implode("\n", $factorsPerWorker),
            'sveikatosRizikosSifrai'     => implode("\n", $codesPerWorker),
            'sveikatosRizikosLentele'    => implode("\n", $tableRows),
            'sveikatosTerminas'          => implode("\n", $periodsPerWorker),
            'rizikosVeiksniai'           => implode("\n", $factorsPerWorker),
            'rizikosSifrai'              => implode("\n", $codesPerWorker),
            'rizikosLentele'             => implode("\n", $tableRows),
            'terminas'                   => implode("\n", $periodsPerWorker),
            'healthRiskFactors'          => implode("\n", $factorsPerWorker),
            'healthRiskCodes'            => implode("\n", $codesPerWorker),
            'healthRiskTable'            => implode("\n", $tableRows),
            'healthCheckTerm'            => implode("\n", $periodsPerWorker),
            'workerRiskRows'             => implode("\n", $tableRows),
            'workerRiskRowsText'         => implode("\n", $tableRows),
            'workerRiskFactorsByType'    => implode("\n", $factorsPerWorker),
            'workerRiskCiphersByType'    => implode("\n", $codesPerWorker),
            'workerCheckPeriodsByType'   => implode("\n", $periodsPerWorker),
        ];
    }

    /**
     * @param array<int|string, mixed> $checkPeriodsByWorkerId
     * @return array<int, array{
     *   workerId:int,
     *   workerName:string,
     *   riskNames:string,
     *   riskCodes:string,
     *   riskWithCodes:string,
     *   checkPeriod:string
     * }>
     */
    private function buildWorkerRows(CompanyRequisite $company, array $checkPeriodsByWorkerId): array
    {
        $workers = [];
        foreach ($company->getCompanyWorkers() as $companyWorker) {
            $worker = $companyWorker->getWorker();
            if ($worker instanceof Worker && $worker->getId() !== null) {
                $workers[$worker->getId()] = $worker;
            }
        }

        if ($workers === []) {
            throw new \InvalidArgumentException('Įmonei nepriskirti darbuotojų tipai');
        }

        $normalizedPeriods = [];
        foreach ($workers as $workerId => $worker) {
            $period = isset($checkPeriodsByWorkerId[$workerId]) ? trim((string) $checkPeriodsByWorkerId[$workerId]) : '';
            if ($period !== '') {
                $normalizedPeriods[$workerId] = $period;
            }
        }

        $riskRows = $this->em->getRepository(WorkerRisk::class)
            ->createQueryBuilder('wr')
            ->leftJoin('wr.worker', 'w')
            ->leftJoin('wr.riskFactor', 'f')
            ->addSelect('w', 'f')
            ->where('w.id IN (:workerIds)')
            ->setParameter('workerIds', array_keys($workers))
            ->addOrderBy('w.name', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();

        $factorsByWorker = [];
        /** @var WorkerRisk $riskRow */
        foreach ($riskRows as $riskRow) {
            $worker = $riskRow->getWorker();
            $factor = $riskRow->getRiskFactor();
            if (! $worker instanceof Worker || $worker->getId() === null || $factor === null) {
                continue;
            }

            if (! isset($factorsByWorker[$worker->getId()])) {
                $factorsByWorker[$worker->getId()] = [];
            }

            $factorsByWorker[$worker->getId()][] = [
                'name' => trim($factor->getName()),
                'code' => trim($factor->getCode()),
            ];
        }

        usort($workers, static fn (Worker $a, Worker $b): int => strcasecmp($a->getName(), $b->getName()));

        $rows = [];
        foreach ($workers as $worker) {
            $workerId = (int) $worker->getId();
            $factorItems = $factorsByWorker[$workerId] ?? [];

            $riskNames = array_values(array_filter(array_map(
                static fn (array $item): string => (string) ($item['name'] ?? ''),
                $factorItems
            )));
            $riskCodes = array_values(array_filter(array_map(
                static fn (array $item): string => (string) ($item['code'] ?? ''),
                $factorItems
            )));
            $riskWithCodes = array_values(array_filter(array_map(
                static function (array $item): string {
                    $name = trim((string) ($item['name'] ?? ''));
                    $code = trim((string) ($item['code'] ?? ''));
                    if ($name === '') {
                        return '';
                    }

                    return $code !== '' ? sprintf('%s (%s)', $name, $code) : $name;
                },
                $factorItems
            )));

            $rows[] = [
                'workerId' => $workerId,
                'workerName' => $worker->getName(),
                'riskNames' => $riskNames !== [] ? implode("\n", $riskNames) : '-',
                'riskCodes' => $riskCodes !== [] ? implode("\n", $riskCodes) : '-',
                'riskWithCodes' => $riskWithCodes !== [] ? implode("\n", $riskWithCodes) : '-',
                'checkPeriod' => $normalizedPeriods[$workerId] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $userContext
     * @return array<string, string>
     */
    private function buildCompanyData(CompanyRequisite $company, array $userContext): array
    {
        $documentDate = $company->getDocumentDate() ?? (new \DateTimeImmutable())->format('Y-m-d');

        return [
            'kompanija'   => (string) $company->getCompanyName(),
            'kodas'       => (string) $company->getCode(),
            'data'        => (string) $documentDate,
            'role'        => (string) ($company->getRole() ?? ''),
            'tipas'       => (string) ($company->getCompanyType() ?? ''),
            'tipasPilnas' => (string) $company->resolveTipasPilnasForDocuments(),
            'adresas'     => (string) ($company->getAddress() ?? ''),
            'outputDirectory' => (string) ($company->getDirectory() ?? ''),
            'managerType' => (string) ($company->getManagerType() ?? ''),
            'vardas'      => (string) ($company->getManagerFirstName() ?? ''),
            'pavarde'     => (string) ($company->getManagerLastName() ?? ''),
            'vadovas'     => trim((string) ($company->getManagerFirstName() ?? '') . ' ' . (string) ($company->getManagerLastName() ?? '')),

            'userId'      => (string) ($userContext['id'] ?? ''),
            'userName'    => (string) ($userContext['firstName'] ?? ''),
            'userSurname' => (string) ($userContext['lastName'] ?? ''),
            'companyId'   => (string) $company->getId(),
        ];
    }

    /**
     * @param array<int, array{
     *   eilNr:string,
     *   workerName:string,
     *   riskNames:string,
     *   riskCodes:string,
     *   riskWithCodes:string,
     *   checkPeriod:string
     * }> $numberedRows
     * @param array<string, string> $fallbackReplacements
     */
    private function applyWorkerRowsToOutput(string $outputPath, array $numberedRows, array $fallbackReplacements): void
    {
        if ($numberedRows === []) {
            return;
        }

        $rows = array_map(
            static fn (array $row): array => [
                'eilNr' => $row['eilNr'],
                'pareigybe' => $row['workerName'],
                'pareigybė' => $row['workerName'],
                'veiksniai' => $row['riskNames'],
                'sifrai' => $row['riskCodes'],
                'veiksniaiSuSifrais' => $row['riskWithCodes'],
                'veiksniaiPilnas' => $row['workerName'] . ': ' . $row['riskWithCodes'],
                'periodiskumas' => $row['checkPeriod'],
                'workerType' => $row['workerName'],
                'riskFactors' => $row['riskNames'],
                'riskCodes' => $row['riskCodes'],
                'riskFactorsWithCodes' => $row['riskWithCodes'],
                'checkPeriod' => $row['checkPeriod'],
            ],
            $numberedRows
        );

        $processor = new TemplateProcessor($outputPath);
        $cloned = false;

        try {
            if (method_exists($processor, 'cloneRowAndSetValues')) {
                try {
                    $processor->cloneRowAndSetValues('pareigybe', $rows);
                    $cloned = true;
                } catch (\Throwable) {
                    try {
                        $processor->cloneRowAndSetValues('pareigybė', $rows);
                        $cloned = true;
                    } catch (\Throwable) {
                        $processor->cloneRowAndSetValues('workerType', $rows);
                        $cloned = true;
                    }
                }
            } else {
                try {
                    $processor->cloneRow('pareigybe', count($rows));
                    $index = 1;
                    foreach ($rows as $row) {
                        $processor->setValue('eilNr#' . $index, $row['eilNr']);
                        $this->setPareigybePair($processor, '#' . (string) $index, $row['pareigybe']);
                        $processor->setValue('veiksniai#' . $index, $row['veiksniai']);
                        $processor->setValue('sifrai#' . $index, $row['sifrai']);
                        $processor->setValue('veiksniaiPilnas#' . $index, $row['veiksniaiPilnas']);
                        $processor->setValue('periodiskumas#' . $index, $row['periodiskumas']);
                        $index++;
                    }
                    $cloned = true;
                } catch (\Throwable) {
                    try {
                        $processor->cloneRow('pareigybė', count($rows));
                        $index = 1;
                        foreach ($rows as $row) {
                            $processor->setValue('eilNr#' . $index, $row['eilNr']);
                            $this->setPareigybePair($processor, '#' . (string) $index, $row['pareigybe']);
                            $processor->setValue('veiksniai#' . $index, $row['veiksniai']);
                            $processor->setValue('sifrai#' . $index, $row['sifrai']);
                            $processor->setValue('veiksniaiPilnas#' . $index, $row['veiksniaiPilnas']);
                            $processor->setValue('periodiskumas#' . $index, $row['periodiskumas']);
                            $index++;
                        }
                        $cloned = true;
                    } catch (\Throwable) {
                        $processor->cloneRow('workerType', count($rows));
                        $index = 1;
                        foreach ($rows as $row) {
                            $processor->setValue('workerType#' . $index, $row['workerType']);
                            $processor->setValue('riskFactors#' . $index, $row['riskFactors']);
                            $processor->setValue('riskCodes#' . $index, $row['riskCodes']);
                            $processor->setValue('checkPeriod#' . $index, $row['checkPeriod']);
                            $index++;
                        }
                        $cloned = true;
                    }
                }
            }
        } catch (\Throwable) {
            $cloned = false;
        }

        if (! $cloned) {
            $processor->setValue('eilNr', $fallbackReplacements['eilNr'] ?? '');
            $this->setPareigybePair($processor, '', $fallbackReplacements['pareigybe'] ?? '');
            $processor->setValue('veiksniai', $fallbackReplacements['veiksniai'] ?? '');
            $processor->setValue('sifrai', $fallbackReplacements['sifrai'] ?? '');
            $processor->setValue('veiksniaiSuSifrais', $fallbackReplacements['veiksniaiSuSifrais'] ?? '');
            $processor->setValue('veiksniaiPilnas', $fallbackReplacements['veiksniaiPilnas'] ?? '');
            $processor->setValue('periodiskumas', $fallbackReplacements['periodiskumas'] ?? '');
            $processor->setValue('workerType', $fallbackReplacements['workerType'] ?? '');
            $processor->setValue('riskFactors', $fallbackReplacements['riskFactors'] ?? '');
            $processor->setValue('riskCodes', $fallbackReplacements['riskCodes'] ?? '');
            $processor->setValue('riskFactorsWithCodes', $fallbackReplacements['riskFactorsWithCodes'] ?? '');
            $processor->setValue('checkPeriod', $fallbackReplacements['checkPeriod'] ?? '');
        }

        $processor->saveAs($outputPath);
    }

    private function setPareigybePair(TemplateProcessor $processor, string $suffix, string $value): void
    {
        $processor->setValue('pareigybe' . $suffix, $value);
        $processor->setValue('pareigybė' . $suffix, $value);
    }
}
