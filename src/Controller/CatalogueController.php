<?php
namespace App\Controller;

use App\Services\AuditLogger;
use App\Services\CreateCatalogue;
use App\Services\DeleteCatalogue;
use App\Services\FileService;
use App\Services\UpdateCatalogue;
use App\Services\ZipFiles;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Katalogų valdymas: create, update, delete.
 * Veikia su templates, generated ir archive.
 * Body laukas "baseDir": "templates" (default), "generated" arba "archive".
 */
final class CatalogueController extends AbstractController
{
    private const BASE_DIR_MAP = [
        'templates' => 'templates',
        'generated' => 'generated',
        'archive'   => 'archive',
        'deleted'   => 'deleted',
    ];

    public function __construct(
        private CreateCatalogue $createCatalogue,
        private UpdateCatalogue $updateCatalogue,
        private DeleteCatalogue $deleteCatalogue,
        private FileService $fileService,
        private ZipFiles $zipFiles,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * GET /api/catalogue/roots
     * Grąžina tik paprašytų root katalogų medžių struktūrą (pagal BASE_DIR_MAP).
     * Query pavyzdžiai:
     * - /api/catalogue/roots?root=templates
     * - /api/catalogue/roots?root=templates,generated
     * - /api/catalogue/roots?root[]=templates&root[]=generated
     */
    #[Route('/api/catalogue/roots', name: 'api_catalogue_roots', methods: ['GET'])]
    public function roots(Request $request): JsonResponse
    {
        $rootParam = $request->query->get('root');
        $rawRoot = [];

        if (is_array($rootParam)) {
            $rawRoot = $rootParam;
        } elseif (is_scalar($rootParam)) {
            $singleRoot = trim((string) $rootParam);
            if ($singleRoot !== '') {
                $rawRoot = str_contains($singleRoot, ',')
                    ? array_map('trim', explode(',', $singleRoot))
                    : [$singleRoot];
            }
        }

        if ($rawRoot === []) {
            return new JsonResponse(['error' => 'Privaloma pateikti bent vieną root parametrą'], 400);
        }

        $requestedRoots = array_values(array_unique(array_filter(
            array_map(static fn ($value) => trim((string) $value), $rawRoot),
            static fn (string $value) => $value !== ''
        )));

        $allowedRoots = array_keys(self::BASE_DIR_MAP);
        $validRoots   = array_values(array_intersect($requestedRoots, $allowedRoots));

        if ($validRoots === []) {
            return new JsonResponse(['error' => 'Nerasta nei viena leidžiama root reikšmė'], 404);
        }

        $result = [];

        foreach ($validRoots as $root) {
            // Archive ir deleted katalogai matomi tik ADMIN vartotojui.
            if (in_array($root, ['archive', 'deleted'], true) && ! $this->isGranted('ROLE_ADMIN')) {
                continue;
            }

            // Grąžiname tik root turinį (be paties root aplanko wrapper).
            $result = array_merge($result, $this->fileService->listDirectory($root));
        }

        return new JsonResponse($result);
    }
    /**
     * POST /api/catalogue/create
     * Body: { "directory": "4 Tvarkos", "folderName": "Naujas", "baseDir": "templates" }
     */
    #[Route('/api/catalogue/create', name: 'api_catalogue_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }

        $baseDir    = trim((string) ($data['root'] ?? 'templates'));
        $directory  = trim((string) ($data['directory'] ?? ''));
        $folderName = trim((string) ($data['folderName'] ?? ''));

        if ($baseDir === null || $folderName === '') {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }

        $status = $this->createCatalogue->create($directory, $folderName, $baseDir);
        if ($status === 'SUCCESS') {
            $this->auditLogger->log("Sukurtas katalogas: {$baseDir}/{$directory}/{$folderName}");
        }
        return new JsonResponse(['status' => $status], $status === 'SUCCESS' ? 200 : 500);
    }

