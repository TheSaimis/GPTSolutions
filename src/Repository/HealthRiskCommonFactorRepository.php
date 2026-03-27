<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\HealthRiskCommonFactor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HealthRiskCommonFactor>
 */
class HealthRiskCommonFactorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HealthRiskCommonFactor::class);
    }
}
