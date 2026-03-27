<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\CompanyRequisite;
use App\Entity\HealthRiskCommonFactor;
use App\Entity\HealthRiskFactor;
use App\Entity\HealthRiskProfile;
use App\Entity\HealthRiskProfileFactor;
use Doctrine\ORM\EntityManagerInterface;

final class HealthRiskService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function buildForCompany(CompanyRequisite $company, ?int $profileId = null): array
    {
        $resolvedProfileId = $profileId;
        if (($resolvedProfileId === null || $resolvedProfileId <= 0) && $company->getHealthRiskProfileId() !== null) {
            $resolvedProfileId = $company->getHealthRiskProfileId();
        }

        return $this->buildForRole((string) ($company->getRole() ?? ''), $resolvedProfileId);
    }

    public function buildForRole(string $role, ?int $profileId = null): array
    {
        $profile = $this->resolveProfile($profileId, $role);
        $items   = $this->collectFactors($profile);

        $factorLines = array_map(static fn(array $i): string => $i['display'], $items);
        $codeLines   = array_map(static fn(array $i): string => $i['code'], $items);
        $tableLines  = array_map(static fn(array $i): string => $i['display'] . ' | ' . $i['code'], $items);

        return [
            'profile'       => $profile !== null ? [
                'id'   => $profile->getId(),
                'name' => $profile->getName(),
            ] : null,
            'role'          => $role,
            'checkupTerm'   => $profile?->getCheckupTerm() ?? 'Tikrintis kas 2 metus',
            'factors'       => $items,
            'factorsText'   => implode("\n", $factorLines),
            'codesText'     => implode("\n", $codeLines),
            'tableText'     => implode("\n", $tableLines),
            'codesCsv'      => implode(', ', $codeLines),
        ];
    }

    /** @return array<int, array{id:int,name:string,code:string,lineNumber:int}> */
    public function listFactors(): array
    {
        $rows = $this->em->getRepository(HealthRiskFactor::class)
            ->createQueryBuilder('f')
            ->addOrderBy('f.lineNumber', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn(HealthRiskFactor $f): array => [
            'id'         => (int) $f->getId(),
            'name'       => $f->getName(),
            'code'       => $f->getCode(),
            'lineNumber' => $f->getLineNumber(),
        ], $rows);
    }

    /** @return array<int, array{id:int,name:string,checkupTerm:string,lineNumber:int}> */
    public function listProfiles(): array
    {
        $rows = $this->em->getRepository(HealthRiskProfile::class)
            ->createQueryBuilder('p')
            ->addOrderBy('p.lineNumber', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn(HealthRiskProfile $p): array => [
            'id'         => (int) $p->getId(),
            'name'       => $p->getName(),
            'checkupTerm'=> $p->getCheckupTerm(),
            'lineNumber' => $p->getLineNumber(),
        ], $rows);
    }

    /** @param array<int, array{factorId:int,note?:string,lineNumber?:int}> $rows */
    public function replaceCommonFactors(array $rows): void
    {
        $repo = $this->em->getRepository(HealthRiskCommonFactor::class);
        foreach ($repo->findAll() as $old) {
            $this->em->remove($old);
        }

        foreach ($rows as $index => $row) {
            $factor = $this->em->getRepository(HealthRiskFactor::class)->find((int) $row['factorId']);
            if (! $factor instanceof HealthRiskFactor) {
                continue;
            }

            $item = new HealthRiskCommonFactor();
            $item->setFactor($factor);
            $item->setNote(isset($row['note']) ? trim((string) $row['note']) : null);
            $item->setLineNumber((int) ($row['lineNumber'] ?? $index));
            $this->em->persist($item);
        }

        $this->em->flush();
    }

    /** @param array<int, array{factorId:int,note?:string,lineNumber?:int}> $rows */
    public function replaceProfileFactors(HealthRiskProfile $profile, array $rows): void
    {
        $repo = $this->em->getRepository(HealthRiskProfileFactor::class);
        foreach ($repo->findBy(['profile' => $profile]) as $old) {
            $this->em->remove($old);
        }

        foreach ($rows as $index => $row) {
            $factor = $this->em->getRepository(HealthRiskFactor::class)->find((int) $row['factorId']);
            if (! $factor instanceof HealthRiskFactor) {
                continue;
            }

            $item = new HealthRiskProfileFactor();
            $item->setProfile($profile);
            $item->setFactor($factor);
            $item->setNote(isset($row['note']) ? trim((string) $row['note']) : null);
            $item->setLineNumber((int) ($row['lineNumber'] ?? $index));
            $this->em->persist($item);
        }

        $this->em->flush();
    }

    private function resolveProfile(?int $profileId, string $role): ?HealthRiskProfile
    {
        if ($profileId !== null && $profileId > 0) {
            $p = $this->em->getRepository(HealthRiskProfile::class)->find($profileId);
            if ($p instanceof HealthRiskProfile) {
                return $p;
            }
        }

        $role = trim($role);
        if ($role === '') {
            return null;
        }

        $matched = $this->em->getRepository(HealthRiskProfile::class)
            ->createQueryBuilder('p')
            ->where('LOWER(TRIM(p.name)) = LOWER(:name)')
            ->setParameter('name', $role)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $matched instanceof HealthRiskProfile ? $matched : null;
    }

    /** @return array<int, array{id:int,name:string,code:string,note:?string,display:string,source:string}> */
    private function collectFactors(?HealthRiskProfile $profile): array
    {
        $result = [];
        $indexByFactorId = [];

        $commonRows = $this->em->getRepository(HealthRiskCommonFactor::class)
            ->createQueryBuilder('cf')
            ->join('cf.factor', 'f')
            ->addOrderBy('cf.lineNumber', 'ASC')
            ->addOrderBy('f.lineNumber', 'ASC')
            ->addOrderBy('cf.id', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var HealthRiskCommonFactor $row */
        foreach ($commonRows as $row) {
            $factor = $row->getFactor();
            if (! $factor instanceof HealthRiskFactor || $factor->getId() === null) {
                continue;
            }
            $item = $this->mapFactorItem($factor, $row->getNote(), 'common');
            $indexByFactorId[$factor->getId()] = count($result);
            $result[] = $item;
        }

        if ($profile instanceof HealthRiskProfile) {
            $profileRows = $this->em->getRepository(HealthRiskProfileFactor::class)
                ->createQueryBuilder('pf')
                ->join('pf.factor', 'f')
                ->where('pf.profile = :profile')
                ->setParameter('profile', $profile)
                ->addOrderBy('pf.lineNumber', 'ASC')
                ->addOrderBy('f.lineNumber', 'ASC')
                ->addOrderBy('pf.id', 'ASC')
                ->getQuery()
                ->getResult();

            /** @var HealthRiskProfileFactor $row */
            foreach ($profileRows as $row) {
                $factor = $row->getFactor();
                if (! $factor instanceof HealthRiskFactor || $factor->getId() === null) {
                    continue;
                }

                $item = $this->mapFactorItem($factor, $row->getNote(), 'profile');
                if (isset($indexByFactorId[$factor->getId()])) {
                    // Profilio nustatymas perrašo bendrą to paties veiksnio įrašą.
                    $result[$indexByFactorId[$factor->getId()]] = $item;
                } else {
                    $indexByFactorId[$factor->getId()] = count($result);
                    $result[] = $item;
                }
            }
        }

        return $result;
    }

    /** @return array{id:int,name:string,code:string,note:?string,display:string,source:string} */
    private function mapFactorItem(HealthRiskFactor $factor, ?string $note, string $source): array
    {
        $cleanNote = $note !== null ? trim($note) : '';
        $display   = $factor->getName();
        if ($cleanNote !== '') {
            $display .= ' (' . $cleanNote . ')';
        }

        return [
            'id'      => (int) $factor->getId(),
            'name'    => $factor->getName(),
            'code'    => $factor->getCode(),
            'note'    => $cleanNote !== '' ? $cleanNote : null,
            'display' => $display,
            'source'  => $source,
        ];
    }
}
