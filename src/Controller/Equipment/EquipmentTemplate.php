<?php

declare(strict_types=1);

namespace App\Controller\Equipment;

use App\Entity\AapEquipmentWordTemplate;
use App\Entity\Equipment;
use App\Entity\Worker;
use App\Entity\WorkerItem;
use App\Repository\AapEquipmentWordTemplateRepository;
use App\Services\AapEquipmentWordDocumentService;
use App\Services\AuditLogger;
use App\Services\ConvertDocToDocx;
use App\Services\CreateEquipmentDocument;
use App\Services\GetPDF;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/equipment-template')]
final class EquipmentTemplate extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CreateEquipmentDocument $createEquipmentDocument,
        private readonly AapEquipmentWordDocumentService $aapEquipmentWordDocumentService,
        private readonly AuditLogger $auditLogger,
        private readonly AapEquipmentWordTemplateRepository $aapEquipmentWordTemplateRepository,
        private readonly ConvertDocToDocx $convertDocToDocx,
        private readonly GetPDF $getPDF,
    ) {}

    private function normalizeAapLocaleParam(mixed $raw): string
    {
        $l = mb_strtolower(trim((string) $raw));

        return in_array($l, ['en', 'ru', 'lt'], true) ? $l : 'lt';
    }

    private function isUniqueConstraintViolation(\Throwable $e): bool
    {
        $current = $e;
        while ($current instanceof \Throwable) {
            if ($current instanceof UniqueConstraintViolationException) {
                return true;
            }
            $current = $current->getPrevious();
        }

        return false;
    }

    /**
     * POST /api/equipment-template/createTemplate
     * Body: { "companyId": 1, "outputs": ["sarasas", "korteles"], "pagrindas": "...", "language": "lt"|"en"|"ru" }
     * outputs neprivalomas: numatyta ["sarasas"]. Abu tipai → ZIP su dviem .docx.
     * pagrindas neprivalomas: vienkartinis ${pagrindas} tekstas kortelėms (kitaip — iš įmonės arba numatytasis).
     * language / locale / documentLanguage — dokumento ir šablono kalba (LT/EN/RU).
     */
    #[Route('/createTemplate', name: 'api_equipment_template_create', methods: ['POST'])]
    public function createTemplate(Request $request): JsonResponse|BinaryFileResponse
    {
        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['error' => 'Neteisingas užklausos JSON'], 400);
        }

        $companyId = $data['companyId'] ?? null;
        if (! is_int($companyId) && ! ctype_digit((string) $companyId)) {
            return new JsonResponse(['error' => 'Būtinas laukas companyId'], 400);
        }

        $outputs = $data['outputs'] ?? $data['documents'] ?? null;
        if ($outputs === null && isset($data['output']) && is_string($data['output'])) {
            $outputs = [$data['output']];
        }
        if (! is_array($outputs) || $outputs === []) {
            $outputs = [AapEquipmentWordDocumentService::OUTPUT_SARASAS];
        }

        $normalized = [];
        foreach ($outputs as $item) {
            if ($item === AapEquipmentWordDocumentService::OUTPUT_SARASAS
                || $item === AapEquipmentWordDocumentService::OUTPUT_KORTELES) {
                $normalized[] = $item;
            }
        }
        if ($normalized === []) {
            return new JsonResponse(['error' => 'outputs turi būti „sarasas“ ir/arba „korteles“'], 400);
        }

        $pagrindasRaw = $data['pagrindas'] ?? $data['aapKortelesPagrindas'] ?? null;
        $kortelesPagrindasOverride = null;
        if (is_string($pagrindasRaw)) {
            $t = trim($pagrindasRaw);
            $kortelesPagrindasOverride = $t !== '' ? $t : null;
        }

        $documentLocale = $this->normalizeAapLocaleParam(
            $data['language'] ?? $data['locale'] ?? $data['documentLanguage'] ?? 'lt'
        );

        try {
            $result = $this->aapEquipmentWordDocumentService->generate(
                (int) $companyId,
                $normalized,
                $kortelesPagrindasOverride,
                $documentLocale
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Nepavyko sugeneruoti dokumento: ' . $e->getMessage()], 500);
        }

        $this->auditLogger->log(
            'Sugeneruoti AAP Word dokumentai (imone ID=' . (int) $companyId . '): ' . implode(', ', $normalized)
        );

        $response = new BinaryFileResponse($result['path']);
        $response->headers->set('Content-Type', $result['mime']);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $result['filename']
        );

        return $response;
    }

    /** GET /api/equipment-template/aap-template/status */
    #[Route('/aap-template/status', name: 'api_equipment_template_aap_status', methods: ['GET'])]
    public function aapTemplateStatus(): JsonResponse
    {
        $templates = [];
        foreach ([AapEquipmentWordDocumentService::OUTPUT_SARASAS, AapEquipmentWordDocumentService::OUTPUT_KORTELES] as $kind) {
            foreach (['lt', 'en', 'ru'] as $locale) {
                $row = ['kind' => $kind, 'locale' => $locale];
                $entity = $this->aapEquipmentWordTemplateRepository->findOneByKindAndLocale($kind, $locale);
                if ($entity instanceof AapEquipmentWordTemplate && $entity->getContent() !== '') {
                    $row['source'] = 'database';
                    $row['originalFilename'] = $entity->getOriginalFilename();
                    $row['updatedAt'] = $entity->getUpdatedAt()->format(DATE_ATOM);
                } else {
                    $row['source'] = $this->aapEquipmentWordDocumentService->hasFilesystemTemplate($kind, $locale)
                        ? 'filesystem'
                        : 'none';
                    $row['originalFilename'] = null;
                    $row['updatedAt'] = null;
                }
                $templates[] = $row;
            }
        }

        return new JsonResponse(['templates' => $templates]);
    }

    /**
     * GET /api/equipment-template/aap-template/{kind}/pdf
     * Esamo šablono (.docx, įskaitant iš DB) peržiūra PDF (LibreOffice).
     */
    #[Route('/aap-template/{kind}/pdf', name: 'api_equipment_template_aap_pdf', methods: ['GET'], requirements: ['kind' => 'sarasas|korteles'])]
    public function aapTemplatePdf(Request $request, string $kind): JsonResponse|BinaryFileResponse
    {
        $locale = $this->normalizeAapLocaleParam($request->query->get('locale', 'lt'));

        try {
            $docxPath = $this->aapEquipmentWordDocumentService->getTemplateDocxAbsolutePath($kind, $locale);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }

        try {
            $pdfPath = $this->getPDF->convertAbsolutePathToPdf($docxPath);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'PDF generavimas nepavyko: ' . $e->getMessage()], 500);
        }

        $loc = mb_strtoupper($locale);
        $filename = $kind === AapEquipmentWordDocumentService::OUTPUT_SARASAS
            ? 'AAP_sarasas_sablonas_' . $loc . '.pdf'
            : 'AAP_korteles_sablonas_' . $loc . '.pdf';

        $response = new BinaryFileResponse($pdfPath);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Cache-Control', 'private, no-store, must-revalidate');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);

        return $response;
    }

    /**
     * POST /api/equipment-template/aap-template (multipart: kind, file)
     * Į DB saugomas .docx (iš .doc konvertuojama per LibreOffice).
     */
    #[Route('/aap-template', name: 'api_equipment_template_aap_upload', methods: ['POST'])]
    public function uploadAapTemplate(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $kind = trim((string) $request->request->get('kind', ''));
        if ($kind !== AapEquipmentWordDocumentService::OUTPUT_SARASAS
            && $kind !== AapEquipmentWordDocumentService::OUTPUT_KORTELES) {
            return new JsonResponse(['error' => 'kind turi būti „sarasas“ arba „korteles“'], 400);
        }

        $locale = $this->normalizeAapLocaleParam($request->request->get('locale', 'lt'));

        $file = $request->files->get('file');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return new JsonResponse(['error' => 'Failas „file“ privalomas ir turi būti .doc arba .docx'], 400);
        }

        $raw = @file_get_contents($file->getPathname());
        if ($raw === false || $raw === '') {
            return new JsonResponse(['error' => 'Nepavyko nuskaityti failo'], 400);
        }

        $ext = strtolower((string) $file->guessExtension());
        if ($ext === '') {
            $ext = strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        }

        try {
            if ($ext === 'docx') {
                $docxBinary = $raw;
            } elseif ($ext === 'doc') {
                $docxBinary = $this->convertDocToDocx->convertDocBinaryToDocxBinary($raw);
            } else {
                return new JsonResponse(['error' => 'Leidžiami tik .doc arba .docx Word šablonai'], 400);
            }
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        $originalName = $file->getClientOriginalName();

        $entity = $this->aapEquipmentWordTemplateRepository->findOneByKindAndLocale($kind, $locale);
        if (! $entity instanceof AapEquipmentWordTemplate) {
            $entity = (new AapEquipmentWordTemplate())
                ->setTemplateKind($kind)
                ->setTemplateLocale($locale);
            $this->em->persist($entity);
        }
        $entity->setTemplateLocale($locale);
        $entity->setOriginalFilename($originalName);
        $entity->setContent($docxBinary);

        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            if (! $this->isUniqueConstraintViolation($e)) {
                throw $e;
            }
            $this->em->clear(AapEquipmentWordTemplate::class);
            $existing = $this->aapEquipmentWordTemplateRepository->findOneByKindAndLocale($kind, $locale);
            if (! $existing instanceof AapEquipmentWordTemplate) {
                throw $e;
            }
            $existing->setTemplateLocale($locale);
            $existing->setOriginalFilename($originalName);
            $existing->setContent($docxBinary);
            $this->em->persist($existing);
            $this->em->flush();
            $entity = $existing;
        }

        $this->aapEquipmentWordDocumentService->clearMaterializedDbTemplates($kind);

        $this->auditLogger->log('Įkeltas AAP Word šablonas į DB (kind=' . $kind . ', locale=' . $locale . '): ' . $entity->getOriginalFilename());

        return new JsonResponse([
            'ok' => true,
            'kind' => $kind,
            'locale' => $locale,
            'originalFilename' => $entity->getOriginalFilename(),
            'updatedAt' => $entity->getUpdatedAt()->format(DATE_ATOM),
        ]);
    }

    /** DELETE /api/equipment-template/aap-template/{kind}?locale=lt|en|ru */
    #[Route('/aap-template/{kind}', name: 'api_equipment_template_aap_delete', methods: ['DELETE'], requirements: ['kind' => 'sarasas|korteles'])]
    public function deleteAapTemplate(Request $request, string $kind): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $locale = $this->normalizeAapLocaleParam($request->query->get('locale', 'lt'));

        $entity = $this->aapEquipmentWordTemplateRepository->findOneByKindAndLocale($kind, $locale);
        if (! $entity instanceof AapEquipmentWordTemplate) {
            return new JsonResponse(['ok' => true, 'message' => 'Įrašo nebuvo']);
        }

        $name = $entity->getOriginalFilename();
        $this->em->remove($entity);
        $this->em->flush();
        $this->aapEquipmentWordDocumentService->clearMaterializedDbTemplates($kind);

        $this->auditLogger->log('Pašalintas AAP Word šablonas iš DB (kind=' . $kind . ', locale=' . $locale . '): ' . $name);

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/equipment', name: 'api_equipment_create', methods: ['POST'])]
    public function createEquipment(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['error' => 'Neteisingas užklausos JSON'], 400);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $nameEn = trim((string) ($data['nameEn'] ?? ''));
        $nameRu = trim((string) ($data['nameRu'] ?? ''));
        $expirationDate = trim((string) ($data['expirationDate'] ?? $data['ExperationDate'] ?? ''));
        $expirationDateEn = trim((string) ($data['expirationDateEn'] ?? ''));
        $expirationDateRu = trim((string) ($data['expirationDateRu'] ?? ''));

        $nameLt = $name !== '' ? $name : ($nameEn !== '' ? $nameEn : $nameRu);
        $expLt = $expirationDate !== '' ? $expirationDate : ($expirationDateEn !== '' ? $expirationDateEn : $expirationDateRu);
        if ($nameLt === '' || $expLt === '') {
            return new JsonResponse([
                'error' => 'Būtinas bent vienos kalbos pavadinimas ir tinkamumo terminas (name / nameEn / nameRu ir atitinkamas terminas)',
            ], 400);
        }

        $unitRaw = (string) ($data['unitOfMeasurement'] ?? $data['unit'] ?? 'vnt');

        $equipment = new Equipment();
        $equipment->setName($nameLt);
        $equipment->setExpirationDate($expLt);
        $equipment->setUnitOfMeasurement($unitRaw);
        $equipment->setNameEn($nameEn !== '' ? $nameEn : null);
        $equipment->setNameRu($nameRu !== '' ? $nameRu : null);
        $equipment->setExpirationDateEn($expirationDateEn !== '' ? $expirationDateEn : null);
        $equipment->setExpirationDateRu($expirationDateRu !== '' ? $expirationDateRu : null);
        $this->em->persist($equipment);
        $this->em->flush();

        return new JsonResponse(['id' => $equipment->getId()], 201);
    }

    #[Route('/assign', name: 'api_equipment_assign_worker', methods: ['POST'])]
    public function assignWorkerEquipment(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['error' => 'Neteisingas užklausos JSON'], 400);
        }

        $workerId    = $data['workerId'] ?? null;
        $equipmentId = $data['equipmentId'] ?? null;
        if (! is_numeric((string) $workerId) || ! is_numeric((string) $equipmentId)) {
            return new JsonResponse(['error' => 'workerId ir equipmentId yra privalomi'], 400);
        }

        $worker = $this->em->getRepository(Worker::class)->find((int) $workerId);
        $equipment = $this->em->getRepository(Equipment::class)->find((int) $equipmentId);
        if (! $worker instanceof Worker || ! $equipment instanceof Equipment) {
            return new JsonResponse(['error' => 'Darbuotojo tipas arba priemonė nerasta'], 404);
        }

        $existing = $this->em->getRepository(WorkerItem::class)
            ->findOneBy(['worker' => $worker, 'equipment' => $equipment]);
        if ($existing instanceof WorkerItem) {
            return new JsonResponse(['status' => 'SUCCESS', 'message' => 'Jau priskirta']);
        }

        $item = new WorkerItem();
        $item->setWorker($worker);
        $item->setEquipment($equipment);
        $this->em->persist($item);
        $this->em->flush();

        return new JsonResponse(['status' => 'SUCCESS']);
    }

    #[Route('/company/{companyId}/data', name: 'api_equipment_company_data', methods: ['GET'], requirements: ['companyId' => '\d+'])]
    public function companyData(int $companyId): JsonResponse
    {
        try {
            $payload = $this->createEquipmentDocument->buildDataByCompanyId($companyId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Nepavyko gauti duomenų: ' . $e->getMessage()], 500);
        }
        return new JsonResponse($payload);
    }
}

