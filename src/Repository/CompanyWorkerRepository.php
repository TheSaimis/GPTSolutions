<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompanyWorker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyWorker>
 */
class CompanyWorkerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyWorker::class);
    }
}
