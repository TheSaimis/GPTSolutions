<?php

namespace App\Controller;

use App\Services\FileService;
use App\Services\GetPDF;
use App\Services\ZipFiles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

final class GeneratedFileController extends AbstractController
{
    private const GENERATED_BASE = 'var/generated';
    private const TEMPLATES_BASE = 'templates';

    public function __construct(
        private ZipFiles $zipFiles,
        private FileService $fileService,
        private GetPDF $getPDF,
    ) {
    }

    /**
     * GET /api/generated/pdf/{path}
     * Konvertuoja sugeneruotą .docx/.doc failą į PDF ir grąžina peržiūrai.
     * Path pvz.: "CompanyName/document_20240101.docx"
     */
    #[Route('/api/generated/pdf/{path}', name: 'api_generated_pdf', methods: ['GET'], requirements: ['path' => '.+'])]
    public function viewAsPdf(string $path): JsonResponse|BinaryFileResponse
    {
        $resolved = $this->fileService->resolvePath(self::GENERATED_BASE, $path);
        if ($resolved === null || !is_file($resolved)) {
            return new JsonResponse(['error' => 'Failas nerastas: ' . $path], 404);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['doc', 'docx'], true)) {
            return new JsonResponse(['error' => 'Palaikomi tik .doc ir .docx failai'], 400);
        }

        try {
            $pdfPath = $this->getPDF->convertToPdf($path, self::GENERATED_BASE);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'PDF generavimas nepavyko: ' . $e->getMessage()], 500);
        }

        $response = new BinaryFileResponse($pdfPath);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            pathinfo($path, PATHINFO_FILENAME) . '.pdf'
        );

        return $response;
    }

    /**
     * GET /api/generated
     * Grąžina sukurtų failų katalogų struktūrą (tipas/pavadinimas).
     */
    #[Route('/api/generated', name: 'api_generated_list', methods: ['GET'])]
    public function listDirectories(): JsonResponse
    {
        return new JsonResponse(
            $this->fileService->listDirectory('var/generated')
        );
    }
    /**
     * GET /api/generated/{directory}/zip
     * Suarchyvuoja nurodytą katalogą iš var/generated/ ir grąžina .zip failą.
     */
    #[Route('/api/generated/zip/{directory}', name: 'api_generated_zip', methods: ['GET'], requirements: ['directory' => '.+'])]
    public function filterFilesByApp(string $directory): JsonResponse|BinaryFileResponse
    {
        $resolved = $this->fileService->resolvePath(self::GENERATED_BASE, $directory);
        if ($resolved === null || !is_dir($resolved)) {
            return new JsonResponse(['error' => 'Katalogas nerastas: ' . $directory], 404);
        }

        try {
            $zipPath = $this->zipFiles->zipDirectory($directory);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Nepavyko sukurti ZIP: ' . $e->getMessage()], 500);
        }

        $response = new BinaryFileResponse($zipPath);
        $response->headers->set('Content-Type', 'application/zip');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $directory . '.zip'
        );

        return $response;
    }

    /**
     * GET /api/generated/all/zip
     * Suarchyvuoja visus katalogus iš var/generated/ ir grąžina vieną .zip failą.
     */
    #[Route('/api/generated/all/zip', name: 'api_generated_all_zip', methods: ['GET'])]
    public function allFilesByApp(): JsonResponse|BinaryFileResponse
    {
        $generatedDir = $this->fileService->getBaseFullPath(self::GENERATED_BASE);

        if ($generatedDir === null) {
            return new JsonResponse(['error' => 'Sugeneruotų failų katalogas nerastas'], 404);
        }

        $dirs = array_filter(scandir($generatedDir), function (string $item) use ($generatedDir) {
            return $item !== '.' && $item !== '..' && is_dir($generatedDir . '/' . $item);
        });

        if (empty($dirs)) {
            return new JsonResponse(['error' => 'Nėra sugeneruotų katalogų'], 404);
        }

        $zipPath = $generatedDir . '/generated.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return new JsonResponse(['error' => 'Nepavyko sukurti ZIP'], 500);
        }

        foreach ($dirs as $dir) {
            $dirPath = $generatedDir . '/' . $dir;
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $filePath = $file->getRealPath();
                    $relativePath = $dir . '/' . substr($filePath, strlen($dirPath) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }

        $zip->close();

        $response = new BinaryFileResponse($zipPath);
        $response->headers->set('Content-Type', 'application/zip');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'generated.zip'
        );

        return $response;
    }

    /**
     * GET /api/templates/zip/{path}
     * Suarchyvuoja templates/{path}/ ir grąžina .zip su statusu.
     * Return: zip failas + X-Status: SUCCESS, arba JSON { "status": "FAIL" }
     */
    #[Route('/api/templates/zip/{path}', name: 'api_templates_directory_zip', methods: ['GET'], requirements: ['path' => '.+'])]
    public function templatesDirectoryZip(string $path): JsonResponse|BinaryFileResponse
    {
        $resolved = $this->fileService->resolvePath(self::TEMPLATES_BASE, $path);
        if ($resolved === null || !is_dir($resolved)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Katalogas nerastas: ' . $path], 404);
        }

        try {
            $zipPath = $this->zipFiles->zipTemplatesDirectory($path);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['status' => 'FAIL', 'error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Nepavyko sukurti ZIP: ' . $e->getMessage()], 500);
        }

        $safeName = str_replace(['/', '\\'], '_', $path) . '.zip';
        $response = new BinaryFileResponse($zipPath);
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('X-Status', 'SUCCESS');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $safeName
        );

        return $response;
    }

    /**
     * GET /api/templates/zip
     * Suarchyvuoja visus šablonus iš templates/ katalogo ir grąžina templates.zip.
     */
    #[Route('/api/templates/zip', name: 'api_templates_zip', methods: ['GET'])]
    public function allTemplatesZip(): JsonResponse|BinaryFileResponse
    {
        $templatesDir = $this->fileService->getBaseFullPath(self::TEMPLATES_BASE);

        if ($templatesDir === null) {
            return new JsonResponse(['error' => 'Šablonų katalogas nerastas'], 404);
        }

        $zipPath = $this->getParameter('kernel.project_dir') . '/var/templates.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return new JsonResponse(['error' => 'Nepavyko sukurti ZIP'], 500);
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($templatesDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isFile())
                continue;

            $name = $file->getFilename();
            if (str_starts_with($name, '~') || $name === 'desktop.ini')
                continue;

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['doc', 'docx'], true))
                continue;

            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($templatesDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }

        if ($zip->count() === 0) {
            $zip->close();
            return new JsonResponse(['error' => 'Nėra šablonų failų'], 404);
        }

        $zip->close();

        $response = new BinaryFileResponse($zipPath);
        $response->headers->set('Content-Type', 'application/zip');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'templates.zip'
        );

        return $response;
    }
}