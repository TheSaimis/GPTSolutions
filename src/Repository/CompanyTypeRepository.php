<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompanyType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyType>
 */
final class CompanyTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyType::class);
    }

    public function findOneByTypeShortLoose(string $short): ?CompanyType
    {
        $t = trim($short);
        if ($t === '') {
            return null;
        }

        $row = $this->findOneBy(['typeShort' => $t]);
        if ($row instanceof CompanyType) {
            return $row;
        }

        $noDot = rtrim($t, '.');
        if ($noDot !== $t) {
            $row = $this->findOneBy(['typeShort' => $noDot]);
            if ($row instanceof CompanyType) {
                return $row;
            }
        }

        $withDot = $t . '.';
        $row     = $this->findOneBy(['typeShort' => $withDot]);
        if ($row instanceof CompanyType) {
            return $row;
        }

        $row = $this->findOneBy(['typeShort' => strtoupper($t)]);
        if ($row instanceof CompanyType) {
            return $row;
        }

        if ($noDot !== $t) {
            $row = $this->findOneBy(['typeShort' => strtoupper($noDot)]);
            if ($row instanceof CompanyType) {
                return $row;
            }
        }

        return null;
    }
}
