<?php

declare(strict_types=1);

namespace App\Controller\AAP\Risk;

use App\Entity\BodyPart;
use App\Entity\RiskList;
use App\Entity\RiskSubcategory;
use App\Entity\Worker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/risk-lists')]
final class RiskListController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'risk_list_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $items = $this->em->getRepository(RiskList::class)
            ->createQueryBuilder('rl')
            ->leftJoin('rl.bodyPart', 'bp')
            ->leftJoin('rl.riskSubcategory', 'rs')
            ->leftJoin('rl.worker', 'w')
            ->addSelect('bp', 'rs', 'w')
            ->addOrderBy('w.id', 'ASC')
            ->addOrderBy('bp.id', 'ASC')
            ->addOrderBy('rs.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            fn (RiskList $item): array => $this->serializeRiskList($item),
            $items
        );

        return $this->json($data);
    }

    #[Route('/{id}', name: 'risk_list_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $item = $this->em->getRepository(RiskList::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'Rizikos sąrašas nerastas'], 404);
        }

        return $this->json($this->serializeRiskList($item));
    }

    #[Route('', name: 'risk_list_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return $this->json(['message' => 'Neteisingas užklausos JSON'], 400);
        }

        $bodyPartId = $payload['bodyPartId'] ?? null;
        $riskSubcategoryId = $payload['riskSubcategoryId'] ?? null;
        $workerId = $payload['workerId'] ?? null;

        if ($bodyPartId === null || $riskSubcategoryId === null || $workerId === null) {
            return $this->json([
                'message' => 'Būtini laukai „bodyPartId“, „riskSubcategoryId“ ir „workerId“',
            ], 400);
        }

        $bodyPart = $this->em->getRepository(BodyPart::class)->find((int) $bodyPartId);
        if ($bodyPart === null) {
            return $this->json(['message' => 'Kūno dalis nerasta'], 404);
        }

        $riskSubcategory = $this->em->getRepository(RiskSubcategory::class)->find((int) $riskSubcategoryId);
        if ($riskSubcategory === null) {
            return $this->json(['message' => 'Rizikos potipis nerastas'], 404);
        }

        $worker = $this->em->getRepository(Worker::class)->find((int) $workerId);
        if ($worker === null) {
            return $this->json(['message' => 'Darbuotojo tipas nerastas'], 404);
        }

        $existing = $this->em->getRepository(RiskList::class)
            ->createQueryBuilder('rl')
            ->where('rl.bodyPart = :bodyPart')
            ->andWhere('rl.riskSubcategory = :riskSubcategory')
            ->andWhere('rl.worker = :worker')
            ->setParameter('bodyPart', $bodyPart)
            ->setParameter('riskSubcategory', $riskSubcategory)
            ->setParameter('worker', $worker)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing !== null) {
            return $this->json(['message' => 'Toks rizikos sąrašo įrašas jau egzistuoja'], 409);
        }

        $item = new RiskList();
        $item->setBodyPart($bodyPart);
        $item->setRiskSubcategory($riskSubcategory);
        $item->setWorker($worker);

        $this->em->persist($item);
        $this->em->flush();

        return $this->json($this->serializeRiskList($item), 201);
    }

    #[Route('/{id}', name: 'risk_list_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->em->getRepository(RiskList::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'Rizikos sąrašas nerastas'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Neteisingas užklausos JSON'], 400);
        }

        if (array_key_exists('bodyPartId', $payload)) {
            $bodyPart = $this->em->getRepository(BodyPart::class)->find((int) $payload['bodyPartId']);
            if ($bodyPart === null) {
                return $this->json(['message' => 'Kūno dalis nerasta'], 404);
            }
            $item->setBodyPart($bodyPart);
        }

        if (array_key_exists('riskSubcategoryId', $payload)) {
            $riskSubcategory = $this->em->getRepository(RiskSubcategory::class)->find((int) $payload['riskSubcategoryId']);
            if ($riskSubcategory === null) {
                return $this->json(['message' => 'Rizikos potipis nerastas'], 404);
            }
            $item->setRiskSubcategory($riskSubcategory);
        }

        if (array_key_exists('workerId', $payload)) {
            $worker = $this->em->getRepository(Worker::class)->find((int) $payload['workerId']);
            if ($worker === null) {
                return $this->json(['message' => 'Darbuotojo tipas nerastas'], 404);
            }
            $item->setWorker($worker);
        }

        $duplicate = $this->em->getRepository(RiskList::class)
            ->createQueryBuilder('rl')
            ->where('rl.bodyPart = :bodyPart')
            ->andWhere('rl.riskSubcategory = :riskSubcategory')
            ->andWhere('rl.worker = :worker')
            ->andWhere('rl.id != :id')
            ->setParameter('bodyPart', $item->getBodyPart())
            ->setParameter('riskSubcategory', $item->getRiskSubcategory())
            ->setParameter('worker', $item->getWorker())
            ->setParameter('id', $item->getId())
            ->getQuery()
            ->getOneOrNullResult();

        if ($duplicate !== null) {
            return $this->json(['message' => 'Toks rizikos sąrašo įrašas jau egzistuoja'], 409);
        }

        $this->em->flush();

        return $this->json($this->serializeRiskList($item));
    }

    #[Route('/{id}', name: 'risk_list_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->em->getRepository(RiskList::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'Rizikos sąrašas nerastas'], 404);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'SUCCESS']);
    }

    private function serializeRiskList(RiskList $item): array
    {
        $bodyPart = $item->getBodyPart();
        $riskSubcategory = $item->getRiskSubcategory();
        $worker = $item->getWorker();

        return [
            'id' => $item->getId(),
            'bodyPart' => $bodyPart !== null ? [
                'id' => $bodyPart->getId(),
                'name' => $bodyPart->getName(),
                'lineNumber' => $bodyPart->getLineNumber(),
            ] : null,
            'riskSubcategory' => $riskSubcategory !== null ? [
                'id' => $riskSubcategory->getId(),
                'name' => $riskSubcategory->getName(),
                'lineNumber' => $riskSubcategory->getLineNumber(),
            ] : null,
            'worker' => $worker !== null ? [
                'id' => $worker->getId(),
                'name' => $worker->getName(),
            ] : null,
        ];
    }
}