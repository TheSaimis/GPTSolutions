<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\CompanyRequisite;
use App\Entity\Worker;
use App\Entity\WorkerRisk;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Atskiro dokumento:
 * "DARBUOTOJU DARBO VIETU KENKSMINGU FAKTORIU NUSTATYMO PAZYMA"
 * generavimo logika.
 */
final class WorkplaceFactorsCertificateService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CreateFile $createFile,
    ) {}

    /**
     * @param array<string, mixed> $userContext
     * @param array<string, mixed> $customReplacements
     */
    public function createDocument(
        int $companyId,
        string $templatePath,
        array $checkPeriodsByWorkerId = [],
        array $userContext = [],
        ?string $name = null,
        array $customReplacements = [],
    ): string {
        /** @var CompanyRequisite|null $company */
        $company = $this->em->getRepository(CompanyRequisite::class)->find($companyId);
        if (! $company instanceof CompanyRequisite) {
            throw new \InvalidArgumentException('Company not found');
        }

        $templatePath = str_replace('\\', '/', urldecode(trim($templatePath)));
        if ($templatePath === '' || str_contains($templatePath, '..') || str_starts_with($templatePath, '/')) {
            throw new \InvalidArgumentException('Invalid template path');
        }

        $directory = dirname($templatePath);
        if ($directory === '.') {
            $directory = '';
        }
        $template = basename($templatePath);
        $workerRows  = $this->buildWorkerRows($company, $checkPeriodsByWorkerId);

        $numberedRows = [];
        foreach ($workerRows as $index => $row) {
            $numberedRows[] = [
                'eilNr' => (string) ($index + 1),
                'workerName' => $row['workerName'],
                'riskNames' => $row['riskNames'],
                'riskCodes' => $row['riskCodes'],
                'checkPeriod' => $row['checkPeriod'],
            ];
        }

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
        $periodsPerWorker = array_map(
            static fn (array $row): string => $row['workerName'] . ': ' . $row['checkPeriod'],
            $numberedRows
        );

        $healthReplacements = [
            // New requested placeholders
            'eilNr'                     => implode("\n", $eilNrLines),
            'pareigybe'                 => implode("\n", $pareigybeLines),
            'veiksniai'                 => implode("\n", $veiksniaiLines),
            'sifrai'                    => implode("\n", $sifraiLines),
            'periodiskumas'             => implode("\n", $periodiskumasLines),

            // Backward compatibility placeholders
            'workerType'                => implode("\n", $pareigybeLines),
            'riskFactors'               => implode("\n", $veiksniaiLines),
            'riskCodes'                 => implode("\n", $sifraiLines),
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

        $rowMarkerKeys = [
            'eilNr',
            'pareigybe',
            'veiksniai',
            'sifrai',
            'periodiskumas',
            'workerType',
            'riskFactors',
            'riskCodes',
            'checkPeriod',
        ];
        $createFileReplacements = array_diff_key($healthReplacements, array_flip($rowMarkerKeys));

        $companyData = $this->buildCompanyData($company, $userContext);
        $companyData['directory'] = $directory;
        $companyData['template'] = $template;
        $companyData['replacements'] = array_merge($createFileReplacements, $customReplacements);

        $outputPath = $this->createFile->createWordDocument($companyData, $name);
        $this->applyWorkerRowsToOutput($outputPath, $numberedRows, $healthReplacements);

        return $outputPath;
    }

    /**
     * @param array<int|string, mixed> $checkPeriodsByWorkerId
     * @return array<int, array{workerId:int,workerName:string,riskNames:string,riskCodes:string,checkPeriod:string}>
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
            throw new \InvalidArgumentException('Company has no worker types assigned');
        }

        $missingPeriods = [];
        $normalizedPeriods = [];
        foreach ($workers as $workerId => $worker) {
            $period = isset($checkPeriodsByWorkerId[$workerId]) ? trim((string) $checkPeriodsByWorkerId[$workerId]) : '';
            if ($period === '') {
                $missingPeriods[] = $worker->getName();
            } else {
                $normalizedPeriods[$workerId] = $period;
            }
        }

        if ($missingPeriods !== []) {
            throw new \InvalidArgumentException(
                'Missing check period for worker types: ' . implode(', ', $missingPeriods)
            );
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

            $rows[] = [
                'workerId' => $workerId,
                'workerName' => $worker->getName(),
                'riskNames' => $riskNames !== [] ? implode("\n", $riskNames) : '-',
                'riskCodes' => $riskCodes !== [] ? implode("\n", $riskCodes) : '-',
                'checkPeriod' => $normalizedPeriods[$workerId],
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
            'tipasPilnas' => (string) ($company->getCategory() ?? ''),
            'adresas'     => (string) ($company->getAddress() ?? ''),
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
     * @param array<int, array{eilNr:string,workerName:string,riskNames:string,riskCodes:string,checkPeriod:string}> $numberedRows
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
                'veiksniai' => $row['riskNames'],
                'sifrai' => $row['riskCodes'],
                'periodiskumas' => $row['checkPeriod'],
                'workerType' => $row['workerName'],
                'riskFactors' => $row['riskNames'],
                'riskCodes' => $row['riskCodes'],
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
                    $processor->cloneRowAndSetValues('workerType', $rows);
                    $cloned = true;
                }
            } else {
                try {
                    $processor->cloneRow('pareigybe', count($rows));
                    $index = 1;
                    foreach ($rows as $row) {
                        $processor->setValue('eilNr#' . $index, $row['eilNr']);
                        $processor->setValue('pareigybe#' . $index, $row['pareigybe']);
                        $processor->setValue('veiksniai#' . $index, $row['veiksniai']);
                        $processor->setValue('sifrai#' . $index, $row['sifrai']);
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
        } catch (\Throwable) {
            $cloned = false;
        }

        if (! $cloned) {
            $processor->setValue('eilNr', $fallbackReplacements['eilNr'] ?? '');
            $processor->setValue('pareigybe', $fallbackReplacements['pareigybe'] ?? '');
            $processor->setValue('veiksniai', $fallbackReplacements['veiksniai'] ?? '');
            $processor->setValue('sifrai', $fallbackReplacements['sifrai'] ?? '');
            $processor->setValue('periodiskumas', $fallbackReplacements['periodiskumas'] ?? '');
            $processor->setValue('workerType', $fallbackReplacements['workerType'] ?? '');
            $processor->setValue('riskFactors', $fallbackReplacements['riskFactors'] ?? '');
            $processor->setValue('riskCodes', $fallbackReplacements['riskCodes'] ?? '');
            $processor->setValue('checkPeriod', $fallbackReplacements['checkPeriod'] ?? '');
        }

        $processor->saveAs($outputPath);
    }

}