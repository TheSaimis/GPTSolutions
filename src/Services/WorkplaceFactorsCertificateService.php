<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\CompanyRequisite;
use App\Entity\CompanyWorker;
use App\Entity\HealthRiskFactor;
use App\Entity\Worker;
use App\Entity\WorkerRisk;
use Doctrine\ORM\EntityManagerInterface;

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
        private readonly HealthRiskService $healthRiskService,
    ) {}

    /**
     * @param array<string, mixed> $userContext
     * @param array<string, mixed> $customReplacements
     */
    public function createDocument(
        int $companyId,
        string $templatePath,
        ?int $healthRiskProfileId = null,
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

        $companyData = $this->buildCompanyData($company, $userContext);
        $healthData  = $this->healthRiskService->buildForCompany($company, $healthRiskProfileId);

        $healthReplacements = [
            // LT placeholders
            'sveikatosRizikosVeiksniai' => $healthData['factorsText'],
            'sveikatosRizikosSifrai'    => $healthData['codesText'],
            'sveikatosRizikosLentele'   => $healthData['tableText'],
            'sveikatosTerminas'         => (string) $healthData['checkupTerm'],
            'rizikosVeiksniai'          => $healthData['factorsText'],
            'rizikosSifrai'             => $healthData['codesText'],
            'rizikosLentele'            => $healthData['tableText'],
            'terminas'                  => (string) $healthData['checkupTerm'],
            'veiksniai'                 => $healthData['factorsText'],
            'sifrai'                    => $healthData['codesText'],
            // EN aliases
            'healthRiskFactors'         => $healthData['factorsText'],
            'healthRiskCodes'           => $healthData['codesText'],
            'healthRiskTable'           => $healthData['tableText'],
            'healthCheckTerm'           => (string) $healthData['checkupTerm'],
        ];

        $companyData['directory']    = $directory;
        $companyData['template']     = $template;
        $companyData['replacements'] = array_merge($healthReplacements, $customReplacements);

        return $this->createFile->createWordDocument($companyData, $name);
    }

    /**
     * @param array<int|string, mixed> $workersInput
     * @return array<string, mixed>
     */
    public function buildCertificateData(int $companyId, array $workersInput = []): array
    {
        /** @var CompanyRequisite|null $company */
        $company = $this->em->getRepository(CompanyRequisite::class)->find($companyId);
        if (! $company instanceof CompanyRequisite) {
            throw new \InvalidArgumentException('Company not found');
        }

        $checkPeriodByWorker = $this->parseCheckPeriods($workersInput);

        $workers = $this->em->getRepository(CompanyWorker::class)
            ->createQueryBuilder('cw')
            ->join('cw.worker', 'w')
            ->where('cw.companyRequisite = :company')
            ->setParameter('company', $company)
            ->addOrderBy('w.id', 'ASC')
            ->getQuery()
            ->getResult();

        $workersData = [];
        /** @var CompanyWorker $cw */
        foreach ($workers as $cw) {
            $worker = $cw->getWorker();
            if (! $worker instanceof Worker || $worker->getId() === null) {
                continue;
            }

            $riskRows = $this->em->getRepository(WorkerRisk::class)
                ->createQueryBuilder('wr')
                ->join('wr.riskFactor', 'rf')
                ->where('wr.worker = :worker')
                ->setParameter('worker', $worker)
                ->addOrderBy('rf.lineNumber', 'ASC')
                ->addOrderBy('rf.id', 'ASC')
                ->getQuery()
                ->getResult();

            $riskFactors = [];
            /** @var WorkerRisk $riskRow */
            foreach ($riskRows as $riskRow) {
                $factor = $riskRow->getRiskFactor();
                if (! $factor instanceof HealthRiskFactor || $factor->getId() === null) {
                    continue;
                }

                $riskFactors[] = [
                    'id'     => (int) $factor->getId(),
                    'name'   => $factor->getName(),
                    'cipher' => $factor->getCode(),
                ];
            }

            $workersData[] = [
                'workerId'      => (int) $worker->getId(),
                'name'          => $worker->getName(),
                'checkPeriod'   => $checkPeriodByWorker[(int) $worker->getId()] ?? null,
                'riskFactors'   => $riskFactors,
            ];
        }

        return [
            'company' => [
                'id'            => (int) $company->getId(),
                'companyName'   => $company->getCompanyName(),
                'code'          => $company->getCode(),
                'companyType'   => $company->getCompanyType(),
                'category'      => $company->getCategory(),
                'address'       => $company->getAddress(),
                'cityOrDistrict'=> $company->getCityOrDistrict(),
            ],
            'workers' => $workersData,
        ];
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

            'userId'      => (string) ($userContext['id'] ?? ''),
            'userName'    => (string) ($userContext['firstName'] ?? ''),
            'userSurname' => (string) ($userContext['lastName'] ?? ''),
            'companyId'   => (string) $company->getId(),
        ];
    }

    /**
     * Palaikomi formatai:
     * 1) workers: [{workerId: 1, checkPeriod: "2 metai"}]
     * 2) workers: {"1":"2 metai","2":"1 metai"}
     *
     * @param array<int|string, mixed> $workersInput
     * @return array<int, string>
     */
    private function parseCheckPeriods(array $workersInput): array
    {
        $result = [];
        foreach ($workersInput as $key => $value) {
            if (is_array($value)) {
                $workerId = $value['workerId'] ?? null;
                if (! is_numeric((string) $workerId)) {
                    continue;
                }
                $period = trim((string) ($value['checkPeriod'] ?? ''));
                if ($period === '') {
                    continue;
                }
                $result[(int) $workerId] = $period;
                continue;
            }

            if (is_numeric((string) $key)) {
                $period = trim((string) $value);
                if ($period !== '') {
                    $result[(int) $key] = $period;
                }
            }
        }

        return $result;
    }
}

