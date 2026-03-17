<?php
namespace App\Controller;

use App\Entity\CompanyRequisite;
use App\Services\AddWordDocument;
use App\Services\AuditLogger;
use App\Services\CreateFile;
use App\Services\FileService;
use App\Services\GetPDF;
use App\Services\Metadata\FindTemplate;
use App\Services\ZipFiles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

final class TemplateController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CreateFile $createFile,
        private readonly ZipFiles $zipFiles,
        private GetPDF $getPDF,
        private FindTemplate $findTemplate,
        private AddWordDocument $addWordDocument,
        private FileService $fileService,
        private AuditLogger $auditLogger,
    ) {}

    // ───── GET  /api/templates/all ─────
    #[Route('/api/templates/all', name: 'api_templates_all', methods: ['GET'])]
    public function all(): JsonResponse
    {
        if ($this->fileService->getBaseFullPath('templates') === null) {
            return new JsonResponse(['error' => 'Templates directory not found'], 500);
        }

        return new JsonResponse(
            $this->filterTemplatesOnly($this->fileService->listDirectory('templates'))
        );
    }

    #[Route('/api/templates/id/{id}', name: 'api_templates_id', methods: ['GET'])]
    public function byId(string $id): JsonResponse
    {
        return new JsonResponse($this->findTemplate->findByTemplateId($id));
    }

    // ───── GET  /api/templates/{category} ─────
    #[Route('/api/templates/{category}', name: 'api_templates_category', methods: ['GET'])]
    public function byCategory(string $category): JsonResponse
    {
        $items = $this->fileService->listDirectory('templates', $category);
        if ($items === []) {
            $resolved = $this->fileService->resolvePath('templates', $category);
            if ($resolved === null || ! is_dir($resolved)) {
                return new JsonResponse(['error' => 'Category not found'], 404);
            }
        }

        return new JsonResponse(['templates' => $this->filterTemplatesOnly($items)]);
    }

    // ───── GET  /api/templates/{category}/{subcategory} ─────
    // #[Route('/api/templates/{category}/{subcategory}', name: 'api_templates_subcategory', methods: ['GET'])]
    // public function bySubcategory(string $category, string $subcategory): JsonResponse
    // {
    //     $dir = $this->getTemplatesDir() . '/' . $category . '/' . $subcategory;
    //     if (!is_dir($dir)) {
    //         return new JsonResponse(['error' => 'Subcategory not found'], 404);
    //     }

    //     return new JsonResponse([
    //         'templates' => $this->scanDirectory($dir),
    //     ]);
    // }

    /**
     * POST /api/template/createFolder
     * Sukuria naują katalogą templates/{directory}/.
     * Body: { "directory": "4 Tvarkos/Naujas" }
     * Return: { "status": "SUCCESS" } arba { "status": "FAIL" }
     */
    #[Route('/api/template/createFolder', name: 'api_template_create_folder', methods: ['POST'])]
    public function createFolder(Request $request): JsonResponse
    {
        $data      = json_decode($request->getContent(), true);
        $directory = is_array($data) ? ($data['directory'] ?? null) : null;

        if ($directory === null || trim((string) $directory) === '') {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }
        $directory = trim((string) $directory);

        $status = $this->addWordDocument->createFolder($directory);
        if ($status === 'SUCCESS') {
            $this->auditLogger->log("Sukurtas šablonų katalogas: {$directory}");
        }
        return new JsonResponse(['status' => $status], $status === 'SUCCESS' ? 200 : 500);
    }

    /**
     * POST /api/template/fillFile
     * Įkelia šabloną (.docx) į templates/{directory}/.
     * Form data: directory (string), template (file .doc/.docx)
     * Return: { "status": "SUCCESS" } arba { "status": "FAIL" }
     */
    #[Route('/api/template/fillFile', name: 'api_template_fill_file', methods: ['POST'])]
    public function fillFile(Request $request): JsonResponse
    {
        $directory = $request->request->get('directory');

        if ($directory === null || trim((string) $directory) === '') {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }
        $directory = trim((string) $directory);

        $file = $request->files->get('template');
        if (! $file instanceof UploadedFile) {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }

        $status = $this->addWordDocument->addWordDocument($file, $directory);
        if ($status === 'SUCCESS') {
            $this->auditLogger->log("Įkeltas šablonas į templates/{$directory}");
        }
        return new JsonResponse(['status' => $status], $status === 'SUCCESS' ? 200 : 500);
    }

    /**
     * POST /api/template/fillFileBulk
     * Masinis šablonų įkėlimas į templates/{directory}/.
     * Form data: directory (string), templates[] (multiple .doc/.docx files)
     * Return: { "status": "SUCCESS"|"FAIL", "results": [{ "file": "x.docx", "status": "SUCCESS" }, ...] }
     */
    #[Route('/api/template/fillFileBulk', name: 'api_template_fill_file_bulk', methods: ['POST'])]
    public function fillFileBulk(
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse | BinaryFileResponse {

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $user = $this->getUser();

        $companyId = $data['companyId'] ?? null;
        $templates = $data['templates'] ?? null;
        $name = isset($data['name']) ? trim((string) $data['name']) : null;

        if (! is_int($companyId) && ! ctype_digit((string) $companyId)) {
            return new JsonResponse(['error' => 'companyId is required'], 400);
        }
        $companyId = (int) $companyId;

        if (! is_array($templates) || count($templates) === 0) {
            return new JsonResponse(['error' => 'templates[] is required'], 400);
        }

        /** @var CompanyRequisite|null $company */
        $company = $em->getRepository(CompanyRequisite::class)->find($companyId);
        if (! $company) {
            return new JsonResponse(['error' => 'Company not found'], 404);
        }

        $documentDate = $company->getDocumentDate() ?? (new \DateTimeImmutable())->format('Y-m-d');

        $code        = (string) $company->getCode();
        $companyData = [
            'kompanija'   => (string) $company->getCompanyName(),
            'kodas'       => $code,
            'data'        => (string) $documentDate,
            'role'        => (string) ($company->getRole() ?? ''),
            'tipas'       => (string) ($company->getCompanyType() ?? ''),
            'tipasPilnas' => (string) ($company->getCategory() ?? ''),
            'adresas'     => (string) ($company->getAddress() ?? ''),
            'managerType' => (string) ($company->getManagerType() ?? ''),
            'vardas'      => (string) ($company->getManagerFirstName() ?? ''),
            'pavarde'     => (string) ($company->getManagerLastName() ?? ''),

            'userId'      => (string) ($user->getId() ?? ''),
            'userName'    => (string) ($user->getFirstName() ?? ''),
            'userSurname' => (string) ($user->getLastName() ?? ''),
            'companyId'   => (string) $company->getId(),
        ];

        if (isset($data['replacements']) && is_array($data['replacements'])) {
            $companyData['replacements'] = $data['replacements'];
        }

        $results        = [];
        $generatedFiles = [];

        foreach ($templates as $tplPath) {
            if (! is_string($tplPath) || trim($tplPath) === '') {
                $results[] = ['template' => (string) $tplPath, 'status' => 'FAIL', 'error' => 'Invalid template path'];
                continue;
            }

            $tplPath = str_replace('\\', '/', urldecode(trim($tplPath)));

            // Security: must be relative inside /templates
            if (str_contains($tplPath, '..') || str_starts_with($tplPath, '/')) {
                $results[] = ['template' => $tplPath, 'status' => 'FAIL', 'error' => 'Invalid path'];
                continue;
            }

            $directory = dirname($tplPath);
            if ($directory === '.') {
                $directory = '';
            }
            $template = basename($tplPath);

            try {
                $generatedPath = $this->createFile->createWordDocument(
                    array_merge($companyData, [
                        'directory' => $directory,
                        'template'  => $template,
                    ]),
                    $name
                );

                $generatedFiles[] = $generatedPath;

                $results[] = [
                    'template' => $tplPath,
                    'status'   => 'SUCCESS',
                    'output'   => basename($generatedPath),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'template' => $tplPath,
                    'status'   => 'FAIL',
                    'error'    => $e->getMessage(),
                ];
            }
        }

        if (count($generatedFiles) === 0) {
            return new JsonResponse(['status' => 'FAIL', 'results' => $results], 500);
        }

        $this->auditLogger->log("Sugeneruoti dokumentai (" . count($generatedFiles) . " vnt.) įmonei \"{$company->getCompanyName()}\" (ID: {$companyId})");

        if (count($generatedFiles) === 1) {
            $docxPath = $generatedFiles[0];

            $response = new BinaryFileResponse($docxPath);
            $response->headers->set(
                'Content-Type',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            );
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                basename($docxPath)
            );

            // Optional: delete generated file after sending
            // $response->deleteFileAfterSend(true);

            return $response;
        }

        try {
            $zipPath = $this->zipFiles->zipFiles($generatedFiles, 'generated_' . $code);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status'  => 'FAIL',
                'error'   => 'Failed to zip generated documents: ' . $e->getMessage(),
                'results' => $results,
            ], 500);
        }

        $response = new BinaryFileResponse($zipPath);
        $response->headers->set('Content-Type', 'application/zip');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'generated_' . $code . '.zip'
        );
        $response->deleteFileAfterSend(true);

        return $response;
    }
    // ───── POST /api/template/create ─────
    //  Body: { directory?, subcategory?, template?, companyName, code,
    //          companyType?, address?, cityOrDistrict?,
    //          managerType (vadovas|vadovė|direktorius|direktorė),
    //          managerFirstName?, managerLastName?, managerFullName?,
    //          documentDate?, role? }
    #[Route('/api/template/create', name: 'api_template_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $directory = (string) $request->request->get('directory', '');
        $file      = $request->files->get('template');

        if (! $file) {
            return new JsonResponse(['error' => 'Missing file field "template"'], 400);
        }

        $status = $this->addWordDocument->addWordDocument($file, $directory);

        if ($status !== 'SUCCESS') {
            return new JsonResponse(['error' => 'Upload failed (invalid file type or save error)'], 400);
        }

        $this->auditLogger->log("Įkeltas šablonas: {$directory}");

        return new JsonResponse(['status' => 'SUCCESS']);
    }

    /**
     * POST /api/template/rename
     * Body: { "path": "4 Tvarkos/file.docx", "newName": "Naujas pavadinimas.docx" }
     */
    #[Route('/api/template/rename', name: 'api_template_rename', methods: ['POST'])]
    public function rename(Request $request): JsonResponse
    {
        $data    = json_decode($request->getContent(), true);
        $path    = trim((string) (is_array($data) ? ($data['directory'] ?? $request->request->get('directory')) : ''));
        $newName = trim((string) (is_array($data) ? ($data['name'] ?? $request->request->get('name') ?? $request->request->get('name')) : ''));

        if ($path === '' || $newName === '') {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'path and newName are required'], 400);
        }

        $status = $this->fileService->rename('templates', $path, $newName);
        if ($status === 'SUCCESS') {
            $this->auditLogger->log("Šablonas pervadintas: {$path} → {$newName}");
        }
        return new JsonResponse(['status' => $status], $status === 'SUCCESS' ? 200 : 500);
    }

    /**
     * POST /api/template/delete
     * Body: { "path": "4 Tvarkos/file.docx" }
     */
    #[Route('/api/template/delete', name: 'api_template_delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        $path = trim((string) (is_array($data) ? ($data['path'] ?? '') : $request->request->get('path') ?? ''));

        if ($path === '') {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'path is required'], 400);
        }

        $status = $this->fileService->delete('templates', $path);
        if ($status === 'SUCCESS') {
            $this->auditLogger->log("Šablonas ištrintas (perkeltas į /deleted): {$path}");
        }
        return new JsonResponse(['status' => $status], $status === 'SUCCESS' ? 200 : 500);
    }

    /**
     * GET /api/templates/pdf/{path}
     * Konvertuoja šabloną į PDF ir grąžina naršyklei peržiūrai.
     */
    #[Route('/api/templates/pdf/{path}', name: 'api_templates_pdf', methods: ['GET'], requirements: ['path' => '.+'])]
    public function previewPdf(string $path): JsonResponse | BinaryFileResponse
    {
        try {
            $pdfPath = $this->getPDF->convertToPdf($path);
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
     * Nustato lytį pagal vadovo tipo galūnę.
     *   vadovas / direktorius  → "Vyras"
     *   vadovė  / direktorė   → "Moteris"
     */
    private function resolveGender(string $managerType): string
    {
        $type = mb_strtolower(trim($managerType));

        $female = ['vadovė', 'direktorė'];
        $male   = ['vadovas', 'direktorius'];

        if (in_array($type, $female, true)) {
            return 'Moteris';
        }
        if (in_array($type, $male, true)) {
            return 'Vyras';
        }

        // Atsarginis: pagal paskutinę raidę
        if (str_ends_with($type, 'ė')) {
            return 'Moteris';
        }

        return 'Vyras';
    }

    // ─────────────────── Helpers ───────────────────

    private function getTemplatesDir(): string
    {
        return $this->getParameter('kernel.project_dir') . '/templates';
    }

    private function getGeneratedDir(): string
    {
        return $this->getParameter('kernel.project_dir') . '/generated';
    }

    /**
     * Tries to find the template file inside /templates.
     * Priority: directory/subcategory/file → directory/file → root/file.
     */
    private function resolveTemplatePath(string $file, array $data): ?string
    {
        $base = $this->getTemplatesDir();
        $dir  = $data['directory'] ?? null;
        $sub  = $data['subcategory'] ?? null;

        $candidates = [];
        if ($dir && $sub) {
            $candidates[] = "$base/$dir/$sub/$file";
        }
        if ($dir) {
            $candidates[] = "$base/$dir/$file";
        }
        $candidates[] = "$base/$file";

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Filtruoja listDirectory rezultatą – palieka tik doc/docx failus.
     *
     * @param array $items
     * @return array
     */
    private function filterTemplatesOnly(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if ($item['type'] === 'directory') {
                $result[] = [
                    'name'     => $item['name'],
                    'type'     => 'directory',
                    'path'     => $item['path'] ?? $item['name'],
                    'children' => $this->filterTemplatesOnly($item['children'] ?? []),
                ];
            } else {
                $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['doc', 'docx'], true)) {
                    $result[] = $item;
                }
            }
        }
        return $result;
    }
}
