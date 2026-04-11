<?php

declare(strict_types=1);

namespace App\Controller\HealthCertificate;

use App\Entity\HealthRiskFactor;
use App\Entity\Worker;
use App\Entity\WorkerRisk;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/health-certificate/worker-risks')]
final class WorkerRiskController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'health_certificate_worker_risk_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $workerIdParam = $request->query->get('workerId');
        $workerId = is_numeric((string) $workerIdParam) ? (int) $workerIdParam : null;

        $qb = $this->em->getRepository(WorkerRisk::class)
            ->createQueryBuilder('wr')
            ->leftJoin('wr.worker', 'w')
            ->leftJoin('wr.riskFactor', 'f')
            ->addSelect('w', 'f');

        if ($workerId !== null && $workerId > 0) {
            $qb->where('w.id = :workerId')->setParameter('workerId', $workerId);
        }

        $items = $qb
            ->addOrderBy('w.name', 'ASC')
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

    #[Route('/worker/{workerId}', name: 'health_certificate_worker_risk_by_worker', methods: ['GET'])]
    public function byWorker(int $workerId): JsonResponse
    {
        $worker = $this->em->getRepository(Worker::class)->find($workerId);
        if (! $worker instanceof Worker) {
            return $this->json(['message' => 'Darbuotojo tipas nerastas'], 404);
        }

        $items = $this->em->getRepository(WorkerRisk::class)
            ->createQueryBuilder('wr')
            ->leftJoin('wr.worker', 'w')
            ->leftJoin('wr.riskFactor', 'f')
            ->addSelect('w', 'f')
            ->where('w.id = :workerId')
            ->setParameter('workerId', $workerId)
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

    #[Route('/{id}', name: 'health_certificate_worker_risk_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $item = $this->em->getRepository(WorkerRisk::class)->find($id);
        if (! $item instanceof WorkerRisk) {
            return $this->json(['message' => 'Sveikatos rizikos įrašas nerastas'], 404);
        }

        return $this->json(self::serializeItem($item));
    }

    #[Route('', name: 'health_certificate_worker_risk_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Neteisingas užklausos JSON'], 400);
        }

        $workerId = isset($payload['workerId']) ? (int) $payload['workerId'] : 0;
        $riskFactorId = isset($payload['riskFactorId']) ? (int) $payload['riskFactorId'] : 0;

        if ($workerId <= 0 || $riskFactorId <= 0) {
            return $this->json(['message' => 'Būtini laukai „workerId“ ir „riskFactorId“'], 400);
        }

        $worker = $this->em->getRepository(Worker::class)->find($workerId);
        if (! $worker instanceof Worker) {
            return $this->json(['message' => 'Darbuotojo tipas nerastas'], 404);
        }

        $riskFactor = $this->em->getRepository(HealthRiskFactor::class)->find($riskFactorId);
        if (! $riskFactor instanceof HealthRiskFactor) {
            return $this->json(['message' => 'Rizikos veiksnys nerastas'], 404);
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
            return $this->json(['message' => 'Toks darbuotojo rizikos įrašas jau egzistuoja'], 409);
        }

        $item = new WorkerRisk();
        $item->setWorker($worker);
        $item->setRiskFactor($riskFactor);

        $this->em->persist($item);
        $this->em->flush();

        return $this->json(self::serializeItem($item), 201);
    }

    #[Route('/{id}', name: 'health_certificate_worker_risk_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->em->getRepository(WorkerRisk::class)->find($id);
        if (! $item instanceof WorkerRisk) {
            return $this->json(['message' => 'Sveikatos rizikos įrašas nerastas'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Neteisingas užklausos JSON'], 400);
        }

        if (array_key_exists('workerId', $payload)) {
            $worker = $this->em->getRepository(Worker::class)->find((int) $payload['workerId']);
            if (! $worker instanceof Worker) {
                return $this->json(['message' => 'Darbuotojo tipas nerastas'], 404);
            }
            $item->setWorker($worker);
        }

        if (array_key_exists('riskFactorId', $payload)) {
            $riskFactor = $this->em->getRepository(HealthRiskFactor::class)->find((int) $payload['riskFactorId']);
            if (! $riskFactor instanceof HealthRiskFactor) {
                return $this->json(['message' => 'Rizikos veiksnys nerastas'], 404);
            }
            $item->setRiskFactor($riskFactor);
        }

        $duplicate = $this->em->getRepository(WorkerRisk::class)
            ->createQueryBuilder('wr')
            ->where('wr.worker = :worker')
            ->andWhere('wr.riskFactor = :riskFactor')
            ->andWhere('wr.id != :id')
            ->setParameter('worker', $item->getWorker())
            ->setParameter('riskFactor', $item->getRiskFactor())
            ->setParameter('id', $item->getId())
            ->getQuery()
            ->getOneOrNullResult();

        if ($duplicate instanceof WorkerRisk) {
            return $this->json(['message' => 'Toks darbuotojo rizikos įrašas jau egzistuoja'], 409);
        }

        $this->em->flush();

        return $this->json(self::serializeItem($item));
    }

    #[Route('/{id}', name: 'health_certificate_worker_risk_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->em->getRepository(WorkerRisk::class)->find($id);
        if (! $item instanceof WorkerRisk) {
            return $this->json(['message' => 'Sveikatos rizikos įrašas nerastas'], 404);
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
                'cipher' => $riskFactor->getCode(),
                'lineNumber' => $riskFactor->getLineNumber(),
            ] : null,
        ];
    }
}
