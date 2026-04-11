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
    /** Katalogas po generated/, kai dokumentai kuriami be įmonės ir nenurodytas outputDirectory. */
    private const OUTPUT_DIRECTORY_NO_COMPANY = 'Be įmonės dokumentai';

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

    #[Route('/api/ping', name: 'api_ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return $this->json(['ok' => true]);
    }
    // ───── GET  /api/templates/all ─────
    #[Route('/api/templates/all', name: 'api_templates_all', methods: ['GET'])]
    public function all(): JsonResponse
    {
        if ($this->fileService->getBaseFullPath('templates') === null) {
            return new JsonResponse(['error' => 'Šablonų katalogas nerastas'], 500);
        }

        return new JsonResponse(
            $this->filterTemplatesOnly($this->fileService->listDirectory('templates'))
        );
    }

    #[Route('/api/templates/id/{id}', name: 'api_templates_id', methods: ['GET'])]
    public function byId(string $id): JsonResponse
    {
        $path = $this->findTemplate->findByTemplateId(trim($id));
        if ($path === null) {
            return new JsonResponse(['error' => 'Šablonas nerastas'], 404);
        }

        return new JsonResponse([
            [
                'id' => trim($id),
                'path' => $path,
            ],
        ]);
    }

    #[Route('/api/templates/id', name: 'api_templates_ids', methods: ['POST'])]
    public function byIds(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['error' => 'Neteisingas JSON užklausos turinys'], 400);
        }

        $rawIds = $data['ids'] ?? $data['id'] ?? null;
        if ($rawIds === null) {
            return new JsonResponse(['error' => 'Privaloma pateikti ids arba id'], 400);
        }

        $ids = is_array($rawIds) ? $rawIds : [$rawIds];
        $normalizedIds = [];
        foreach ($ids as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }
            $value = trim((string) $candidate);
            if ($value !== '') {
                $normalizedIds[] = $value;
            }
        }
        $normalizedIds = array_values(array_unique($normalizedIds));

        if (count($normalizedIds) === 0) {
            return new JsonResponse(['error' => 'Nepateiktas nė vienas tinkamas ID'], 400);
        }

        $result = [];
        foreach ($normalizedIds as $templateId) {
            $path = $this->findTemplate->findByTemplateId($templateId);
            if ($path !== null) {
                $result[] = [
                    'id' => $templateId,
                    'path' => $path,
                ];
            }
        }

        return new JsonResponse($result);
    }

    // ───── GET  /api/templates/{category} ─────
    #[Route('/api/templates/{category}', name: 'api_templates_category', methods: ['GET'])]
    public function byCategory(string $category): JsonResponse
    {
        $items = $this->fileService->listDirectory('templates', $category);
        if ($items === []) {
            $resolved = $this->fileService->resolvePath('templates', $category);
            if ($resolved === null || ! is_dir($resolved)) {
                return new JsonResponse(['error' => 'Kategorija nerasta'], 404);
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

        $status = $this->addWordDocument->createFolder($directory, 'templates');
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

        $result = $this->addWordDocument->addWordDocument($file, $directory, 'templates');
        $status = $result['status'] ?? 'FAIL';
        if ($status === 'SUCCESS') {
            $this->auditLogger->log("Įkeltas šablonas į templates/{$directory}");
        }
        return new JsonResponse(['status' => $status], $status === 'SUCCESS' ? 200 : 500);
    }

    /**
     * POST /api/template/fillFileBulk
     * Body JSON: templates[] (būtina), companyId (nebūtina – jei nėra, naudojami laukai iš body arba numatytos reikšmės).
     * templates[] gali būti eilučių masyvas (keliai) arba objektų { path|template, replacements|custom? } –
     * kiekvienam šablonui savo papildomi makro; su body.custom/replacements sujungiama (įrašo laukai perrašo bendrus).
     * Be įmonės galima nurodyti: companyName/kompanija, code/kodas, documentDate/data, role, companyType/tipas,
     * category/tipasPilnas, address/adresas, cityOrDistrict/miestas, outputDirectory (jei tuščia — „Be įmonės dokumentai“), managerType,
     * managerFirstName/vardas, managerLastName/pavarde.
     * Su companyId: kiekvienam šablono keliui nustatoma kalba (kaip CreateFile – pavadinime ar kelyje EN/RU/LT),
     * ir kompanijos laukai imami iš DB atitinkama kalba (pvz. company_name_en, type_short_en, …), jei tuščia – LT atsarginė reikšmė.
     * Return: vienas .docx arba .zip, arba JSON su results.
     */
    #[Route('/api/template/fillFileBulk', name: 'api_template_fill_file_bulk', methods: ['POST'])]
    public function fillFileBulk(
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse | BinaryFileResponse {

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['error' => 'Neteisingas užklausos JSON'], 400);
        }

        $user = $this->getUser();

        $templates = $data['templates'] ?? null;
        $name      = isset($data['name']) ? trim((string) $data['name']) : null;

        if (! is_array($templates) || count($templates) === 0) {
            return new JsonResponse(['error' => 'Būtinas masyvas templates[]'], 400);
        }

        $rawCompanyId = $data['companyId'] ?? null;
        $hasCompanyId = $rawCompanyId !== null && $rawCompanyId !== ''
            && (is_int($rawCompanyId) || ctype_digit((string) $rawCompanyId));
        if ($hasCompanyId) {
            $hasCompanyId = ((int) $rawCompanyId) > 0;
        }

        if ($hasCompanyId) {
            $companyId = (int) $rawCompanyId;
            /** @var CompanyRequisite|null $company */
            $company = $em->getRepository(CompanyRequisite::class)->find($companyId);
            if (! $company) {
                return new JsonResponse(['error' => 'Įmonė nerasta'], 404);
            }

            $documentDate = $company->getDocumentDate() ?? (new \DateTimeImmutable())->format('Y-m-d');

            $code = (string) $company->getCode();
            /** Per šablono kelią žemiau sujungiama su localizedCompanyFieldsForFillBulk. */
            $companyData = [
                'kodas'             => $code,
                'data'              => (string) $documentDate,
                'outputDirectory'   => (string) ($company->getDirectory() ?? ''),
                'managerType'       => (string) ($company->getManagerType() ?? ''),
                'userId'            => (string) ($user->getId() ?? ''),
                'userName'          => (string) ($user->getFirstName() ?? ''),
                'userSurname'       => (string) ($user->getLastName() ?? ''),
                'companyId'         => (string) $company->getId(),
                '_fillBulkCompany'  => true,
            ];
        } else {
            $company   = null;
            $companyId = 0;

            $documentDate = isset($data['documentDate']) ? trim((string) $data['documentDate']) : '';
            if ($documentDate === '' && isset($data['data'])) {
                $documentDate = trim((string) $data['data']);
            }
            if ($documentDate === '') {
                $documentDate = (new \DateTimeImmutable())->format('Y-m-d');
            }

            $kompanija = trim((string) ($data['companyName'] ?? $data['kompanija'] ?? ''));
            if ($kompanija === '') {
                $kompanija = 'Nenurodyta įmonė';
            }

            $code = trim((string) ($data['code'] ?? $data['kodas'] ?? ''));

            $outputDirectory = trim(str_replace('\\', '/', (string) ($data['outputDirectory'] ?? '')), '/');
            if ($outputDirectory === '') {
                $outputDirectory = self::OUTPUT_DIRECTORY_NO_COMPANY;
            }

            $companyData = [
                'kompanija'       => $kompanija,
                'kodas'           => $code,
                'data'            => $documentDate,
                'role'            => (string) ($data['role'] ?? ''),
                'tipas'           => (string) ($data['companyType'] ?? $data['tipas'] ?? ''),
                'tipasPilnas'     => (string) ($data['category'] ?? $data['tipasPilnas'] ?? ''),
                'adresas'         => (string) ($data['address'] ?? $data['adresas'] ?? ''),
                'miestas'         => (string) ($data['cityOrDistrict'] ?? $data['miestas'] ?? ''),
                'outputDirectory' => $outputDirectory,
                'managerType'     => (string) ($data['managerType'] ?? ''),
                'vardas'          => (string) ($data['managerFirstName'] ?? $data['vardas'] ?? ''),
                'pavarde'         => (string) ($data['managerLastName'] ?? $data['pavarde'] ?? ''),

                'userId'      => (string) ($user->getId() ?? ''),
                'userName'    => (string) ($user->getFirstName() ?? ''),
                'userSurname' => (string) ($user->getLastName() ?? ''),
                'companyId'   => '0',
            ];
        }

        $globalReplacements = $data['replacements'] ?? $data['custom'] ?? [];
        if (! is_array($globalReplacements)) {
            $globalReplacements = [];
        }

        $results         = [];
        $generatedFiles  = [];
        $templateJobs    = [];

        foreach ($templates as $entry) {
            if (is_string($entry)) {
                $templateJobs[] = [
                    'path'           => $entry,
                    'replacements'   => $globalReplacements,
                ];

                continue;
            }

            if (is_array($entry)) {
                $path = $entry['path'] ?? $entry['template'] ?? null;
                if (! is_string($path) || trim($path) === '') {
                    $results[] = [
                        'template' => '',
                        'status'   => 'FAIL',
                        'error'    => 'Template object must include path or template',
                    ];

                    continue;
                }

                $per = $entry['replacements'] ?? $entry['custom'] ?? [];
                if (! is_array($per)) {
                    $per = [];
                }

                $templateJobs[] = [
                    'path'         => $path,
                    'replacements' => array_merge($globalReplacements, $per),
                ];

                continue;
            }

            $results[] = [
                'template' => 'invalid',
                'status'   => 'FAIL',
                'error'    => 'templates[] entries must be string or object with path/template',
            ];
        }

        foreach ($templateJobs as $job) {
            $tplPath = $job['path'];

            $tplPath = str_replace('\\', '/', urldecode(trim($tplPath)));

            // Security: must be relative inside /templates
            if (str_contains($tplPath, '..') || str_starts_with($tplPath, '/')) {
                $results[] = ['template' => $tplPath, 'status' => 'FAIL', 'error' => 'Netinkamas kelias'];
                continue;
            }

            $directory = dirname($tplPath);
            if ($directory === '.') {
                $directory = '';
            }
            $template = basename($tplPath);

            $payload = $companyData;
            $payload['replacements'] = $job['replacements'];
            if (($companyData['_fillBulkCompany'] ?? false) === true && $company instanceof CompanyRequisite) {
                unset($payload['_fillBulkCompany']);
                $lang = $this->resolveLanguageFromTemplateRelativePath($tplPath);
                $payload = array_merge($payload, $this->localizedCompanyFieldsForFillBulk($company, $lang));
            }

            try {
                $generatedPath = $this->createFile->createWordDocument(
                    array_merge($payload, [
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

        if ($company !== null) {
            $this->auditLogger->log("Sugeneruoti dokumentai (" . count($generatedFiles) . " vnt.) įmonei \"{$company->getCompanyName()}\" (ID: {$companyId})");
        } else {
            $this->auditLogger->log('Sugeneruoti dokumentai (' . count($generatedFiles) . ' vnt.) be įmonės (fillFileBulk)');
        }

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

        $zipSlug = trim((string) ($companyData['kodas'] ?? ''));
        if ($zipSlug === '') {
            $zipSlug = 'bulk';
        }

        try {
            $zipPath = $this->zipFiles->zipFiles($generatedFiles, 'generated_' . $zipSlug);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status'  => 'FAIL',
                'error'   => 'Nepavyko supakuoti sugeneruotų dokumentų: ' . $e->getMessage(),
                'results' => $results,
            ], 500);
        }

        $response = new BinaryFileResponse($zipPath);
        $response->headers->set('Content-Type', 'application/zip');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'generated_' . $zipSlug . '.zip'
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
            return new JsonResponse(['error' => 'Trūksta failo lauko „template“'], 400);
        }

        $result = $this->addWordDocument->addWordDocument($file, $directory, 'templates');
        $status = $result['status'] ?? 'FAIL';

        if ($status !== 'SUCCESS') {
            return new JsonResponse(['error' => 'Įkėlimas nepavyko (netinkamas failo tipas arba įrašymo klaida)'], 400);
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
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Būtini laukai path ir newName'], 400);
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
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Būtinas laukas path'], 400);
        }

        $status = $this->fileService->delete('templates', $path);
        if ($status === 'SUCCESS') {
            $this->auditLogger->log("Šablonas ištrintas (perkeltas į /deleted): {$path}");
        }
        return new JsonResponse(['status' => $status], $status === 'SUCCESS' ? 200 : 500);
    }

    /**
     * GET /api/templates/file/{path}
     * Atsisiunčia šablono failą (.docx, .doc, .xls, .xlsx) pagal kelią.
     * Path pvz.: "4 Tvarkos/dokumentas.docx"
     */
    #[Route('/api/templates/file/{path}', name: 'api_templates_file', methods: ['GET'], requirements: ['path' => '.+'])]
    public function getTemplateFile(string $path): JsonResponse | BinaryFileResponse
    {
        $resolved = $this->fileService->resolvePath('templates', $path);
        if ($resolved === null || ! is_file($resolved)) {
            return new JsonResponse(['error' => 'Failas nerastas: ' . $path], 404);
        }

        $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
        if (! in_array($ext, ['doc', 'docx', 'xls', 'xlsx'], true)) {
            return new JsonResponse(['error' => 'Leidžiami tik Word ir Excel failai'], 400);
        }

        $response = new BinaryFileResponse($resolved);
        $response->headers->set('Content-Type', match ($ext) {
            'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc'   => 'application/msword',
            'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls'   => 'application/vnd.ms-excel',
            default => 'application/octet-stream',
        });
        $response->headers->set('Cache-Control', 'private, no-store, must-revalidate');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($resolved)
        );

        return $response;
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
        $response->headers->set('Cache-Control', 'private, no-store, must-revalidate');
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
     * Kaip CreateFile::detectLanguageFromPath – pagal šablono kelią / pavadinimą (EN, RU, LT).
     */
    private function resolveLanguageFromTemplateRelativePath(string $relativePath): string
    {
        $norm     = str_replace('\\', '/', trim($relativePath));
        $baseName = pathinfo($norm, PATHINFO_FILENAME);

        if (preg_match('/\s(RU|EN)$/i', (string) $baseName, $matches)) {
            return mb_strtoupper($matches[1]);
        }
        if (preg_match('/(?:^|[\s_-])(RU|EN)$/i', (string) $baseName, $matches)) {
            return mb_strtoupper($matches[1]);
        }

        $segments = preg_split('#/#', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($segments as $seg) {
            $t = trim($seg);
            if (preg_match('/^(RU|EN|LT)$/i', $t)) {
                return mb_strtoupper($t);
            }
        }

        return 'LT';
    }

    /**
     * Užpildo kompanijos laukus iš DB pagal kalbą (suderinta su CreateFile.resolveDocumentLanguage).
     *
     * @return array<string, string>
     */
    private function localizedCompanyFieldsForFillBulk(CompanyRequisite $company, string $lang): array
    {
        if ($lang === 'EN') {
            return [
                'kompanija'   => (string) ($company->getCompanyNameEn() ?? $company->getCompanyName() ?? ''),
                'role'        => (string) ($company->getRoleEn() ?? $company->getRole() ?? ''),
                'tipas'       => $this->resolveCompanyTypeShortForLang($company, 'EN'),
                'tipasPilnas' => $this->resolveTipasPilnasForLang($company, 'EN'),
                'adresas'     => (string) ($company->getAddressEn() ?? $company->getAddress() ?? ''),
                'miestas'     => (string) ($company->getCityOrDistrictEn() ?? $company->getCityOrDistrict() ?? ''),
                'vardas'      => (string) ($company->getManagerFirstNameEn() ?? $company->getManagerFirstName() ?? ''),
                'pavarde'     => (string) ($company->getManagerLastNameEn() ?? $company->getManagerLastName() ?? ''),
            ];
        }

        if ($lang === 'RU') {
            return [
                'kompanija'   => (string) ($company->getCompanyNameRu() ?? $company->getCompanyName() ?? ''),
                'role'        => (string) ($company->getRoleRu() ?? $company->getRole() ?? ''),
                'tipas'       => $this->resolveCompanyTypeShortForLang($company, 'RU'),
                'tipasPilnas' => $this->resolveTipasPilnasForLang($company, 'RU'),
                'adresas'     => (string) ($company->getAddressRu() ?? $company->getAddress() ?? ''),
                'miestas'     => (string) ($company->getCityOrDistrictRu() ?? $company->getCityOrDistrict() ?? ''),
                'vardas'      => (string) ($company->getManagerFirstNameRu() ?? $company->getManagerFirstName() ?? ''),
                'pavarde'     => (string) ($company->getManagerLastNameRu() ?? $company->getManagerLastName() ?? ''),
            ];
        }

        return [
            'kompanija'   => (string) $company->getCompanyName(),
            'role'        => (string) ($company->getRole() ?? ''),
            'tipas'       => (string) ($company->getCompanyType() ?? ''),
            'tipasPilnas' => (string) $company->resolveTipasPilnasForDocuments(),
            'adresas'     => (string) ($company->getAddress() ?? ''),
            'miestas'     => (string) ($company->getCityOrDistrict() ?? ''),
            'vardas'      => (string) ($company->getManagerFirstName() ?? ''),
            'pavarde'     => (string) ($company->getManagerLastName() ?? ''),
        ];
    }

    private function resolveCompanyTypeShortForLang(CompanyRequisite $company, string $lang): string
    {
        $ref = $company->getCompanyTypeRef();
        if ($lang === 'EN') {
            $v = $ref?->getTypeShortEn();
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        } elseif ($lang === 'RU') {
            $v = $ref?->getTypeShortRu();
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        return (string) ($company->getCompanyType() ?? '');
    }

    private function resolveTipasPilnasForLang(CompanyRequisite $company, string $lang): string
    {
        $ref = $company->getCompanyTypeRef();
        if ($lang === 'EN') {
            $v = $ref?->getTypeEn();
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        } elseif ($lang === 'RU') {
            $v = $ref?->getTypeRu();
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        return (string) $company->resolveTipasPilnasForDocuments();
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
                if (in_array($ext, ['doc', 'docx', 'xls', 'xlsx'], true)) {
                    $result[] = $item;
                }
            }
        }
        return $result;
    }
}