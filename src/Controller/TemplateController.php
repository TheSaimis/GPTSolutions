<?php

namespace App\Controller;

use App\Entity\CompanyRequisite;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use App\Services\AddWordDocument;
use App\Services\CreateFile;

final class TemplateController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CreateFile $createFile,
        private AddWordDocument $addWordDocument,
    ) {}

    // ───── GET  /api/templates/all ─────
    #[Route('/api/templates/all', name: 'api_templates_all', methods: ['GET'])]
    public function all(): JsonResponse
    {
        $dir = $this->getTemplatesDir();
        if (!is_dir($dir)) {
            return new JsonResponse(['error' => 'Templates directory not found'], 500);
        }

        return new JsonResponse([
            $this->scanDirectory($dir)
        ]);
    }

    // ───── GET  /api/templates/{category} ─────
    #[Route('/api/templates/{category}', name: 'api_templates_category', methods: ['GET'])]
    public function byCategory(string $category): JsonResponse
    {
        $dir = $this->getTemplatesDir() . '/' . $category;
        if (!is_dir($dir)) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        return new JsonResponse([
            'templates' => $this->scanDirectory($dir),
        ]);
    }

    // ───── GET  /api/templates/{category}/{subcategory} ─────
    #[Route('/api/templates/{category}/{subcategory}', name: 'api_templates_subcategory', methods: ['GET'])]
    public function bySubcategory(string $category, string $subcategory): JsonResponse
    {
        $dir = $this->getTemplatesDir() . '/' . $category . '/' . $subcategory;
        if (!is_dir($dir)) {
            return new JsonResponse(['error' => 'Subcategory not found'], 404);
        }

        return new JsonResponse([
            'templates' => $this->scanDirectory($dir),
        ]);
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
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }

        $status = $this->addWordDocument->addWordDocument($file, $directory);

        return new JsonResponse(['status' => $status], $status === 'SUCCESS' ? 200 : 500);
    }

    // ───── POST /api/template/create ─────
    //  Body: { directory?, subcategory?, template?, company, code,
    //          companyType?, address?, cityOrDistrict?,
    //          managerType (vadovas|vadovė|direktorius|direktorė),
    //          managerFirstName?, managerLastName?, managerFullName?,
    //          instructionDate?, role? }
    #[Route('/api/template/create', name: 'api_template_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse|BinaryFileResponse
    {
        $data = json_decode($request->getContent(), true);
    
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON body'], 400);
        }
    
        // ── Mandatory fields ───────────────────────────────────────────────────────
        $required = ['company', 'code', 'role', 'instructionDate', 'directory'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                return new JsonResponse(['error' => sprintf('Field "%s" is required', $field)], 400);
            }
        }
    
        $company = trim((string) $data['company']);
        $code    = trim((string) $data['code']);
        $role    = trim((string) $data['role']);
        $instructionDate = trim((string) $data['instructionDate']);
    
        // "directory" arrives WITH filename, e.g. "4 Tvarkos/3 Mobingo Tvarka 2023.docx"
        $dirWithFile = trim((string) $data['directory']);
    
        // Normalize slashes (support Windows "\" too)
        $dirWithFile = str_replace('\\', '/', $dirWithFile);
    
        $template = basename($dirWithFile);          // filename.ext
        $directory = trim(dirname($dirWithFile));    // folder path or "."
    
        if ($directory === '.' || $directory === '/') {
            $directory = ''; // template is directly under /templates
        }
    
        // Optional sanity: ensure template looks like a doc file
        // if (!preg_match('/\.docx?$/i', $template)) {
        //     return new JsonResponse(['error' => 'directory must include a .doc/.docx filename'], 400);
        // }
    
        // ── Create Word document ───────────────────────────────────────────────────
        try {
            $pathToDocx = $this->createFile->createWordDocument([
                'directory'       => $directory,
                'template'        => $template,
                'company'         => $company,
                'code'            => $code,
                'instructionDate' => $instructionDate, // CreateFile expects instructionDate key
                'role'            => $role,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Failed to create document',
                'details' => $e->getMessage(),
            ], 500);
        }
    
        // ── Return the generated file ──────────────────────────────────────────────
        $response = new BinaryFileResponse($pathToDocx);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($pathToDocx)
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
        return $this->getParameter('kernel.project_dir') . '/var/generated';
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
     * Recursively scans a directory and returns doc/docx files.
     * Ignores temp files (~$, ~WRL) and system files.
     */
    private function scanDirectory(string $dir): array
    {
        $result = [];
        $items  = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..'
                || str_starts_with($item, '~')
                || $item === 'desktop.ini'
            ) {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $result[] = [
                    'name'     => $item,
                    'type'     => 'directory',
                    'children' => $this->scanDirectory($path),
                ];
            } else {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (in_array($ext, ['doc', 'docx'], true)) {
                    $result[] = [
                        'name' => $item,
                        'type' => 'file',
                    ];
                }
            }
        }

        return $result;
    }
}
