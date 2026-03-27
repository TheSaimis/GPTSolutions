<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\AuditLogger;
use App\Services\WorkplaceFactorsCertificateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/workplace-factors-certificate')]
final class WorkplaceFactorsCertificateController extends AbstractController
{
    public function __construct(
        private readonly WorkplaceFactorsCertificateService $certificateService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * POST /api/workplace-factors-certificate/create
     * Body:
     * {
     *   "companyId": 1,
     *   "template": "1 Sveikatos tikrinimo pazyma + knyga.docx",
     *   "healthRiskProfileId": 2,
     *   "name": "output.docx",
     *   "replacements": { ... papildomi custom laukai ... }
     * }
     */
    #[Route('/create', name: 'api_workplace_factors_certificate_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse|BinaryFileResponse
    {
        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $companyId = $data['companyId'] ?? null;
        if (! is_int($companyId) && ! ctype_digit((string) $companyId)) {
            return new JsonResponse(['error' => 'companyId is required'], 400);
        }

        $templatePath = trim((string) ($data['template'] ?? ''));
        if ($templatePath === '') {
            return new JsonResponse(['error' => 'template is required'], 400);
        }

        $profileId = $data['healthRiskProfileId'] ?? null;
        $profileId = is_numeric((string) $profileId) ? (int) $profileId : null;

        $custom = $data['replacements'] ?? $data['custom'] ?? [];
        if (! is_array($custom)) {
            $custom = [];
        }

        $name = isset($data['name']) ? trim((string) $data['name']) : null;
        if ($name === '') {
            $name = null;
        }

        $user = $this->getUser();
        $userContext = [
            'id'        => method_exists($user, 'getId') ? $user->getId() : null,
            'firstName' => method_exists($user, 'getFirstName') ? $user->getFirstName() : null,
            'lastName'  => method_exists($user, 'getLastName') ? $user->getLastName() : null,
        ];

        try {
            $outputPath = $this->certificateService->createDocument(
                (int) $companyId,
                $templatePath,
                $profileId,
                $userContext,
                $name,
                $custom
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Nepavyko sugeneruoti dokumento: ' . $e->getMessage()], 500);
        }

        $this->auditLogger->log(
            sprintf(
                'Sugeneruota DARBUOTOJU DARBO VIETU KENKSMINGU FAKTORIU NUSTATYMO PAZYMA (companyId=%d, template=%s)',
                (int) $companyId,
                $templatePath
            )
        );

        $response = new BinaryFileResponse($outputPath);
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($outputPath)
        );

        return $response;
    }
}

