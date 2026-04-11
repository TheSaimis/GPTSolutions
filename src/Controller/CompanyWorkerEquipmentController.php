<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CompanyRequisite;
use App\Entity\CompanyWorker;
use App\Entity\CompanyWorkerEquipment;
use App\Entity\Equipment;
use App\Entity\Worker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/company-worker-equipment')]
final class CompanyWorkerEquipmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'company_worker_equipment_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $companyIdParam = $request->query->get('companyId');
        if (! is_numeric((string) $companyIdParam) || (int) $companyIdParam <= 0) {
            return $this->json(['message' => 'Būtinas užklausos parametras companyId'], 400);
        }

        $companyId = (int) $companyIdParam;
        $company = $this->em->getRepository(CompanyRequisite::class)->find($companyId);
        if (! $company instanceof CompanyRequisite) {
            return $this->json(['message' => 'Įmonė nerasta'], 404);
        }

        $items = $this->em->getRepository(CompanyWorkerEquipment::class)
            ->createQueryBuilder('cwe')
            ->leftJoin('cwe.companyRequisite', 'c')
            ->leftJoin('cwe.worker', 'w')
            ->leftJoin('cwe.equipment', 'e')
            ->addSelect('c', 'w', 'e')
            ->where('c.id = :companyId')
            ->setParameter('companyId', $companyId)
            ->addOrderBy('w.name', 'ASC')
            ->addOrderBy('e.name', 'ASC')
            ->addOrderBy('cwe.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn (CompanyWorkerEquipment $item): array => self::serializeItem($item),
            $items
        );

        return $this->json($data);
    }

    #[Route('', name: 'company_worker_equipment_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Neteisingas užklausos JSON'], 400);
        }

        $companyId = isset($payload['companyId']) ? (int) $payload['companyId'] : 0;
        $workerId = isset($payload['workerId']) ? (int) $payload['workerId'] : 0;
        $equipmentId = isset($payload['equipmentId']) ? (int) $payload['equipmentId'] : 0;

        if ($companyId <= 0 || $workerId <= 0 || $equipmentId <= 0) {
            return $this->json(['message' => 'Būtini laukai companyId, workerId ir equipmentId'], 400);
        }

        $company = $this->em->getRepository(CompanyRequisite::class)->find($companyId);
        if (! $company instanceof CompanyRequisite) {
            return $this->json(['message' => 'Įmonė nerasta'], 404);
        }

        $worker = $this->em->getRepository(Worker::class)->find($workerId);
        if (! $worker instanceof Worker) {
            return $this->json(['message' => 'Darbuotojo tipas nerastas'], 404);
        }

        $equipment = $this->em->getRepository(Equipment::class)->find($equipmentId);
        if (! $equipment instanceof Equipment) {
            return $this->json(['message' => 'Priemonė nerasta'], 404);
        }

        $cw = $this->em->getRepository(CompanyWorker::class)
            ->createQueryBuilder('cw')
            ->where('cw.companyRequisite = :company')
            ->andWhere('cw.worker = :worker')
            ->setParameter('company', $company)
            ->setParameter('worker', $worker)
            ->getQuery()
            ->getOneOrNullResult();

        if (! $cw instanceof CompanyWorker) {
            return $this->json(['message' => 'Šis darbuotojų tipas nepriskirtas šiai įmonei'], 400);
        }

        $existing = $this->em->getRepository(CompanyWorkerEquipment::class)
            ->createQueryBuilder('x')
            ->where('x.companyRequisite = :c')
            ->andWhere('x.worker = :w')
            ->andWhere('x.equipment = :e')
            ->setParameter('c', $company)
            ->setParameter('w', $worker)
            ->setParameter('e', $equipment)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing instanceof CompanyWorkerEquipment) {
            return $this->json(['message' => 'Toks priskyrimas jau egzistuoja'], 409);
        }

        $item = new CompanyWorkerEquipment();
        $item->setCompanyRequisite($company);
        $item->setWorker($worker);
        $item->setEquipment($equipment);
        if (array_key_exists('quantity', $payload)) {
            $item->setQuantity(Equipment::normalizeDocumentQuantity($payload['quantity']));
        }
        $this->em->persist($item);
        $this->em->flush();

        return $this->json(self::serializeItem($item), 201);
    }

    #[Route('/{id}', name: 'company_worker_equipment_patch', methods: ['PATCH'])]
    public function patch(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $item = $this->em->getRepository(CompanyWorkerEquipment::class)->find($id);
        if (! $item instanceof CompanyWorkerEquipment) {
            return $this->json(['message' => 'Nerasta'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Neteisingas užklausos JSON'], 400);
        }

        if (array_key_exists('quantity', $payload)) {
            $item->setQuantity(Equipment::normalizeDocumentQuantity($payload['quantity']));
        }

        $this->em->flush();

        return $this->json(self::serializeItem($item));
    }

    #[Route('/{id}', name: 'company_worker_equipment_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $item = $this->em->getRepository(CompanyWorkerEquipment::class)->find($id);
        if (! $item instanceof CompanyWorkerEquipment) {
            return $this->json(['message' => 'Nerasta'], 404);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'SUCCESS']);
    }

    private static function serializeItem(CompanyWorkerEquipment $item): array
    {
        $company = $item->getCompanyRequisite();
        $worker = $item->getWorker();
        $equipment = $item->getEquipment();

        return [
            'id' => $item->getId(),
            'quantity' => $item->getQuantity(),
            'company' => $company !== null ? [
                'id' => $company->getId(),
                'companyName' => $company->getCompanyName(),
            ] : null,
            'worker' => $worker !== null ? [
                'id' => $worker->getId(),
                'name' => $worker->getName(),
            ] : null,
            'equipment' => $equipment !== null ? [
                'id' => $equipment->getId(),
                'name' => $equipment->getName(),
                'expirationDate' => $equipment->getExpirationDate(),
                'unitOfMeasurement' => $equipment->getUnitOfMeasurement(),
            ] : null,
        ];
    }
}
