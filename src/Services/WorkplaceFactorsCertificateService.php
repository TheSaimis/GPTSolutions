<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\CompanyRequisite;
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
}

