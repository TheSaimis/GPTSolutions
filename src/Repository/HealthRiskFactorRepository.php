<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\HealthRiskFactor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HealthRiskFactor>
 */
class HealthRiskFactorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HealthRiskFactor::class);
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('f')
            ->select('1')
            ->where('LOWER(f.name) = LOWER(:name)')
            ->setParameter('name', trim($name))
            ->setMaxResults(1);

        if ($excludeId !== null) {
            $qb->andWhere('f.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    public function existsByCode(string $code, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('f')
            ->select('1')
            ->where('LOWER(f.code) = LOWER(:code)')
            ->setParameter('code', trim($code))
            ->setMaxResults(1);

        if ($excludeId !== null) {
            $qb->andWhere('f.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }
}
