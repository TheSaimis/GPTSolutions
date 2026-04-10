<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AapEquipmentGroupWorker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AapEquipmentGroupWorker>
 */
class AapEquipmentGroupWorkerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AapEquipmentGroupWorker::class);
    }
}
