<?php
namespace App\Controller;

use App\Services\AddWordDocument;
use App\Services\AuditLogger;
use App\Services\FileService;
use App\Services\GetPDF;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/files')]
final class FileController extends AbstractController
{
    private const ALLOWED_BASE_DIRS = ['templates', 'generated', 'deleted'];

    public function __construct(
        private readonly FileService $fileService,
        private AddWordDocument $addWordDocument,
        private GetPDF $getPDF,
        private AuditLogger $auditLogger,
    ) {}

    #[Route('/change-directory', name: 'api_files_change_directory', methods: ['POST'])]
    public function changeDirectory(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Invalid JSON'], 400);
        }

        $baseDir      = (string) ($data['baseDir'] ?? 'templates');
        $directory    = (string) ($data['directory'] ?? '');
        $newDirectory = (string) ($data['newDirectory'] ?? '');

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

        $this->auditLogger->log("Failas perkeltas: {$baseDir}/{$directory} → {$newPath}");

        return new JsonResponse([
            'status'  => 'SUCCESS',
            'oldPath' => $directory,
            'newPath' => $newPath,
        ]);
    }

    #[Route('/create', name: 'api_files_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $root = trim((string) $request->request->get('root', ''));
        $path = trim((string) $request->request->get('directory', ''));
        $file = $request->files->get('template');
        if (! $file) {
            return new JsonResponse(['error' => 'Missing file field "template"'], 400);
        }
        if (! in_array($root, self::ALLOWED_BASE_DIRS, true)) {
            return new JsonResponse(['error' => 'Neleistinas katalogas'], 403);
        }
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        if (str_starts_with($path, $root . '/')) {
            $path = substr($path, strlen($root) + 1);
        }

        $result = $this->addWordDocument->addWordDocument($file, $path, $root);

        $this->auditLogger->log("Įkeltas failas į {$root}/{$path}");

        return new JsonResponse($result);
    }

    #[Route('/rename', name: 'api_file_rename', methods: ['POST'])]
    public function rename(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data    = json_decode($request->getContent(), true);
        $root    = trim((string) (is_array($data) ? ($data['root'] ?? $request->request->get('root')) : ''));
        $path    = trim((string) (is_array($data) ? ($data['directory'] ?? $request->request->get('directory')) : ''));
        $newName = trim((string) (is_array($data) ? ($data['name'] ?? $request->request->get('name')) : ''));
        if (! in_array($root, self::ALLOWED_BASE_DIRS, true)) {
            return new JsonResponse(['error' => 'Neleistinas katalogas'], 403);
        }
        if ($path === '' || $newName === '') {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'path and newName are required'], 400);
        }
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        if (str_starts_with($path, $root . '/')) {
            $path = substr($path, strlen($root) + 1);
        }
        $resolved = $this->fileService->resolvePath($root, $path);
        if ($resolved === null || ! is_file($resolved)) {
            return new JsonResponse(['error' => 'Failas nerastas: ' . $path], 404);
        }
        $status = $this->fileService->rename($root, $path, $newName);
        if ($status === 'SUCCESS') {
            $this->auditLogger->log("Failas pervadintas: {$root}/{$path} → {$newName}");
        }
        return new JsonResponse(
            ['status' => $status],
            $status === 'SUCCESS' ? 200 : 500
        );
    }

    #[Route('/delete', name: 'api_file_delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        $root = trim((string) (is_array($data) ? ($data['root'] ?? $request->request->get('root')) : ''));
        $path = trim((string) (is_array($data) ? ($data['directory'] ?? $request->request->get('directory')) : ''));

        if (! in_array($root, self::ALLOWED_BASE_DIRS, true)) {
            return new JsonResponse(['error' => 'Neleistinas katalogas'], 403);
        }
        if ($path === '') {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'path is required'], 400);
        }
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        if (str_starts_with($path, $root . '/')) {
            $path = substr($path, strlen($root) + 1);
        }
        $resolved = $this->fileService->resolvePath($root, $path);
        if ($resolved === null || ! is_file($resolved)) {
            return new JsonResponse(['error' => 'Failas nerastas: ' . $path], 404);
        }
        $status = $this->fileService->delete($root, $path);
        if ($status === 'SUCCESS') {
            $this->auditLogger->log("Failas ištrintas (perkeltas į /deleted): {$root}/{$path}");
        }
        return new JsonResponse(
            ['status' => $status],
            $status === 'SUCCESS' ? 200 : 500
        );
    }

    /**
     * GET /api/files/document-data/{root}/{path}
     * Grąžina dokumento duomenis ir metaduomenis. Nurodai root (templates|generated) ir path – gauni viską.
     * Pvz.: GET /api/files/document-data/generated/UAB/CompanyName/doc.docx
     */
    /**
     * GET /api/files/deleted?root=templates|generated
     * Grąžina ištrintų dokumentų katalogo turinį. root opcjonalus – jei tuščias, grąžina templates ir generated.
     */
    #[Route('/deleted', name: 'api_files_deleted_list', methods: ['GET'])]
    public function listDeleted(Request $request): JsonResponse
    {
        $root = trim((string) $request->query->get('root', ''));

        if ($root !== '' && ! in_array($root, self::ALLOWED_BASE_DIRS, true)) {
            return new JsonResponse(['error' => 'Neleistinas root. Leidžiami: templates, generated'], 400);
        }

        $items = $this->fileService->listDeleted($root);

        return new JsonResponse($items);
    }

    /**
     * POST /api/files/restore
     * Atkuria failą iš /deleted. Body: { "root": "templates"|"generated", "path": "4 Tvarkos/doc.docx" }
     */
    #[Route('/restore', name: 'api_files_restore', methods: ['POST'])]
    public function restore(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Invalid JSON'], 400);
        }

        $path = trim((string) ($data['path'] ?? $data['directory'] ?? ''));

        if ($path === '') {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'path is required'], 400);
        }

        $path = str_replace('\\', '/', $path);

        $status = $this->fileService->restore($path);
        if ($status === 'SUCCESS') {
            $this->auditLogger->log("Failas atkurtas: {$path}");
        }

        return new JsonResponse(
            ['status' => $status, 'path' => $path],
            $status === 'SUCCESS' ? 200 : 400
        );
    }

    #[Route('/document-data/{root}/{path}', name: 'api_files_document_data', methods: ['GET'], requirements: ['path' => '.+'], utf8: true)]
    public function getDocumentData(string $root, string $path): JsonResponse
    {
        if (! in_array($root, self::ALLOWED_BASE_DIRS, true)) {
            return new JsonResponse(['error' => 'Neleistinas katalogas. Leidžiami: templates, generated'], 403);
        }

        $result = $this->fileService->getFileMetadata($root, $path);
        if ($result === null) {
            return new JsonResponse(['error' => 'Failas nerastas arba nepalaikomas formatas: ' . $path], 404);
        }

        $custom = $result['metadata']['custom'] ?? [];
        if (isset($custom['customVariables']) && is_string($custom['customVariables'])) {
            $decoded                   = json_decode($custom['customVariables'], true);
            $custom['customVariables'] = is_array($decoded) ? $decoded : [];
        }

        return new JsonResponse([
            'path'     => $result['path'],
            'filename' => $result['filename'],
            'metadata' => [
                'core'   => $result['metadata']['core'] ?? [],
                'custom' => $custom,
            ],
        ]);
    }

    #[Route('/download/{root}/{path}', name: 'api_downlaod_file', methods: ['GET'], requirements: ['path' => '.+'], utf8: true)]
    public function getGeneratedFile(string $path, string $root): JsonResponse | BinaryFileResponse
    {
        if (! in_array($root, self::ALLOWED_BASE_DIRS, true)) {
            return new JsonResponse(['error' => 'Neleistinas katalogas'], 403);
        }

        $resolved = $this->fileService->resolvePath($root, $path);
        if ($resolved === null || ! is_file($resolved)) {
            return new JsonResponse(['error' => 'Failas nerastas: ' . $path], 404);
        }
        $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
        if (! in_array($ext, ['doc', 'docx', 'xls', 'xlsx'], true)) {
            return new JsonResponse(['error' => 'Leidžiami tik Word ir Excel failai'], 400);
        }
        $response = new BinaryFileResponse($resolved);
        $response->headers->set('Content-Type', match ($ext) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc'  => 'application/msword',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls'  => 'application/vnd.ms-excel',
        });
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($resolved)
        );
        return $response;
    }

    #[Route('/pdf/{root}/{path}', name: 'api_file_pdf', methods: ['GET'], requirements: ['path' => '.+'])]
    public function viewAsPdf(string $root, string $path): JsonResponse | BinaryFileResponse
    {
        if (! in_array($root, self::ALLOWED_BASE_DIRS, true)) {
            return new JsonResponse(['error' => 'Neleistinas katalogas'], 403);
        }
        $baseDir = $root;

        $resolved = $this->fileService->resolvePath($root, $path);
        if ($resolved === null || ! is_file($resolved)) {
            return new JsonResponse(['error' => 'Failas nerastas: ' . $root . '/' . $path], 404);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, ['doc', 'docx', 'xls', 'xlsx'], true)) {
            return new JsonResponse(['error' => 'Palaikomi tik .doc, .docx, .xls, .xlsx failai'], 400);
        }

        try {
            $pdfPath = $this->getPDF->convertToPdf($path, $baseDir);
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

}
