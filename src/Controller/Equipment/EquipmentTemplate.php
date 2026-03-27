<?php

declare(strict_types=1);

namespace App\Controller\Equipment;

use App\Entity\Equipment;
use App\Entity\Worker;
use App\Entity\WorkerItem;
use App\Services\AuditLogger;
use App\Services\CreateEquipmentDocument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/equipment-template')]
final class EquipmentTemplate extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CreateEquipmentDocument $createEquipmentDocument,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * POST /api/equipment-template/createTemplate
     * Body: { "companyId": 1 }
     */
    #[Route('/createTemplate', name: 'api_equipment_template_create', methods: ['POST'])]
    public function createTemplate(Request $request): JsonResponse|BinaryFileResponse
    {
        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $companyId = $data['companyId'] ?? null;
        if (! is_int($companyId) && ! ctype_digit((string) $companyId)) {
            return new JsonResponse(['error' => 'companyId is required'], 400);
        }

        try {
            $outputPath = $this->createEquipmentDocument->createByCompanyId((int) $companyId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Nepavyko sugeneruoti AAP dokumento: ' . $e->getMessage()], 500);
        }

        $this->auditLogger->log('Sugeneruotas AAP saraso dokumentas imonei ID=' . (int) $companyId);

        $response = new BinaryFileResponse($outputPath);
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($outputPath)
        );

        return $response;
    }

    #[Route('/equipment', name: 'api_equipment_create', methods: ['POST'])]
    public function createEquipment(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $expirationDate = trim((string) ($data['expirationDate'] ?? $data['ExperationDate'] ?? ''));
        if ($name === '' || $expirationDate === '') {
            return new JsonResponse(['error' => 'name ir expirationDate yra privalomi'], 400);
        }

        $equipment = new Equipment();
        $equipment->setName($name);
        $equipment->setExpirationDate($expirationDate);
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
            return new JsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $workerId    = $data['workerId'] ?? null;
        $equipmentId = $data['equipmentId'] ?? null;
        if (! is_numeric((string) $workerId) || ! is_numeric((string) $equipmentId)) {
            return new JsonResponse(['error' => 'workerId ir equipmentId yra privalomi'], 400);
        }

        $worker = $this->em->getRepository(Worker::class)->find((int) $workerId);
        $equipment = $this->em->getRepository(Equipment::class)->find((int) $equipmentId);
        if (! $worker instanceof Worker || ! $equipment instanceof Equipment) {
            return new JsonResponse(['error' => 'Worker arba Equipment nerastas'], 404);
        }

        $existing = $this->em->getRepository(WorkerItem::class)
            ->findOneBy(['worker' => $worker, 'equipment' => $equipment]);
        if ($existing instanceof WorkerItem) {
            return new JsonResponse(['status' => 'SUCCESS', 'message' => 'Already assigned']);
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
            return new JsonResponse(['error' => 'Nepavyko gauti duomenu: ' . $e->getMessage()], 500);
        }

        return new JsonResponse($payload);
    }
}

