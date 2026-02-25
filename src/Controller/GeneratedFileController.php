<?php

namespace App\Controller;

use App\Services\ZipFiles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

final class GeneratedFileController extends AbstractController
{
    public function __construct(
        private ZipFiles $zipFiles,
    ) {}

    /**
     * GET /api/generated/{directory}/zip
     * Suarchyvuoja nurodytą katalogą iš var/generated/ ir grąžina .zip failą.
     */
    #[Route('/api/generated/zip/{directory}', name: 'api_generated_zip', methods: ['GET'])]
    public function filterFilesByApp(string $directory): JsonResponse|BinaryFileResponse
    {
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
        $generatedDir = $this->getParameter('kernel.project_dir') . '/var/generated';

        if (!is_dir($generatedDir)) {
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
                    $filePath     = $file->getRealPath();
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
     * GET /api/templates/zip
     * Suarchyvuoja visus šablonus iš templates/ katalogo ir grąžina templates.zip.
     */
    #[Route('/api/templates/zip', name: 'api_templates_zip', methods: ['GET'])]
    public function allTemplatesZip(): JsonResponse|BinaryFileResponse
    {
        $templatesDir = $this->getParameter('kernel.project_dir') . '/templates';

        if (!is_dir($templatesDir)) {
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
            if (!$file->isFile()) continue;

            $name = $file->getFilename();
            if (str_starts_with($name, '~') || $name === 'desktop.ini') continue;

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['doc', 'docx'], true)) continue;

            $filePath     = $file->getRealPath();
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
