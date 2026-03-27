<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CompanyRequisite;
use App\Entity\CompanyWorker;
use App\Entity\Worker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/company-workers')]
final class CompanyWorkerController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/company/{companyId}', name: 'company_worker_by_company', methods: ['GET'])]
    public function byCompany(int $companyId): JsonResponse
    {
        $company = $this->em->getRepository(CompanyRequisite::class)->find($companyId);
        if (! $company instanceof CompanyRequisite) {
            return $this->json(['message' => 'Company not found'], 404);
        }

        $items = $this->em->getRepository(CompanyWorker::class)
            ->createQueryBuilder('cw')
            ->leftJoin('cw.worker', 'w')
            ->leftJoin('cw.companyRequisite', 'c')
            ->addSelect('w', 'c')
            ->where('c.id = :companyId')
            ->setParameter('companyId', $companyId)
            ->addOrderBy('w.name', 'ASC')
            ->addOrderBy('cw.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn (CompanyWorker $item): array => self::serializeItem($item),
            $items
        );

        return $this->json($data);
    }

    #[Route('', name: 'company_worker_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        $companyId = isset($payload['companyId']) ? (int) $payload['companyId'] : 0;
        $workerId = isset($payload['workerId']) ? (int) $payload['workerId'] : 0;

        if ($companyId <= 0 || $workerId <= 0) {
            return $this->json(['message' => 'Fields "companyId" and "workerId" are required'], 400);
        }

        $company = $this->em->getRepository(CompanyRequisite::class)->find($companyId);
        if (! $company instanceof CompanyRequisite) {
            return $this->json(['message' => 'Company not found'], 404);
        }

        $worker = $this->em->getRepository(Worker::class)->find($workerId);
        if (! $worker instanceof Worker) {
            return $this->json(['message' => 'Worker not found'], 404);
        }

        $existing = $this->em->getRepository(CompanyWorker::class)
            ->createQueryBuilder('cw')
            ->where('cw.companyRequisite = :company')
            ->andWhere('cw.worker = :worker')
            ->setParameter('company', $company)
            ->setParameter('worker', $worker)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing instanceof CompanyWorker) {
            return $this->json(['message' => 'CompanyWorker already exists'], 409);
        }

        $item = new CompanyWorker();
        $item->setCompanyRequisite($company);
        $item->setWorker($worker);

        $this->em->persist($item);
        $this->em->flush();

        return $this->json(self::serializeItem($item), 201);
    }

    #[Route('/{id}', name: 'company_worker_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->em->getRepository(CompanyWorker::class)->find($id);
        if (! $item instanceof CompanyWorker) {
            return $this->json(['message' => 'CompanyWorker not found'], 404);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'SUCCESS']);
    }

    private static function serializeItem(CompanyWorker $item): array
    {
        $company = $item->getCompanyRequisite();
        $worker = $item->getWorker();

        return [
            'id' => $item->getId(),
            'company' => $company !== null ? [
                'id' => $company->getId(),
                'companyName' => $company->getCompanyName(),
            ] : null,
            'worker' => $worker !== null ? [
                'id' => $worker->getId(),
                'name' => $worker->getName(),
            ] : null,
        ];
    }
}