    /**
     * POST /api/catalogue/update
     * Body: { "oldDirectory": "4 Tvarkos/Senas", "newDirectory": "4 Tvarkos/Naujas", "baseDir": "templates" }
     */
    #[Route('/api/catalogue/update', name: 'api_catalogue_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }

        $baseDir      = trim((string) ($data['root']));
        $oldDirectory = trim((string) ($data['oldDirectory'] ?? $data['directory'] ?? ''));
        $newDirectory = trim((string) ($data['newDirectory'] ?? ''));

        if ($baseDir === null || $oldDirectory === '' || $newDirectory === '') {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }

        $status = $this->updateCatalogue->update($oldDirectory, $newDirectory, $baseDir);
        if ($status === 'SUCCESS') {
            $this->auditLogger->log("Katalogas pervadintas: {$oldDirectory} → {$newDirectory}");
        }
        return new JsonResponse(['status' => $status], $status === 'SUCCESS' ? 200 : 500);
    }

    /**
     * POST /api/catalogue/delete
     * Body: { "directory": "4 Tvarkos", "folderName": "Senas", "baseDir": "templates" }
     */
    #[Route('/api/catalogue/delete', name: 'api_catalogue_delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }

        $baseDir   = trim((string) ($data['root'] ?? 'you need to specify the root folder'));
        $directory = trim((string) ($data['directory'] ?? ''));

        if ($baseDir === null || $directory === '') {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }

        $status = $this->deleteCatalogue->delete($directory, $baseDir);
        if ($status === 'SUCCESS') {
            $target = str_replace('\\', '/', $directory);
            $this->auditLogger->log("Katalogas ištrintas (perkeltas į /deleted): {$baseDir}/{$target}");
        }
        return new JsonResponse(['status' => $status], $status === 'SUCCESS' ? 200 : 500);
    }

    #[Route('/api/catalogue/zip/{root}', name: 'api_catalogue_zip_root', methods: ['GET'])]
    #[Route('/api/catalogue/zip/{root}/{directory}', name: 'api_catalogue_zip', methods: ['GET'], requirements: ['directory' => '.+'])]
    public function filterFilesByApp(string $root, string $directory = ''): JsonResponse | BinaryFileResponse
    {
        if (! array_key_exists($root, self::BASE_DIR_MAP)) {
            return new JsonResponse(['error' => 'Katalogas nerastas: ' . $root], 404);
        }
        if ($root === 'archive' && ! $this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Archive katalogas pasiekiamas tik ADMIN'], 403);
        }

        $resolved = $directory === ''
            ? $this->fileService->getBaseFullPath($root)
            : $this->fileService->resolvePath($root, $directory);
        if ($resolved === null || ! is_dir($resolved)) {
            $target = $directory === '' ? $root : $directory;
            return new JsonResponse(['error' => 'Katalogas nerastas: ' . $target], 404);
        }

        try {
            $zipPath = $this->zipFiles->zipDirectory($root, $directory);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Nepavyko sukurti ZIP: ' . $e->getMessage()], 500);
        }

        $response = new BinaryFileResponse($zipPath);
        $response->headers->set('Content-Type', 'application/zip');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            ($directory === '' ? $root : basename($directory)) . '.zip'
        );

        return $response;
    }

    // --- Legacy routes (backward compatible) ---

    #[Route('/api/catalogue/template/create', name: 'api_catalogue_template_create', methods: ['POST'])]
    public function createTemplate(Request $request): JsonResponse
    {
        return $this->create($request);
    }

    #[Route('/api/catalogue/template/update', name: 'api_catalogue_template_update', methods: ['POST'])]
    public function updateTemplate(Request $request): JsonResponse
    {
        return $this->update($request);
    }

    #[Route('/api/catalogue/template/delete', name: 'api_catalogue_template_delete', methods: ['POST'])]
    public function deleteTemplate(Request $request): JsonResponse
    {
        return $this->delete($request);
    }

    private function resolveBaseDir(array $data): ?string
    {
        $key = trim((string) ($data['baseDir'] ?? 'templates'));
        return self::BASE_DIR_MAP[$key] ?? null;
    }
}
