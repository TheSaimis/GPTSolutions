<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\AapEquipmentGroup;
use App\Entity\AapEquipmentGroupEquipment;
use App\Entity\AapEquipmentGroupWorker;
use App\Entity\CompanyRequisite;
use App\Entity\CompanyWorker;
use App\Entity\CompanyWorkerEquipment;
use App\Entity\Equipment;
use App\Entity\Worker;
use App\Entity\WorkerItem;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Rinkti įmonės darbuotojų tipų ir jiems priskirtų apsaugos priemonių duomenis
 * Word šablonų užpildymui (žr. AapEquipmentWordDocumentService).
 */
final class CreateEquipmentDocument
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @return array{
     *   company: array{id:int,companyName:?string,code:?string,address:?string,cityOrDistrict:?string},
     *   workers: array<int, array{
     *      workerId:int,
     *      workerName:string,
     *      equipment: array<int, array{id:int,name:string,expirationDate:string,unitOfMeasurement:string}>
     *   }>,
     *   groups: array<int, array{
     *      groupId:int,
     *      groupName:string,
     *      workers: array<int, array{workerId:int,workerName:string}>,
     *      equipment: array<int, array{id:int,name:string,expirationDate:string,unitOfMeasurement:string}>
     *   }>
     * }
     */
    public function buildDataByCompanyId(int $companyId): array
    {
        /** @var CompanyRequisite|null $company */
        $company = $this->em->getRepository(CompanyRequisite::class)->find($companyId);
        if (! $company instanceof CompanyRequisite) {
            throw new \InvalidArgumentException('Imone nerasta');
        }

        $companyWorkers = $this->em->getRepository(CompanyWorker::class)
            ->createQueryBuilder('cw')
            ->join('cw.worker', 'w')
            ->where('cw.companyRequisite = :company')
            ->setParameter('company', $company)
            ->addOrderBy('w.id', 'ASC')
            ->getQuery()
            ->getResult();

        $workersData = [];
        /** @var CompanyWorker $cw */
        foreach ($companyWorkers as $cw) {
            $worker = $cw->getWorker();
            if (! $worker instanceof Worker || $worker->getId() === null) {
                continue;
            }

            $equipment = $this->resolveEquipmentRowsForCompanyWorker($company, $worker);

            $workersData[] = [
                'workerId'   => (int) $worker->getId(),
                'workerName' => $worker->getName(),
                'equipment'  => $equipment,
            ];
        }

        return [
            'company' => [
                'id'             => (int) $company->getId(),
                'companyName'    => $company->getCompanyName(),
                'code'           => $company->getCode(),
                'address'        => $company->getAddress(),
                'cityOrDistrict' => $company->getCityOrDistrict(),
            ],
            'workers' => $workersData,
            'groups'  => $this->buildGroupsPayload($company),
        ];
    }

    /**
     * Grupės su nariais — jei masyvas ne tuščias, AAP Word generuojamas po vieną lentelės eilutę grupei.
     *
     * @return list<array{
     *   groupId:int,
     *   groupName:string,
     *   workers: list<array{workerId:int,workerName:string}>,
     *   equipment: list<array{id:int,name:string,expirationDate:string,unitOfMeasurement:string}>
     * }>
     */
    private function buildGroupsPayload(CompanyRequisite $company): array
    {
        /** @var list<AapEquipmentGroup> $groups */
        $groups = $this->em->getRepository(AapEquipmentGroup::class)->findBy(
            ['companyRequisite' => $company],
            ['sortOrder' => 'ASC', 'id' => 'ASC']
        );

        if ($groups === []) {
            return [];
        }

        $out = [];
        foreach ($groups as $g) {
            $workers = [];
            $gwRows = $g->getGroupWorkers()->toArray();
            usort(
                $gwRows,
                static fn (AapEquipmentGroupWorker $a, AapEquipmentGroupWorker $b): int =>
                    strcmp($a->getWorker()?->getName() ?? '', $b->getWorker()?->getName() ?? '')
            );
            foreach ($gwRows as $gw) {
                $w = $gw->getWorker();
                if (! $w instanceof Worker || $w->getId() === null) {
                    continue;
                }
                $workers[] = [
                    'workerId'   => (int) $w->getId(),
                    'workerName' => $w->getName(),
                ];
            }

            $equipment = [];
            $geRows = $g->getGroupEquipment()->toArray();
            usort(
                $geRows,
                static fn (AapEquipmentGroupEquipment $a, AapEquipmentGroupEquipment $b): int =>
                    strcmp($a->getEquipment()?->getName() ?? '', $b->getEquipment()?->getName() ?? '')
            );
            foreach ($geRows as $ge) {
                $eq = $ge->getEquipment();
                if (! $eq instanceof Equipment || $eq->getId() === null) {
                    continue;
                }
                $equipment[] = [
                    'id'                => (int) $eq->getId(),
                    'name'              => $eq->getName(),
                    'expirationDate'    => $eq->getExpirationDate(),
                    'unitOfMeasurement' => $eq->getUnitOfMeasurement(),
                ];
            }

            $out[] = [
                'groupId'   => (int) $g->getId(),
                'groupName' => $g->getName(),
                'workers'   => $workers,
                'equipment' => $equipment,
            ];
        }

        return $out;
    }

    /**
     * Jei įmonei yra bent viena eilutė company_worker_equipment šiam darbuotojų tipui —
     * naudojamas tik tas sąrašas. Kitu atveju – bendras worker_item šablonas.
     *
     * @return list<array{id:int,name:string,expirationDate:string,unitOfMeasurement:string}>
     */
    private function resolveEquipmentRowsForCompanyWorker(CompanyRequisite $company, Worker $worker): array
    {
        $companySpecific = $this->em->getRepository(CompanyWorkerEquipment::class)
            ->createQueryBuilder('cwe')
            ->join('cwe.equipment', 'e')
            ->where('cwe.companyRequisite = :company')
            ->andWhere('cwe.worker = :worker')
            ->setParameter('company', $company)
            ->setParameter('worker', $worker)
            ->addOrderBy('e.name', 'ASC')
            ->addOrderBy('cwe.id', 'ASC')
            ->getQuery()
            ->getResult();

        if ($companySpecific !== []) {
            $equipment = [];
            $seen = [];
            /** @var CompanyWorkerEquipment $row */
            foreach ($companySpecific as $row) {
                $eq = $row->getEquipment();
                if (! $eq instanceof Equipment || $eq->getId() === null) {
                    continue;
                }
                if (isset($seen[$eq->getId()])) {
                    continue;
                }
                $seen[$eq->getId()] = true;
                $equipment[] = [
                    'id'                => (int) $eq->getId(),
                    'name'              => $eq->getName(),
                    'expirationDate'    => $eq->getExpirationDate(),
                    'unitOfMeasurement' => $eq->getUnitOfMeasurement(),
                ];
            }

            return $equipment;
        }

        $workerItems = $this->em->getRepository(WorkerItem::class)
            ->createQueryBuilder('wi')
            ->join('wi.equipment', 'e')
            ->where('wi.worker = :worker')
            ->setParameter('worker', $worker)
            ->addOrderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();

        $equipment = [];
        $seen = [];
        /** @var WorkerItem $wi */
        foreach ($workerItems as $wi) {
            $eq = $wi->getEquipment();
            if (! $eq instanceof Equipment || $eq->getId() === null) {
                continue;
            }
            if (isset($seen[$eq->getId()])) {
                continue;
            }
            $seen[$eq->getId()] = true;

            $equipment[] = [
                'id'                => (int) $eq->getId(),
                'name'              => $eq->getName(),
                'expirationDate'    => $eq->getExpirationDate(),
                'unitOfMeasurement' => $eq->getUnitOfMeasurement(),
            ];
        }

        return $equipment;
    }
}

