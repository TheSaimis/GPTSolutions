<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BodyPartCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BodyPartCategory>
 */
class BodyPartCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BodyPartCategory::class);
    }
}
