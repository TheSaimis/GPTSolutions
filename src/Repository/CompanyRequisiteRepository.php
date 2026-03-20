<?php

namespace App\Repository;

use App\Entity\CompanyRequisite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyRequisite>
 */
class CompanyRequisiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyRequisite::class);
    }

    /**
     * Ar egzistuoja kita įmonė su tokiu pačiu pavadinimu (ignoruojant ištrintas).
     *
     * @param int|null $excludeId Įmonės ID, kurią neįskaityti (update atveju)
     */
    public function existsByName(string $companyName, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.deleted = false')
            ->andWhere('LOWER(TRIM(c.companyName)) = LOWER(:name)')
            ->setParameter('name', trim($companyName));

        if ($excludeId !== null) {
            $qb->andWhere('c.id != :id')->setParameter('id', $excludeId);
        }

        return $qb->select('1')->setMaxResults(1)->getQuery()->getOneOrNullResult() !== null;
    }
}
