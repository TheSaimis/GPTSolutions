<?php

namespace App\Controller;

use App\Services\FileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/files')]
final class FileController extends AbstractController
{
    private const ALLOWED_BASE_DIRS = ['templates', 'var/generated'];

    public function __construct(
        private readonly FileService $fileService,
    ) {}

    #[Route('/change-directory', name: 'api_files_change_directory', methods: ['POST'])]
    public function changeDirectory(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Invalid JSON'], 400);
        }

        $baseDir      = (string) ($data['baseDir'] ?? 'templates');
        $directory     = (string) ($data['directory'] ?? '');
        $newDirectory  = (string) ($data['newDirectory'] ?? '');

        if (! in_array($baseDir, self::ALLOWED_BASE_DIRS, true)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Invalid baseDir. Allowed: ' . implode(', ', self::ALLOWED_BASE_DIRS)], 400);
        }

        if ($directory === '' || $newDirectory === '') {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'directory and newDirectory are required'], 400);
        }

        $result = $this->fileService->move($baseDir, $directory, $newDirectory);

        if ($result === 'FAIL') {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Failed to move file'], 400);
        }

        $fileName = basename($directory);
        $newPath  = trim($newDirectory, '/') . '/' . $fileName;

        return new JsonResponse([
            'status'  => 'SUCCESS',
            'oldPath' => $directory,
            'newPath' => $newPath,
        ]);
    }
}
