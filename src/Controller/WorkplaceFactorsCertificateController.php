<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\AuditLogger;
use App\Services\WorkplaceFactorsCertificateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/workplace-factors-certificate')]
final class WorkplaceFactorsCertificateController extends AbstractController
{
    private const TEMPLATE_PATH = 'otherTemplates/pazyma/pazyma.docx';

    public function __construct(
        private readonly WorkplaceFactorsCertificateService $certificateService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * POST /api/workplace-factors-certificate/create
     * Body:
     * {
     *   "companyId": 1,
     *   "checkPeriods": { "1": "1 metai", "2": "2 metai" },
     *   "replacements": { ... papildomi custom laukai ... },
     *   "templatePath": "otherTemplates/pazyma/pazyma.docx",  // optional; overrides default template file
     *   "documentData": { "workerRows": [...], ... } | "json string"  // optional fill-only replay; company still from DB
     * }
     */
    #[Route('/create', name: 'api_workplace_factors_certificate_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse|BinaryFileResponse
    {
        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['error' => 'Neteisingas užklausos JSON'], 400);
        }

        $documentDataOverride = null;
        $rawDocumentData = $data['documentData'] ?? null;
        if ($rawDocumentData !== null && $rawDocumentData !== '') {
            if (is_string($rawDocumentData)) {
                $documentDataOverride = json_decode($rawDocumentData, true);
            } elseif (is_array($rawDocumentData)) {
                $documentDataOverride = $rawDocumentData;
            }
            if (! is_array($documentDataOverride)) {
                return new JsonResponse(['error' => 'documentData must be a JSON object or a JSON string'], 400);
            }
        }

        $companyId = $data['companyId'] ?? null;
        if (! is_int($companyId) && ! ctype_digit((string) $companyId)) {
            return new JsonResponse(['error' => 'Būtinas laukas companyId'], 400);
        }

        $templatePath = self::resolveCertificateTemplatePath($data, $documentDataOverride);

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $absoluteTemplatePath = $projectDir . '/templates/' . $templatePath;
        if (! is_file($absoluteTemplatePath)) {
            return new JsonResponse([
                'error' => 'Nerastas pažymos šablonas. Įkelkite jį per /api/workplace-factors-certificate/template/upload',
            ], 400);
        }

        $checkPeriods = [];
        $rawCheckPeriods = $data['checkPeriods'] ?? $data['workerCheckPeriods'] ?? null;
        if (is_array($rawCheckPeriods)) {
            foreach ($rawCheckPeriods as $workerId => $period) {
                if (is_numeric((string) $workerId)) {
                    $checkPeriods[(int) $workerId] = trim((string) $period);
                }
            }
        }

        $rawRows = $data['rows'] ?? $data['workerRows'] ?? null;
        if (is_array($rawRows)) {
            foreach ($rawRows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $workerId = $row['workerId'] ?? null;
                if (! is_numeric((string) $workerId)) {
                    continue;
                }
                $period = trim((string) ($row['checkPeriod'] ?? $row['period'] ?? ''));
                $checkPeriods[(int) $workerId] = $period;
            }
        }

        $custom = $data['replacements'] ?? $data['custom'] ?? [];
        if (! is_array($custom)) {
            $custom = [];
        }

        $user = $this->getUser();
        $userContext = [
            'id'        => method_exists($user, 'getId') ? $user->getId() : null,
            'firstName' => method_exists($user, 'getFirstName') ? $user->getFirstName() : null,
            'lastName'  => method_exists($user, 'getLastName') ? $user->getLastName() : null,
        ];
        if (is_array($documentDataOverride) && isset($documentDataOverride['userContext']) && is_array($documentDataOverride['userContext'])) {
            $userContext = $documentDataOverride['userContext'];
        }

        try {
            $outputPath = $this->certificateService->createDocument(
                (int) $companyId,
                $templatePath,
                $checkPeriods,
                $userContext,
                null,
                $custom,
                $documentDataOverride,
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

    /**
     * POST /api/workplace-factors-certificate/template/upload
     * Form-data: template=<docx file>
     * Uploads/overwrites templates/otherTemplates/pazyma/pazyma.docx
     */
    #[Route('/template/upload', name: 'api_workplace_factors_certificate_template_upload', methods: ['POST'])]
    public function uploadTemplate(Request $request): JsonResponse
    {
        $file = $request->files->get('template') ?? $request->files->get('file');
        if (! $file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'Trūksta failo lauko „template“'], 400);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension !== 'docx') {
            return new JsonResponse(['error' => 'Leidžiamas tik .docx failas'], 400);
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $targetDirectory = $projectDir . '/templates/otherTemplates/pazyma';

        if (! is_dir($targetDirectory) && ! @mkdir($targetDirectory, 0775, true) && ! is_dir($targetDirectory)) {
            return new JsonResponse(['error' => 'Nepavyko sukurti šablonų katalogo'], 500);
        }

        try {
            $file->move($targetDirectory, 'pazyma.docx');
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Nepavyko įkelti šablono: ' . $e->getMessage()], 500);
        }

        $this->auditLogger->log('Atnaujintas pažymos šablonas: templates/' . self::TEMPLATE_PATH);

        return new JsonResponse([
            'status' => 'SUCCESS',
            'template' => self::TEMPLATE_PATH,
        ]);
    }

    /**
     * Prefer explicit API fields, then legacy `documentData.templatePath` from older Word files.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $documentDataOverride
     */
    private function resolveCertificateTemplatePath(array $data, ?array $documentDataOverride): string
    {
        foreach (['templatePath', 'template'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $tp = trim(str_replace('\\', '/', $data[$key]));
                if ($tp !== '' && ! str_contains($tp, '..')) {
                    return $tp;
                }
            }
        }

        if (is_array($documentDataOverride) && isset($documentDataOverride['templatePath']) && is_string($documentDataOverride['templatePath'])) {
            $tp = trim(str_replace('\\', '/', $documentDataOverride['templatePath']));
            if ($tp !== '') {
                return $tp;
            }
        }

        return self::TEMPLATE_PATH;
    }
}