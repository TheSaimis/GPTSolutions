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
    private const BORDER_THIN = 'thin';
    private const GRAY_FILL   = 'D9D9D9';

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

    /** @return RiskSubcategory[] */
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
                foreach ($this->getSubcategoriesForCategory($cat) as $sub) {
                    $columns[] = ['subcategory' => $sub, 'category' => $cat, 'group' => $group];
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
        $dataStartCol  = 3; // column C
        $colCount      = count($columns);
        $totalCols     = $dataStartCol + $colCount - 1;
        $lastColLetter = $this->colLetter($totalCols);
        $midCol        = $this->colLetter((int) floor(($dataStartCol + $totalCols) / 2));
        $bColLetter    = $this->colLetter(2); // B

        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(20);
        for ($c = $dataStartCol; $c <= $totalCols; $c++) {
            $sheet->getColumnDimension($this->colLetter($c))->setWidth(4.5);
        }

        $row = $startRow;

        // ═══════ ROW 1: Title ═══════
        $sheet->setCellValue('A' . $row, 'Profesinės rizikos veiksnių įvertinimo, parenkant asmenines apsaugos priemones');
        $sheet->mergeCells('A' . $row . ':' . $lastColLetter . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A' . $row)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $row++;

        // ═══════ ROW 2: Company | Position | "darbo vietoje." ═══════
        $sheet->setCellValue('A' . $row, $company->getName());
        $sheet->mergeCells('A' . $row . ':B' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        $posEnd = max($dataStartCol, $totalCols - 1);
        $sheet->setCellValue($this->colLetter($dataStartCol) . $row, $worker->getName());
        if ($posEnd > $dataStartCol) {
            $sheet->mergeCells($this->colLetter($dataStartCol) . $row . ':' . $this->colLetter($posEnd) . $row);
        }
        $sheet->getStyle($this->colLetter($dataStartCol) . $row)->getAlignment()->setShrinkToFit(true);
        $sheet->setCellValue($lastColLetter . $row, 'darbo vietoje.');
        $sheet->getStyle('A' . $row . ':' . $lastColLetter . $row)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $row++;

        // ═══════ TABLE START ═══════
        $tableStartRow = $row;

        // ── "Darbo aplinkos kenksmingi ir pavojingi veiksniai"
        $sheet->setCellValue($this->colLetter($dataStartCol) . $row, 'Darbo aplinkos kenksmingi ir pavojingi veiksniai');
        $sheet->mergeCells($this->colLetter($dataStartCol) . $row . ':' . $lastColLetter . $row);
        $this->boldCenter($sheet, 'A' . $row, $lastColLetter . $row);
        $row++;

        // ── Group header row (Fiziniai, Fizikiniai, ...)
        $sheet->setCellValue('A' . $row, 'Kūno dalys');
        $sheet->mergeCells('A' . $row . ':B' . ($row + 1));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $col = $dataStartCol;
        foreach ($riskGroups as $group) {
            $groupColCount = $this->countColumnsForGroup($group, $columns);
            if ($groupColCount === 0) {
                continue;
            }
            $sLetter = $this->colLetter($col);
            $eLetter = $this->colLetter($col + $groupColCount - 1);
            $sheet->setCellValue($sLetter . $row, $group->getName());
            if ($groupColCount > 1) {
                $sheet->mergeCells($sLetter . $row . ':' . $eLetter . $row);
            }
            $col += $groupColCount;
        }
        $this->boldCenter($sheet, $this->colLetter($dataStartCol) . $row, $lastColLetter . $row);
        $row++;

        // ── Category header row (Mechaniniai, Skysčiai, ...)
        $col = $dataStartCol;
        foreach ($riskGroups as $group) {
            $categories = $this->getCategoriesForGroup($group);
            $directSubs = $this->getDirectSubcategoriesForGroup($group);

            foreach ($categories as $cat) {
                $catSubCount = $this->countColumnsForCategory($cat, $columns);
                if ($catSubCount === 0) {
                    continue;
                }
                $sLetter = $this->colLetter($col);
                $eLetter = $this->colLetter($col + $catSubCount - 1);
                $sheet->setCellValue($sLetter . $row, $cat->getName());
                if ($catSubCount > 1) {
                    $sheet->mergeCells($sLetter . $row . ':' . $eLetter . $row);
                }
                $col += $catSubCount;
            }

            foreach ($directSubs as $sub) {
                foreach ($columns as $c) {
                    if ($c['subcategory']->getId() === $sub->getId()) {
                        $col++;
                        break;
                    }
                }
            }
        }
        $this->boldCenter($sheet, $this->colLetter($dataStartCol) . $row, $lastColLetter . $row);
        $row++;

        // ── Subcategory names (vertical text, tall row)
        $col = $dataStartCol;
        foreach ($columns as $sc) {
            $letter = $this->colLetter($col);
            $sheet->setCellValue($letter . $row, $sc['subcategory']->getName());
            $sheet->getStyle($letter . $row)->getAlignment()->setTextRotation(90);
            $col++;
        }
        $this->boldCenter($sheet, 'A' . $row, $lastColLetter . $row);
        $sheet->getRowDimension($row)->setRowHeight(120);
        $row++;

        // ═══════ DATA ROWS (horizontalus pilka/balta nuo B stulpelio) ═══════
        $dataRowIndex = 0;
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
                    }
                    $col++;
                }

                $sheet->getStyle('B' . $row . ':' . $lastColLetter . $row)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                if ($dataRowIndex % 2 === 1) {
                    $sheet->getStyle('B' . $row . ':' . $lastColLetter . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB(self::GRAY_FILL);
                }

                $dataRowIndex++;
                $row++;
            }

            $catEndRow = $row - 1;
            if ($catStartRow <= $catEndRow) {
                $sheet->setCellValue('A' . $catStartRow, $bpCat->getName());
                if ($catEndRow > $catStartRow) {
                    $sheet->mergeCells('A' . $catStartRow . ':A' . $catEndRow);
                }
                $sheet->getStyle('A' . $catStartRow)->getFont()->setBold(true);
                $sheet->getStyle('A' . $catStartRow)->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setWrapText(true);
            }
        }
        $dataEndRow = max($row - 1, $tableStartRow);

        // ── Borders
        $tableRange = 'A' . $tableStartRow . ':' . $lastColLetter . $dataEndRow;
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(self::BORDER_THIN);

        // ═══════ FOOTER ═══════
        $row++;
        $sheet->setCellValue('A' . $row, date('Y') . 'm. ' . $this->lithuanianMonth((int) date('m')) . ' ' . date('d') . ' d');
        $row++;
        $sheet->setCellValue('A' . $row, 'Lentelę užpildė:');
        $sheet->setCellValue($this->colLetter($dataStartCol) . $row, '(pareigos)');
        $sheet->setCellValue($midCol . $row, '(parašas)');
        $sheet->setCellValue($lastColLetter . $row, '(vardo raidė, pavardė)');
        $sheet->getStyle('A' . $row . ':' . $lastColLetter . $row)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return $row;
    }

    // ─── Helpers ──────────────────────────────────────

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

    private function boldCenter(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $startCell,
        string $endCell,
    ): void {
        $range = $startCell . ':' . $endCell;
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    private function lithuanianMonth(int $month): string
    {
        $months = [
            1 => 'sausio', 2 => 'vasario', 3 => 'kovo', 4 => 'balandžio',
            5 => 'gegužės', 6 => 'birželio', 7 => 'liepos', 8 => 'rugpjūčio',
            9 => 'rugsėjo', 10 => 'spalio', 11 => 'lapkričio', 12 => 'gruodžio',
        ];
        return $months[$month] ?? '';
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
