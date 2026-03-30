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
     * GET /api/risk/export/{companyId}
     * Sugeneruoja .xlsx failą su rizikos vertinimo lentelėmis kiekvienam įmonės darbuotojui.
     */
    #[Route('/export/{companyId}', name: 'api_risk_export', methods: ['GET'], requirements: ['companyId' => '\d+'])]
    public function export(int $companyId): JsonResponse | BinaryFileResponse
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

        $projectDir = $kernel->getProjectDir();
        $targetDir  = $projectDir . '/otherTemplates/AAP';

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $targetPath = $targetDir . '/AAP.xlsx';

        try {
            // Optional: delete old file (Windows safety)
            if (file_exists($targetPath)) {
                unlink($targetPath);
            }
            $uploaded->move($targetDir, 'AAP.xlsx');
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Nepavyko išsaugoti failo: ' . $e->getMessage(),
            ], 500);
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $input = new ArrayInput([
            'command' => 'app:aap:import-xls',
            'path'    => $targetPath,
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
                    // 'path'   => $tmpPath,
                ], 500);
            }

            return new JsonResponse([
                'status'  => 'ok',
                'message' => 'AAP Excel importas į DB sėkmingas.',
                'output'  => $output,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'class' => $e::class,
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'path'  => $tmpPath,
            ], 500);
        } finally {
            if (isset($tmpPath) && is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }
}
