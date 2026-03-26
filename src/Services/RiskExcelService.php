<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\BodyPart;
use App\Entity\BodyPartCategory;
use App\Entity\Company;
use App\Entity\RiskCategory;
use App\Entity\RiskGroup;
use App\Entity\RiskList;
use App\Entity\RiskSubcategory;
use App\Entity\Worker;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

final class RiskExcelService
{
    private const HEADER_COLOR = 'FFFF00';
    private const BORDER_THIN  = 'thin';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $projectDir,
    ) {}

    public function generate(int $companyId): string
    {
        $company = $this->em->getRepository(Company::class)->find($companyId);
        if ($company === null) {
            throw new \InvalidArgumentException("Įmonė nerastas: ID {$companyId}");
        }

        $workers = $this->getCompanyWorkers($company);
        if ($workers === []) {
            throw new \InvalidArgumentException("Įmonei \"{$company->getName()}\" nepriskirta darbuotojų");
        }

        $bodyPartCategories = $this->getBodyPartCategories();
        $riskGroups         = $this->getRiskGroups();
        $columns            = $this->buildColumns($riskGroups);

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rizikos vertinimas');

        $currentRow = 1;

        foreach ($workers as $worker) {
            $riskMap    = $this->buildRiskMap($worker);
            $currentRow = $this->renderWorkerTable(
                $sheet,
                $currentRow,
                $company,
                $worker,
                $bodyPartCategories,
                $riskGroups,
                $columns,
                $riskMap
            );
            $currentRow += 2;
        }

        $outputDir = $this->projectDir . '/var/risk_export';
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $slug     = preg_replace('/[^\w]+/', '_', $company->getName()) ?: 'company';
        $filename = 'rizikos_vertinimas_' . $slug . '.xlsx';
        $path     = $outputDir . '/' . $filename;

        $writer = new XlsxWriter($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    // ─── Data fetching ───────────────────────────────

    /** @return Worker[] */
    private function getCompanyWorkers(Company $company): array
    {
        $workers = [];
        foreach ($company->getCompanyWorkers() as $cw) {
            $workers[] = $cw->getWorker();
        }
        return $workers;
    }

    /** @return BodyPartCategory[] */
    private function getBodyPartCategories(): array
    {
        return $this->em->getRepository(BodyPartCategory::class)
            ->createQueryBuilder('c')
            ->addOrderBy('c.lineNumber', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return BodyPart[] */
    private function getBodyPartsForCategory(BodyPartCategory $category): array
    {
        return $this->em->getRepository(BodyPart::class)
            ->createQueryBuilder('bp')
            ->where('bp.category = :cat')
            ->setParameter('cat', $category)
            ->addOrderBy('bp.lineNumber', 'ASC')
            ->addOrderBy('bp.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return RiskGroup[] */
    private function getRiskGroups(): array
    {
        return $this->em->getRepository(RiskGroup::class)
            ->createQueryBuilder('rg')
            ->addOrderBy('rg.lineNumber', 'ASC')
            ->addOrderBy('rg.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return RiskCategory[] */
    private function getCategoriesForGroup(RiskGroup $group): array
    {
        return $this->em->getRepository(RiskCategory::class)
            ->createQueryBuilder('rc')
            ->where('rc.group = :g')
            ->setParameter('g', $group)
            ->addOrderBy('rc.lineNumber', 'ASC')
            ->addOrderBy('rc.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return RiskSubcategory[] */
    private function getSubcategoriesForCategory(RiskCategory $category): array
    {
        return $this->em->getRepository(RiskSubcategory::class)
            ->createQueryBuilder('rs')
            ->where('rs.category = :cat')
            ->setParameter('cat', $category)
            ->addOrderBy('rs.lineNumber', 'ASC')
            ->addOrderBy('rs.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return RiskSubcategory[] subkategorijos tiesiogiai priklausančios grupei (be tarpinės kategorijos) */
    private function getDirectSubcategoriesForGroup(RiskGroup $group): array
    {
        return $this->em->getRepository(RiskSubcategory::class)
            ->createQueryBuilder('rs')
            ->where('rs.group = :g')
            ->andWhere('rs.category IS NULL')
            ->setParameter('g', $group)
            ->addOrderBy('rs.lineNumber', 'ASC')
            ->addOrderBy('rs.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sudaro stulpelių sąrašą: kiekvienas elementas turi subcategory, category (nullable), group.
     *
     * @return array{subcategory: RiskSubcategory, category: ?RiskCategory, group: RiskGroup}[]
     */
    private function buildColumns(array $riskGroups): array
    {
        $columns = [];
        foreach ($riskGroups as $group) {
            $categories = $this->getCategoriesForGroup($group);

            if ($categories === []) {
                foreach ($this->getDirectSubcategoriesForGroup($group) as $sub) {
                    $columns[] = ['subcategory' => $sub, 'category' => null, 'group' => $group];
                }
                continue;
            }

            foreach ($categories as $cat) {
                $subs = $this->getSubcategoriesForCategory($cat);
                if ($subs !== []) {
                    foreach ($subs as $sub) {
                        $columns[] = ['subcategory' => $sub, 'category' => $cat, 'group' => $group];
                    }
                }
            }

            foreach ($this->getDirectSubcategoriesForGroup($group) as $sub) {
                $columns[] = ['subcategory' => $sub, 'category' => null, 'group' => $group];
            }
        }
        return $columns;
    }

    /**
     * @return array<string, true>
     */
    private function buildRiskMap(Worker $worker): array
    {
        $lists = $this->em->getRepository(RiskList::class)
            ->createQueryBuilder('rl')
            ->where('rl.worker = :w')
            ->setParameter('w', $worker)
            ->getQuery()
            ->getResult();

        $map = [];
        /** @var RiskList $rl */
        foreach ($lists as $rl) {
            $key       = $rl->getBodyPart()->getId() . '-' . $rl->getRiskSubcategory()->getId();
            $map[$key] = true;
        }
        return $map;
    }

    // ─── Rendering ───────────────────────────────────

    /**
     * @param RiskGroup[] $riskGroups
     * @param array{subcategory: RiskSubcategory, category: ?RiskCategory, group: RiskGroup}[] $columns
     * @param array<string, true> $riskMap
     */
    private function renderWorkerTable(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $startRow,
        Company $company,
        Worker $worker,
        array $bodyPartCategories,
        array $riskGroups,
        array $columns,
        array $riskMap,
    ): int {
        $dataStartCol  = 3;
        $totalCols     = $dataStartCol + count($columns) - 1;
        $lastColLetter = $this->colLetter($totalCols);

        $row = $startRow;

        // ── Row 1: company name | worker name | "darbo vietoje,"
        $sheet->setCellValue('A' . $row, $company->getName());
        $sheet->setCellValue('C' . $row, $worker->getName());
        $sheet->setCellValue($lastColLetter . $row, 'darbo vietoje,');
        $this->applyHeaderStyle($sheet, 'A' . $row, $lastColLetter . $row);
        $row++;

        // ── Row 2: "Darbo aplinkos kenksmingi ir pavojingi veiksniai"
        $sheet->setCellValue($this->colLetter($dataStartCol) . $row, 'Darbo aplinkos kenksmingi ir pavojingi veiksniai');
        $sheet->mergeCells($this->colLetter($dataStartCol) . $row . ':' . $lastColLetter . $row);
        $this->applyHeaderStyle($sheet, $this->colLetter($dataStartCol) . $row, $lastColLetter . $row);
        $row++;

        // ── Row 3: RiskGroup headers (top-level: Fiziniai, Fizikiniai, ...)
        $groupRow = $row;
        $sheet->setCellValue('A' . $row, 'Kūno dalys');
        $sheet->mergeCells('A' . $row . ':A' . ($row + 1));

        $col = $dataStartCol;
        foreach ($riskGroups as $group) {
            $groupColCount = $this->countColumnsForGroup($group, $columns);
            if ($groupColCount === 0) {
                continue;
            }
            $startColLetter = $this->colLetter($col);
            $endColLetter   = $this->colLetter($col + $groupColCount - 1);
            $sheet->setCellValue($startColLetter . $row, $group->getName());
            if ($groupColCount > 1) {
                $sheet->mergeCells($startColLetter . $row . ':' . $endColLetter . $row);
            }
            $col += $groupColCount;
        }
        $this->applyHeaderStyle($sheet, 'A' . $row, $lastColLetter . $row);
        $row++;

        // ── Row 4: RiskCategory headers (middle level, optional)
        $col          = $dataStartCol;
        $hasAnyCat    = false;
        $prevGroupId  = null;

        foreach ($riskGroups as $group) {
            $categories   = $this->getCategoriesForGroup($group);
            $directSubs   = $this->getDirectSubcategoriesForGroup($group);

            foreach ($categories as $cat) {
                $catSubCount = $this->countColumnsForCategory($cat, $columns);
                if ($catSubCount === 0) {
                    continue;
                }
                $startColLetter = $this->colLetter($col);
                $endColLetter   = $this->colLetter($col + $catSubCount - 1);
                $sheet->setCellValue($startColLetter . $row, $cat->getName());
                if ($catSubCount > 1) {
                    $sheet->mergeCells($startColLetter . $row . ':' . $endColLetter . $row);
                }
                $hasAnyCat = true;
                $col += $catSubCount;
            }

            foreach ($directSubs as $sub) {
                $found = false;
                foreach ($columns as $c) {
                    if ($c['subcategory']->getId() === $sub->getId()) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $col++;
                }
            }
        }
        $this->applyHeaderStyle($sheet, 'A' . $row, $lastColLetter . $row);
        $row++;

        // ── Row 5: Subcategory names (vertical text - individual risk items)
        $col = $dataStartCol;
        foreach ($columns as $sc) {
            $letter = $this->colLetter($col);
            $sheet->setCellValue($letter . $row, $sc['subcategory']->getName());
            $sheet->getStyle($letter . $row)->getAlignment()->setTextRotation(90);
            $sheet->getColumnDimension($letter)->setWidth(4.5);
            $col++;
        }
        $this->applyHeaderStyle($sheet, 'A' . $row, $lastColLetter . $row);
        $sheet->getRowDimension($row)->setRowHeight(120);
        $row++;

        // ── Data rows: body parts grouped by category
        foreach ($bodyPartCategories as $bpCat) {
            $bodyParts = $this->getBodyPartsForCategory($bpCat);
            if ($bodyParts === []) {
                continue;
            }

            $catStartRow = $row;
            foreach ($bodyParts as $bp) {
                $sheet->setCellValue('B' . $row, $bp->getName());

                $col = $dataStartCol;
                foreach ($columns as $sc) {
                    $key = $bp->getId() . '-' . $sc['subcategory']->getId();
                    if (isset($riskMap[$key])) {
                        $sheet->setCellValue($this->colLetter($col) . $row, '+');
                        $sheet->getStyle($this->colLetter($col) . $row)
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                    $col++;
                }
                $row++;
            }

            $catEndRow = $row - 1;
            if ($catStartRow <= $catEndRow) {
                $sheet->setCellValue('A' . $catStartRow, $bpCat->getName());
                if ($catEndRow > $catStartRow) {
                    $sheet->mergeCells('A' . $catStartRow . ':A' . $catEndRow);
                }
                $sheet->getStyle('A' . $catStartRow)->getFont()->setBold(true);
                $sheet->getStyle('A' . $catStartRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            }
        }
        $dataEndRow = $row - 1;

        // ── Borders
        if ($dataEndRow >= $startRow) {
            $tableRange = 'A' . $startRow . ':' . $lastColLetter . $dataEndRow;
            $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(self::BORDER_THIN);
        }

        // ── Column widths
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(20);

        // ── Footer
        $row++;
        $sheet->setCellValue('A' . $row, date('Y') . 'm. dat' . date('m') . ' d');
        $row += 2;
        $sheet->setCellValue('A' . $row, 'Lentelę užpildė:');
        $sheet->setCellValue('C' . $row, '[pareigos]');
        $sheet->setCellValue($this->colLetter((int) floor(($dataStartCol + $totalCols) / 2)) . $row, '[parašas]');
        $sheet->setCellValue($lastColLetter . $row, '[vardo raidė, pavardė]');

        return $row;
    }

    private function countColumnsForGroup(RiskGroup $group, array $columns): int
    {
        $count = 0;
        foreach ($columns as $c) {
            if ($c['group']->getId() === $group->getId()) {
                $count++;
            }
        }
        return $count;
    }

    private function countColumnsForCategory(RiskCategory $cat, array $columns): int
    {
        $count = 0;
        foreach ($columns as $c) {
            if ($c['category'] !== null && $c['category']->getId() === $cat->getId()) {
                $count++;
            }
        }
        return $count;
    }

    private function applyHeaderStyle(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $startCell,
        string $endCell
    ): void {
        $range = $startCell . ':' . $endCell;
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB(self::HEADER_COLOR);
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    private function colLetter(int $colNumber): string
    {
        $letter = '';
        while ($colNumber > 0) {
            $mod        = ($colNumber - 1) % 26;
            $letter     = chr(65 + $mod) . $letter;
            $colNumber  = (int) (($colNumber - $mod) / 26);
        }
        return $letter;
    }
}
