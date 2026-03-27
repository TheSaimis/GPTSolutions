<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\HealthRiskFactor;
use App\Entity\Worker;
use App\Entity\WorkerRisk;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/worker-risks')]
final class WorkerRiskController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/worker/{workerId}', name: 'worker_risk_by_worker', methods: ['GET'])]
    public function byWorker(int $workerId): JsonResponse
    {
        $worker = $this->em->getRepository(Worker::class)->find($workerId);
        if (! $worker instanceof Worker) {
            return $this->json(['message' => 'Worker not found'], 404);
        }

        $items = $this->em->getRepository(WorkerRisk::class)
            ->createQueryBuilder('wr')
            ->leftJoin('wr.worker', 'w')
            ->leftJoin('wr.riskFactor', 'f')
            ->addSelect('w', 'f')
            ->where('w.id = :workerId')
            ->setParameter('workerId', $workerId)
            ->addOrderBy('f.lineNumber', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->addOrderBy('wr.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn (WorkerRisk $item): array => self::serializeItem($item),
            $items
        );

        return $this->json($data);
    }

    #[Route('', name: 'worker_risk_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        $workerId = isset($payload['workerId']) ? (int) $payload['workerId'] : 0;
        $riskFactorId = isset($payload['riskFactorId']) ? (int) $payload['riskFactorId'] : 0;

        if ($workerId <= 0 || $riskFactorId <= 0) {
            return $this->json(['message' => 'Fields "workerId" and "riskFactorId" are required'], 400);
        }

        $worker = $this->em->getRepository(Worker::class)->find($workerId);
        if (! $worker instanceof Worker) {
            return $this->json(['message' => 'Worker not found'], 404);
        }

        $riskFactor = $this->em->getRepository(HealthRiskFactor::class)->find($riskFactorId);
        if (! $riskFactor instanceof HealthRiskFactor) {
            return $this->json(['message' => 'Risk factor not found'], 404);
        }

        $existing = $this->em->getRepository(WorkerRisk::class)
            ->createQueryBuilder('wr')
            ->where('wr.worker = :worker')
            ->andWhere('wr.riskFactor = :riskFactor')
            ->setParameter('worker', $worker)
            ->setParameter('riskFactor', $riskFactor)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing instanceof WorkerRisk) {
            return $this->json(['message' => 'WorkerRisk already exists'], 409);
        }

        $item = new WorkerRisk();
        $item->setWorker($worker);
        $item->setRiskFactor($riskFactor);

        $this->em->persist($item);
        $this->em->flush();

        return $this->json(self::serializeItem($item), 201);
    }

    #[Route('/{id}', name: 'worker_risk_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->em->getRepository(WorkerRisk::class)->find($id);
        if (! $item instanceof WorkerRisk) {
            return $this->json(['message' => 'WorkerRisk not found'], 404);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'SUCCESS']);
    }

    private static function serializeItem(WorkerRisk $item): array
    {
        $worker = $item->getWorker();
        $riskFactor = $item->getRiskFactor();

        return [
            'id' => $item->getId(),
            'worker' => $worker !== null ? [
                'id' => $worker->getId(),
                'name' => $worker->getName(),
            ] : null,
            'riskFactor' => $riskFactor !== null ? [
                'id' => $riskFactor->getId(),
                'name' => $riskFactor->getName(),
                'code' => $riskFactor->getCode(),
                'lineNumber' => $riskFactor->getLineNumber(),
            ] : null,
        ];
    }
}
