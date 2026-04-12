<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AapEquipmentCompanyGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AapEquipmentCompanyGroup>
 */
class AapEquipmentCompanyGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AapEquipmentCompanyGroup::class);
    }
}
