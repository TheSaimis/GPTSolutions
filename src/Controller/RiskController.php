<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\RiskExcelService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/risk')]
final class RiskController extends AbstractController
{
    public function __construct(
        private readonly RiskExcelService $riskExcelService,
    ) {}

    /**
     * GET /api/risk/export/{companyId}
     * Sugeneruoja .xlsx failą su rizikos vertinimo lentelėmis kiekvienam įmonės darbuotojui.
     */
    #[Route('/export/{companyId}', name: 'api_risk_export', methods: ['GET'], requirements: ['companyId' => '\d+'])]
    public function export(int $companyId): JsonResponse|BinaryFileResponse
    {
        try {
            $path = $this->riskExcelService->generate($companyId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Excel generavimas nepavyko: ' . $e->getMessage()], 500);
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($path)
        );

        return $response;
    }
}
