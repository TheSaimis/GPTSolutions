<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CompanyRequisite;
use App\Entity\HealthRiskFactor;
use App\Entity\HealthRiskProfile;
use App\Services\HealthRiskService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/health-risk')]
final class HealthRiskController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HealthRiskService $healthRiskService,
    ) {}

    #[Route('/factors', name: 'api_health_risk_factors', methods: ['GET'])]
    public function factors(): JsonResponse
    {
        return new JsonResponse(['factors' => $this->healthRiskService->listFactors()]);
    }

    #[Route('/factors', name: 'api_health_risk_factor_create', methods: ['POST'])]
    public function createFactor(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        $codeOrCipher = trim((string) ($data['code'] ?? $data['cipher'] ?? ''));
        if (! is_array($data) || trim((string) ($data['name'] ?? '')) === '' || $codeOrCipher === '') {
            return new JsonResponse(['error' => 'name ir code/cipher yra privalomi'], 400);
        }

        $factor = new HealthRiskFactor();
        $factor->setName(trim((string) $data['name']));
        $factor->setCode($codeOrCipher);
        $factor->setLineNumber((int) ($data['lineNumber'] ?? 0));

        $this->em->persist($factor);
        $this->em->flush();

        return new JsonResponse(['id' => $factor->getId()], 201);
    }

    #[Route('/profiles', name: 'api_health_risk_profiles', methods: ['GET'])]
    public function profiles(): JsonResponse
    {
        return new JsonResponse(['profiles' => $this->healthRiskService->listProfiles()]);
    }

    #[Route('/profiles', name: 'api_health_risk_profile_create', methods: ['POST'])]
    public function createProfile(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (! is_array($data) || trim((string) ($data['name'] ?? '')) === '') {
            return new JsonResponse(['error' => 'name yra privalomas'], 400);
        }

        $profile = new HealthRiskProfile();
        $profile->setName(trim((string) $data['name']));
        $profile->setCheckupTerm(trim((string) ($data['checkupTerm'] ?? 'Tikrintis kas 2 metus')));
        $profile->setLineNumber((int) ($data['lineNumber'] ?? 0));

        $this->em->persist($profile);
        $this->em->flush();

        return new JsonResponse(['id' => $profile->getId()], 201);
    }

    #[Route('/profiles/{id}', name: 'api_health_risk_profile_update', methods: ['PUT'], requirements: ['id' => '\\d+'])]
    public function updateProfile(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $profile = $this->em->getRepository(HealthRiskProfile::class)->find($id);
        if (! $profile instanceof HealthRiskProfile) {
            return new JsonResponse(['error' => 'Profilis nerastas'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        if (array_key_exists('name', $data)) {
            $profile->setName(trim((string) $data['name']));
        }
        if (array_key_exists('checkupTerm', $data)) {
            $profile->setCheckupTerm(trim((string) $data['checkupTerm']));
        }
        if (array_key_exists('lineNumber', $data)) {
            $profile->setLineNumber((int) $data['lineNumber']);
        }

        $this->em->flush();

        return new JsonResponse(['status' => 'SUCCESS']);
    }

    #[Route('/factors/{id}', name: 'api_health_risk_factor_update', methods: ['PUT'], requirements: ['id' => '\\d+'])]
    public function updateFactor(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $factor = $this->em->getRepository(HealthRiskFactor::class)->find($id);
        if (! $factor instanceof HealthRiskFactor) {
            return new JsonResponse(['error' => 'Veiksnys nerastas'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        if (array_key_exists('name', $data)) {
            $factor->setName(trim((string) $data['name']));
        }
        if (array_key_exists('code', $data) || array_key_exists('cipher', $data)) {
            $factor->setCode(trim((string) ($data['code'] ?? $data['cipher'])));
        }
        if (array_key_exists('lineNumber', $data)) {
            $factor->setLineNumber((int) $data['lineNumber']);
        }

        $this->em->flush();

        return new JsonResponse(['status' => 'SUCCESS']);
    }

    #[Route('/common-factors', name: 'api_health_risk_common_replace', methods: ['POST'])]
    public function replaceCommonFactors(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        $rows = is_array($data) ? ($data['factors'] ?? null) : null;
        if (! is_array($rows)) {
            return new JsonResponse(['error' => 'factors[] privalomas'], 400);
        }

        $this->healthRiskService->replaceCommonFactors($rows);
        return new JsonResponse(['status' => 'SUCCESS']);
    }

    #[Route('/profiles/{id}/factors', name: 'api_health_risk_profile_factors_replace', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function replaceProfileFactors(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $profile = $this->em->getRepository(HealthRiskProfile::class)->find($id);
        if (! $profile instanceof HealthRiskProfile) {
            return new JsonResponse(['error' => 'Profilis nerastas'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $rows = is_array($data) ? ($data['factors'] ?? null) : null;
        if (! is_array($rows)) {
            return new JsonResponse(['error' => 'factors[] privalomas'], 400);
        }

        $this->healthRiskService->replaceProfileFactors($profile, $rows);
        return new JsonResponse(['status' => 'SUCCESS']);
    }

    #[Route('/company/{companyId}', name: 'api_health_risk_company', methods: ['GET'], requirements: ['companyId' => '\\d+'])]
    public function byCompany(int $companyId, Request $request): JsonResponse
    {
        $company = $this->em->getRepository(CompanyRequisite::class)->find($companyId);
        if (! $company instanceof CompanyRequisite) {
            return new JsonResponse(['error' => 'Įmonė nerasta'], 404);
        }

        $profileId = $request->query->get('profileId');
        $profileId = is_numeric((string) $profileId) ? (int) $profileId : null;

        return new JsonResponse($this->healthRiskService->buildForCompany($company, $profileId));
    }

    #[Route('/preview', name: 'api_health_risk_preview', methods: ['GET'])]
    public function preview(Request $request): JsonResponse
    {
        $role      = trim((string) $request->query->get('role', ''));
        $profileId = $request->query->get('profileId');
        $profileId = is_numeric((string) $profileId) ? (int) $profileId : null;

        return new JsonResponse($this->healthRiskService->buildForRole($role, $profileId));
    }
}
