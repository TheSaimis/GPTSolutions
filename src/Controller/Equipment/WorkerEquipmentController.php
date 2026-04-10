<?php

declare(strict_types=1);

namespace App\Controller\Equipment;

use App\Entity\Equipment;
use App\Entity\Worker;
use App\Entity\WorkerItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/worker-items')]
final class WorkerEquipmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'worker_item_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $workerIdParam = $request->query->get('workerId');
        $equipmentIdParam = $request->query->get('equipmentId');
        $workerId = is_numeric((string) $workerIdParam) ? (int) $workerIdParam : null;
        $equipmentId = is_numeric((string) $equipmentIdParam) ? (int) $equipmentIdParam : null;

        $qb = $this->em->getRepository(WorkerItem::class)
            ->createQueryBuilder('wi')
            ->leftJoin('wi.worker', 'w')
            ->leftJoin('wi.equipment', 'e')
            ->addSelect('w', 'e');

        if ($workerId !== null && $workerId > 0) {
            $qb->andWhere('w.id = :workerId')->setParameter('workerId', $workerId);
        }

        if ($equipmentId !== null && $equipmentId > 0) {
            $qb->andWhere('e.id = :equipmentId')->setParameter('equipmentId', $equipmentId);
        }

        $items = $qb
            ->addOrderBy('w.name', 'ASC')
            ->addOrderBy('e.name', 'ASC')
            ->addOrderBy('wi.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn (WorkerItem $item): array => self::serializeItem($item),
            $items
        );

        return $this->json($data);
    }

    #[Route('/worker/{workerId}', name: 'worker_item_by_worker', methods: ['GET'])]
    public function byWorker(int $workerId): JsonResponse
    {
        $worker = $this->em->getRepository(Worker::class)->find($workerId);
        if (! $worker instanceof Worker) {
            return $this->json(['message' => 'Worker not found'], 404);
        }

        $items = $this->em->getRepository(WorkerItem::class)
            ->createQueryBuilder('wi')
            ->leftJoin('wi.worker', 'w')
            ->leftJoin('wi.equipment', 'e')
            ->addSelect('w', 'e')
            ->where('w.id = :workerId')
            ->setParameter('workerId', $workerId)
            ->addOrderBy('e.name', 'ASC')
            ->addOrderBy('wi.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn (WorkerItem $item): array => self::serializeItem($item),
            $items
        );

        return $this->json($data);
    }

    #[Route('/equipment/{equipmentId}', name: 'worker_item_by_equipment', methods: ['GET'])]
    public function byEquipment(int $equipmentId): JsonResponse
    {
        $equipment = $this->em->getRepository(Equipment::class)->find($equipmentId);
        if (! $equipment instanceof Equipment) {
            return $this->json(['message' => 'Equipment not found'], 404);
        }

        $items = $this->em->getRepository(WorkerItem::class)
            ->createQueryBuilder('wi')
            ->leftJoin('wi.worker', 'w')
            ->leftJoin('wi.equipment', 'e')
            ->addSelect('w', 'e')
            ->where('e.id = :equipmentId')
            ->setParameter('equipmentId', $equipmentId)
            ->addOrderBy('w.name', 'ASC')
            ->addOrderBy('wi.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn (WorkerItem $item): array => self::serializeItem($item),
            $items
        );

        return $this->json($data);
    }

    #[Route('/{id}', name: 'worker_item_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $item = $this->em->getRepository(WorkerItem::class)->find($id);
        if (! $item instanceof WorkerItem) {
            return $this->json(['message' => 'Worker item not found'], 404);
        }

        return $this->json(self::serializeItem($item));
    }

    #[Route('', name: 'worker_item_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        $workerId = isset($payload['workerId']) ? (int) $payload['workerId'] : 0;
        $equipmentId = isset($payload['equipmentId']) ? (int) $payload['equipmentId'] : 0;

        if ($workerId <= 0 || $equipmentId <= 0) {
            return $this->json(['message' => 'Fields "workerId" and "equipmentId" are required'], 400);
        }

        $worker = $this->em->getRepository(Worker::class)->find($workerId);
        if (! $worker instanceof Worker) {
            return $this->json(['message' => 'Worker not found'], 404);
        }

        $equipment = $this->em->getRepository(Equipment::class)->find($equipmentId);
        if (! $equipment instanceof Equipment) {
            return $this->json(['message' => 'Equipment not found'], 404);
        }

        $existing = $this->em->getRepository(WorkerItem::class)
            ->createQueryBuilder('wi')
            ->where('wi.worker = :worker')
            ->andWhere('wi.equipment = :equipment')
            ->setParameter('worker', $worker)
            ->setParameter('equipment', $equipment)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing instanceof WorkerItem) {
            return $this->json(['message' => 'WorkerItem already exists'], 409);
        }

        $item = new WorkerItem();
        $item->setWorker($worker);
        $item->setEquipment($equipment);

        $this->em->persist($item);
        $this->em->flush();

        return $this->json(self::serializeItem($item), 201);
    }

    #[Route('/{id}', name: 'worker_item_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $item = $this->em->getRepository(WorkerItem::class)->find($id);
        if (! $item instanceof WorkerItem) {
            return $this->json(['message' => 'Worker item not found'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        if (array_key_exists('workerId', $payload)) {
            $worker = $this->em->getRepository(Worker::class)->find((int) $payload['workerId']);
            if (! $worker instanceof Worker) {
                return $this->json(['message' => 'Worker not found'], 404);
            }
            $item->setWorker($worker);
        }

        if (array_key_exists('equipmentId', $payload)) {
            $equipment = $this->em->getRepository(Equipment::class)->find((int) $payload['equipmentId']);
            if (! $equipment instanceof Equipment) {
                return $this->json(['message' => 'Equipment not found'], 404);
            }
            $item->setEquipment($equipment);
        }

        $duplicate = $this->em->getRepository(WorkerItem::class)
            ->createQueryBuilder('wi')
            ->where('wi.worker = :worker')
            ->andWhere('wi.equipment = :equipment')
            ->andWhere('wi.id != :id')
            ->setParameter('worker', $item->getWorker())
            ->setParameter('equipment', $item->getEquipment())
            ->setParameter('id', $item->getId())
            ->getQuery()
            ->getOneOrNullResult();

        if ($duplicate instanceof WorkerItem) {
            return $this->json(['message' => 'WorkerItem already exists'], 409);
        }

        $this->em->flush();

        return $this->json(self::serializeItem($item));
    }

    #[Route('/{id}', name: 'worker_item_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $item = $this->em->getRepository(WorkerItem::class)->find($id);
        if (! $item instanceof WorkerItem) {
            return $this->json(['message' => 'Worker item not found'], 404);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'SUCCESS']);
    }

    private static function serializeItem(WorkerItem $item): array
    {
        $worker = $item->getWorker();
        $equipment = $item->getEquipment();

        return [
            'id' => $item->getId(),
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
