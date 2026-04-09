<?php

declare (strict_types = 1);

namespace App\Controller;

use App\Services\RiskExcelService;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/risk')]
final class RiskController extends AbstractController
{
    public function __construct(
        private readonly RiskExcelService $riskExcelService,
    ) {}

    /**
     * GET /api/risk/export/{companyId}?nameAndSurname=...&role=...
     * POST /api/risk/export/{companyId} — JSON: { "nameAndSurname": "...", "role": "..." }
     * Sugeneruoja .xlsx failą su rizikos vertinimo lentelėmis kiekvienam įmonės darbuotojui.
     */
    #[Route('/export/{companyId}', name: 'api_risk_export', methods: ['GET', 'POST'], requirements: ['companyId' => '\d+'])]
    public function export(Request $request, int $companyId): JsonResponse | BinaryFileResponse
    {
        [$nameAndSurname, $role] = $this->parseRiskExportSignerParams($request);

        try {
            $path = $this->riskExcelService->generate($companyId, $nameAndSurname, $role);
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

    /**
     * @return array{0: ?string, 1: ?string} [nameAndSurname, role]
     */
    private function parseRiskExportSignerParams(Request $request): array
    {
        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            if (! is_array($data)) {
                return [null, null];
            }
            $ns = trim((string) ($data['nameAndSurname'] ?? ''));
            $r  = trim((string) ($data['role'] ?? ''));

            return [
                $ns !== '' ? $ns : null,
                $r !== '' ? $r : null,
            ];
        }

        $ns = trim((string) $request->query->get('nameAndSurname', ''));
        $r  = trim((string) $request->query->get('role', ''));

        return [
            $ns !== '' ? $ns : null,
            $r !== '' ? $r : null,
        ];
    }

    #[Route('/import-xls', name: 'api_risk_import_xls', methods: ['POST'])]
    public function importXls(Request $request, KernelInterface $kernel): JsonResponse
    {
        $uploaded = $request->files->get('file');
        if (! $uploaded instanceof UploadedFile) {
            $uploaded = $request->files->get('template');
        }

        if (! $uploaded instanceof UploadedFile) {
            return new JsonResponse(['error' => 'Nepateiktas Excel failas (file/template).'], 400);
        }

        $ext = strtolower((string) $uploaded->getClientOriginalExtension());
        if (! in_array($ext, ['xls', 'xlsx'], true)) {
            return new JsonResponse(['error' => 'Leidžiami tik .xls ir .xlsx failai.'], 400);
        }

        $tmpPath = (string) $uploaded->getPathname();
        if (! is_file($tmpPath)) {
            return new JsonResponse([
                'error' => 'Nepavyko pasiekti įkelto failo laikinoje vietoje.',
            ], 500);
        }

        $projectDir = $kernel->getProjectDir();
        $targetDir  = $projectDir . '/otherTemplates/AAP';
        $targetPath = $targetDir . '/AAP.xlsx';
        $saveWarning = null;

        // Keep a stable copy as AAP.xlsx when directory is writable,
        // but do not fail import if this copy cannot be written.
        try {
            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0775, true);
            }
            if (is_dir($targetDir) && is_writable($targetDir)) {
                if (file_exists($targetPath)) {
                    @unlink($targetPath);
                }
                if (! @copy($tmpPath, $targetPath)) {
                    $saveWarning = 'Nepavyko išsaugoti AAP.xlsx kopijos kataloge otherTemplates/AAP.';
                }
            } else {
                $saveWarning = 'otherTemplates/AAP katalogas nėra writable, AAP.xlsx kopija neišsaugota.';
            }
        } catch (\Throwable $e) {
            $saveWarning = 'AAP.xlsx kopijos išsaugojimas nepavyko: ' . $e->getMessage();
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $input = new ArrayInput([
            'command' => 'app:aap:import-xls',
            'path'    => $tmpPath,
            '--reset' => true,
        ]);

        $buffer = new BufferedOutput();

        try {
            $exitCode = $application->run($input, $buffer);
            $output   = trim($buffer->fetch());

            if ($exitCode !== 0) {
                return new JsonResponse([
                    'error'  => 'Import komanda nepavyko.',
                    'output' => $output,
                    'path'   => $targetPath,
                ], 500);
            }

            return new JsonResponse([
                'status'  => 'ok',
                'message' => 'AAP Excel importas į DB sėkmingas.',
                'output'  => $output,
                'warning' => $saveWarning,
                'savedPath' => $saveWarning === null ? $targetPath : null,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'class' => $e::class,
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'path'  => $targetPath,
            ], 500);
        }
    }
}