<?php

namespace App\Controller;

use App\Entity\CompanyRequisite;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use App\Services\CreateFile;

final class TemplateController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CreateFile $createFile,
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
            'templates' => $this->scanDirectory($dir),
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

        if (empty($data['company']) || empty($data['code'])) {
            return new JsonResponse(['error' => 'Fields "company" and "code" are required'], 400);
        }

        // Jei atsiųstas "managerFullName" – skaldome į vardą ir pavardę
        $firstName = $data['managerFirstName'] ?? '';
        $lastName  = $data['managerLastName'] ?? '';
        if (empty($firstName) && empty($lastName) && !empty($data['managerFullName'])) {
            $parts     = explode(' ', trim($data['managerFullName']), 2);
            $firstName = $parts[0] ?? '';
            $lastName  = $parts[1] ?? '';
        }

        $gender = $this->resolveGender($data['managerType'] ?? '');

        // ── 1. Persist requisites to DB ──
        $req = new CompanyRequisite();
        $req->setCompanyName($data['company']);
        $req->setCode($data['code']);
        $req->setCompanyType($data['companyType'] ?? null);
        $req->setCategory($data['category'] ?? null);
        $req->setAddress($data['address'] ?? null);
        $req->setCityOrDistrict($data['cityOrDistrict'] ?? null);
        $req->setManagerType($data['managerType'] ?? null);
        $req->setManagerFirstName($firstName ?: null);
        $req->setManagerLastName($lastName ?: null);
        $req->setDocumentDate($data['instructionDate'] ?? null);
        $req->setRole($data['role'] ?? null);
        $req->setDirectory($data['directory'] ?? null);
        $req->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($req);
        $this->em->flush();

        // ── 2. Sukurti Word failą per CreateFile servisą ──
        $pathToDocx = $this->createFile->createWordDocument([
            'directory'        => $data['directory'] ?? '',
            'template'         => $data['template'] ?? '0 rekvizitai.docx',
            'company'          => $data['company'],
            'code'             => $data['code'],
            'instructionDate'  => $data['instructionDate'] ?? '',
            'role'             => $data['role'] ?? '',
        ]);
        $outputName = basename($pathToDocx);
        $outputPath = $pathToDocx;

        // ── 3. Return the filled file ──
        $response = new BinaryFileResponse($outputPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $outputName
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
