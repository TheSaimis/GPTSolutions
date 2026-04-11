<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AapEquipmentWordTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AapEquipmentWordTemplate>
 */
class AapEquipmentWordTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AapEquipmentWordTemplate::class);
    }

    /**
     * Randa šabloną pagal rūšį ir kalbą (LOWER — sutampa su skirtinga DB reikšmių registracija).
     */
    public function findOneByKindAndLocale(string $kind, string $locale): ?AapEquipmentWordTemplate
    {
        $loc = mb_strtolower(trim($locale));

        return $this->createQueryBuilder('t')
            ->where('t.templateKind = :kind')
            ->andWhere('LOWER(t.templateLocale) = :loc')
            ->setParameter('kind', $kind)
            ->setParameter('loc', $loc)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
