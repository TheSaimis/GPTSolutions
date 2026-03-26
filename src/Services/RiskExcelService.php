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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

final class RiskExcelService
{
    private const BORDER_THIN = Border::BORDER_THIN;
    private const GRAY_FILL   = 'D9D9D9';
    private const YELLOW_FILL = 'FFF200';

    /**
     * Struktūros pradžia:
     * A - kūno dalių kategorija
     * B - kūno dalis
     * C... - rizikų stulpeliai
     */
    private const BODY_PART_CATEGORY_COL = 1; // A
    private const BODY_PART_COL          = 2; // B
    private const DATA_START_COL         = 3; // C

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $projectDir,
    ) {}

    public function generate(int $companyId): string
    {
        $company = $this->em->getRepository(Company::class)->find($companyId);
        if ($company === null) {
            throw new \InvalidArgumentException("Įmonė nerasta: ID {$companyId}");
        }

        $workers = $this->getCompanyWorkers($company);
        if ($workers === []) {
            throw new \InvalidArgumentException("Įmonei \"{$company->getName()}\" nepriskirta darbuotojų");
        }

        $bodyPartCategories = $this->getBodyPartCategories();
        $riskGroups         = $this->getRiskGroups();
        $columnTree         = $this->buildColumnTree($riskGroups);

        if ($columnTree['leafColumns'] === []) {
            throw new \InvalidArgumentException('Nėra rizikos subkategorijų eksporto generavimui');
        }

        $spreadsheet = new Spreadsheet();

        // Pašalinam default sheet turinį - naudosim po vieną sheet kiekvienam worker
        $defaultSheet = $spreadsheet->getActiveSheet();
        $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($defaultSheet));

        foreach ($workers as $index => $worker) {
            $sheetTitle = $this->makeSheetTitle($worker, $index + 1);
            $sheet      = new Worksheet($spreadsheet, $sheetTitle);
            $spreadsheet->addSheet($sheet);

            $riskMap = $this->buildRiskMap($worker);

            $this->renderWorkerSheet(
                $sheet,
                $company,
                $worker,
                $bodyPartCategories,
                $columnTree,
                $riskMap
            );
        }

        if ($spreadsheet->getSheetCount() > 0) {
            $spreadsheet->setActiveSheetIndex(0);
        }

        $outputDir = $this->projectDir . '/var/risk_export';
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $slug     = preg_replace('/[^\p{L}\p{N}]+/u', '_', $company->getName()) ?: 'company';
        $filename = 'rizikos_vertinimas_' . $slug . '.xlsx';
        $path     = $outputDir . '/' . $filename;

        $writer = new XlsxWriter($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    // ─────────────────────────────────────────────
    // Data
    // ─────────────────────────────────────────────

    /** @return Worker[] */
    private function getCompanyWorkers(Company $company): array
    {
        $workers = [];
        foreach ($company->getCompanyWorkers() as $cw) {
            $worker = $cw->getWorker();
            if ($worker !== null) {
                $workers[] = $worker;
            }
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
            $bodyPart      = $rl->getBodyPart();
            $subcategory   = $rl->getRiskSubcategory();

            if ($bodyPart === null || $subcategory === null) {
                continue;
            }

            $key = $bodyPart->getId() . '-' . $subcategory->getId();
            $map[$key] = true;
        }

        return $map;
    }

    /**
     * Sugeneruoja header medį taip, kaip reikia Excel logikai:
     *
     * - row 1: title
     * - row 3: company / worker / darbo vietoje
     * - row 4: merged "Darbo aplinkos..."
     * - row 5: group
     * - row 6: category arba direct subcategory
     * - row 7: leaf subcategory
     *
     * Jei group turi subcategory be category, ji užima row 6:7.
     *
     * @return array{
     *     groups: array<int, array{
     *         group: RiskGroup,
     *         startCol: int,
     *         endCol: int,
     *         items: array<int, array{
     *             type: 'category'|'direct',
     *             category: ?RiskCategory,
     *             directSubcategory: ?RiskSubcategory,
     *             startCol: int,
     *             endCol: int,
     *             leafs: array<int, RiskSubcategory>
     *         }>
     *     }>,
     *     leafColumns: array<int, array{
     *         subcategory: RiskSubcategory,
     *         col: int,
     *         group: RiskGroup,
     *         category: ?RiskCategory
     *     }>,
     *     columnMap: array<int, int>
     * }
     */
    private function buildColumnTree(array $riskGroups): array
    {
        $groups     = [];
        $leafCols   = [];
        $columnMap  = [];
        $currentCol = self::DATA_START_COL;

        foreach ($riskGroups as $group) {
            $groupStartCol = $currentCol;
            $items         = [];

            $categories = $this->getCategoriesForGroup($group);
            foreach ($categories as $category) {
                $subs = $this->getSubcategoriesForCategory($category);
                if ($subs === []) {
                    continue;
                }

                $itemStartCol = $currentCol;
                foreach ($subs as $sub) {
                    $leafCols[] = [
                        'subcategory' => $sub,
                        'col'         => $currentCol,
                        'group'       => $group,
                        'category'    => $category,
                    ];
                    $columnMap[$sub->getId()] = $currentCol;
                    $currentCol++;
                }
                $itemEndCol = $currentCol - 1;

                $items[] = [
                    'type'              => 'category',
                    'category'          => $category,
                    'directSubcategory' => null,
                    'startCol'          => $itemStartCol,
                    'endCol'            => $itemEndCol,
                    'leafs'             => $subs,
                ];
            }

            $directSubs = $this->getDirectSubcategoriesForGroup($group);
            foreach ($directSubs as $sub) {
                $leafCols[] = [
                    'subcategory' => $sub,
                    'col'         => $currentCol,
                    'group'       => $group,
                    'category'    => null,
                ];
                $columnMap[$sub->getId()] = $currentCol;

                $items[] = [
                    'type'              => 'direct',
                    'category'          => null,
                    'directSubcategory' => $sub,
                    'startCol'          => $currentCol,
                    'endCol'            => $currentCol,
                    'leafs'             => [$sub],
                ];

                $currentCol++;
            }

            if ($currentCol > $groupStartCol) {
                $groups[] = [
                    'group'    => $group,
                    'startCol' => $groupStartCol,
                    'endCol'   => $currentCol - 1,
                    'items'    => $items,
                ];
            }
        }

        return [
            'groups'     => $groups,
            'leafColumns'=> $leafCols,
            'columnMap'  => $columnMap,
        ];
    }

    // ─────────────────────────────────────────────
    // Rendering
    // ─────────────────────────────────────────────

    /**
     * @param BodyPartCategory[] $bodyPartCategories
     * @param array{
     *     groups: array<int, array{
     *         group: RiskGroup,
     *         startCol: int,
     *         endCol: int,
     *         items: array<int, array{
     *             type: 'category'|'direct',
     *             category: ?RiskCategory,
     *             directSubcategory: ?RiskSubcategory,
     *             startCol: int,
     *             endCol: int,
     *             leafs: array<int, RiskSubcategory>
     *         }>
     *     }>,
     *     leafColumns: array<int, array{
     *         subcategory: RiskSubcategory,
     *         col: int,
     *         group: RiskGroup,
     *         category: ?RiskCategory
     *     }>,
     *     columnMap: array<int, int>
     * } $columnTree
     * @param array<string, true> $riskMap
     */
    private function renderWorkerSheet(
        Worksheet $sheet,
        Company $company,
        Worker $worker,
        array $bodyPartCategories,
        array $columnTree,
        array $riskMap,
    ): void {
        $leafColumns = $columnTree['leafColumns'];
        $groups      = $columnTree['groups'];

        $lastCol     = self::DATA_START_COL + count($leafColumns) - 1;
        $lastLetter  = $this->colLetter($lastCol);

        // ── Global sheet style
        $sheet->getDefaultRowDimension()->setRowHeight(20);
        $sheet->freezePane('C8');
        $sheet->getSheetView()->setZoomScale(90);

        // ── Row 1: Title
        $sheet->setCellValue('A1', 'Profesinės rizikos veiksnių įvertinimo, parenkant asmenines apsaugos priemones');
        $sheet->mergeCells('A1:' . $lastLetter . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $this->centerRange($sheet, 'A1', $lastLetter . '1');

        // ── Row 3: company / worker / darbo vietoje
        $companyEndCol = max(self::BODY_PART_COL, min(self::DATA_START_COL + 4, $lastCol));
        $workerStartCol = $companyEndCol + 1;
        $workerEndCol = max($workerStartCol, $lastCol - 1);

        $sheet->setCellValue('A3', 'UAB "' . $company->getName() . '"');
        if ($companyEndCol > 1) {
            $sheet->mergeCells('A3:' . $this->colLetter($companyEndCol) . '3');
        }

        $sheet->setCellValue(
            $this->colLetter($workerStartCol) . '3',
            $this->getWorkerDisplayName($worker)
        );
        if ($workerEndCol > $workerStartCol) {
            $sheet->mergeCells(
                $this->colLetter($workerStartCol) . '3:' . $this->colLetter($workerEndCol) . '3'
            );
        }

        $sheet->setCellValue($lastLetter . '3', 'darbo vietoje');
        $sheet->getStyle('A3:' . $lastLetter . '3')->getFont()->setBold(true);
        $this->centerRange($sheet, 'A3', $lastLetter . '3');
        $sheet->getStyle('A3:' . $this->colLetter($companyEndCol) . '3')
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB(self::YELLOW_FILL);

        // ── Row 4: merged label
        $sheet->setCellValue($this->colLetter(self::DATA_START_COL) . '4', 'Darbo aplinkos kenksmingi ir pavojingi veiksniai');
        $sheet->mergeCells($this->colLetter(self::DATA_START_COL) . '4:' . $lastLetter . '4');
        $this->centerRange($sheet, $this->colLetter(self::DATA_START_COL) . '4', $lastLetter . '4');
        $sheet->getStyle($this->colLetter(self::DATA_START_COL) . '4')->getFont()->setBold(true);

        // ── Left header
        $sheet->setCellValue('A5', 'Kūno dalys');
        $sheet->mergeCells('A5:B7');
        $sheet->getStyle('A5')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A5')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // ── Row 5: group headers
        foreach ($groups as $groupNode) {
            $start = $this->colLetter($groupNode['startCol']);
            $end   = $this->colLetter($groupNode['endCol']);

            $sheet->setCellValue($start . '5', $groupNode['group']->getName());
            if ($groupNode['startCol'] < $groupNode['endCol']) {
                $sheet->mergeCells($start . '5:' . $end . '5');
            }

            $sheet->getStyle($start . '5:' . $end . '5')->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);

            $sheet->getStyle($start . '5')->getFont()->setBold(true)->setSize(12);
        }

        // ── Row 6 and 7:
        // category item -> row 6 merged across its leaf columns, leaf labels row 7
        // direct subcategory -> merged vertically row 6:7
        foreach ($groups as $groupNode) {
            foreach ($groupNode['items'] as $item) {
                $start = $this->colLetter($item['startCol']);
                $end   = $this->colLetter($item['endCol']);

                if ($item['type'] === 'category' && $item['category'] !== null) {
                    $sheet->setCellValue($start . '6', $item['category']->getName());
                    if ($item['startCol'] < $item['endCol']) {
                        $sheet->mergeCells($start . '6:' . $end . '6');
                    }

                    $sheet->getStyle($start . '6:' . $end . '6')->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);

                    $sheet->getStyle($start . '6')->getFont()->setBold(true);
                }

                if ($item['type'] === 'direct' && $item['directSubcategory'] !== null) {
                    $sheet->setCellValue($start . '6', $item['directSubcategory']->getName());
                    $sheet->mergeCells($start . '6:' . $start . '7');
                    $sheet->getStyle($start . '6:' . $start . '7')->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setTextRotation(90)
                        ->setWrapText(true);

                    $sheet->getStyle($start . '6')->getFont()->setBold(true);
                }
            }
        }

        // ── Row 7: leaf subcategories under categories
        foreach ($leafColumns as $leaf) {
            if ($leaf['category'] === null) {
                continue;
            }

            $colLetter = $this->colLetter($leaf['col']);
            $sheet->setCellValue($colLetter . '7', $leaf['subcategory']->getName());
            $sheet->getStyle($colLetter . '7')->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_BOTTOM)
                ->setTextRotation(90)
                ->setWrapText(true);
            $sheet->getStyle($colLetter . '7')->getFont()->setBold(false);
        }

        $sheet->getRowDimension(5)->setRowHeight(28);
        $sheet->getRowDimension(6)->setRowHeight(35);
        $sheet->getRowDimension(7)->setRowHeight(170);

        // ── Column widths
        $sheet->getColumnDimension('A')->setWidth(16);
        $sheet->getColumnDimension('B')->setWidth(20);
        for ($col = self::DATA_START_COL; $col <= $lastCol; $col++) {
            $sheet->getColumnDimension($this->colLetter($col))->setWidth(5);
        }

        // ── Data rows from row 8
        $rowMap  = [];
        $row     = 8;
        $stripe  = 0;

        foreach ($bodyPartCategories as $bpCategory) {
            $bodyParts = $this->getBodyPartsForCategory($bpCategory);
            if ($bodyParts === []) {
                continue;
            }

            $categoryStartRow = $row;

            foreach ($bodyParts as $bodyPart) {
                $sheet->setCellValue('B' . $row, $bodyPart->getName());
                $sheet->getStyle('B' . $row)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $rowMap[$bodyPart->getId()] = $row;

                if ($stripe % 2 === 0) {
                    $sheet->getStyle('A' . $row . ':' . $lastLetter . $row)
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB(self::GRAY_FILL);
                }

                $stripe++;
                $row++;
            }

            $categoryEndRow = $row - 1;

            $sheet->setCellValue('A' . $categoryStartRow, $bpCategory->getName());
            if ($categoryEndRow > $categoryStartRow) {
                $sheet->mergeCells('A' . $categoryStartRow . ':A' . $categoryEndRow);
            }

            $sheet->getStyle('A' . $categoryStartRow . ':A' . $categoryEndRow)->getFont()->setBold(true);
            $sheet->getStyle('A' . $categoryStartRow . ':A' . $categoryEndRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }

        $dataEndRow = $row - 1;

        // ── Put "+"
        foreach ($riskMap as $key => $_) {
            [$bodyPartId, $subcategoryId] = array_map('intval', explode('-', $key, 2));

            $targetRow = $rowMap[$bodyPartId] ?? null;
            $targetCol = $columnTree['columnMap'][$subcategoryId] ?? null;

            if ($targetRow === null || $targetCol === null) {
                continue;
            }

            $cell = $this->colLetter($targetCol) . $targetRow;
            $sheet->setCellValue($cell, '+');
            $sheet->getStyle($cell)->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle($cell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // ── Borders
        $tableStartCell = 'A4';
        $tableEndCell   = $lastLetter . $dataEndRow;

        $sheet->getStyle($tableStartCell . ':' . $tableEndCell)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(self::BORDER_THIN);

        // ── Vertical align for data area
        $sheet->getStyle('A8:' . $lastLetter . $dataEndRow)->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);

        // ── Footer
        $footerRow = $dataEndRow + 2;
        $sheet->setCellValue('A' . $footerRow, date('Y') . 'm. ' . $this->lithuanianMonth((int) date('m')) . ' ' . date('d') . ' d');
        $sheet->getStyle('A' . $footerRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB(self::YELLOW_FILL);

        $footerRow += 2;
        $sheet->setCellValue('A' . $footerRow, 'Lentelę užpildė:');

        $signRoleCol  = self::DATA_START_COL;
        $signSignCol  = max(self::DATA_START_COL + 6, (int) floor(($lastCol + self::DATA_START_COL) / 2));
        $signNameCol  = $lastCol;

        $sheet->setCellValue($this->colLetter($signRoleCol) . $footerRow, '(pareigos)');
        $sheet->setCellValue($this->colLetter($signSignCol) . $footerRow, '(parašas)');
        $sheet->setCellValue($this->colLetter($signNameCol) . $footerRow, '(vardo raidė, pavardė)');

        $this->centerRange($sheet, $this->colLetter($signRoleCol) . $footerRow, $this->colLetter($signNameCol) . $footerRow);

        // ── Page setup
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.3);
        $sheet->getPageMargins()->setBottom(0.3);
        $sheet->getPageMargins()->setLeft(0.2);
        $sheet->getPageMargins()->setRight(0.2);
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function getWorkerDisplayName(Worker $worker): string
    {
        if (method_exists($worker, 'getPosition') && is_string($worker->getPosition()) && trim($worker->getPosition()) !== '') {
            return trim($worker->getPosition());
        }

        if (method_exists($worker, 'getJobTitle') && is_string($worker->getJobTitle()) && trim($worker->getJobTitle()) !== '') {
            return trim($worker->getJobTitle());
        }

        if (method_exists($worker, 'getProfession') && is_string($worker->getProfession()) && trim($worker->getProfession()) !== '') {
            return trim($worker->getProfession());
        }

        if (method_exists($worker, 'getName') && is_string($worker->getName()) && trim($worker->getName()) !== '') {
            return trim($worker->getName());
        }

        return 'Darbuotojas';
    }

    private function makeSheetTitle(Worker $worker, int $index): string
    {
        $base = $this->getWorkerDisplayName($worker);
        $base = preg_replace('/[\\\\\\/\\?\\*\\:\\[\\]]+/', ' ', $base) ?? 'Darbuotojas';
        $base = trim($base);

        if ($base === '') {
            $base = 'Darbuotojas_' . $index;
        }

        // Excel sheet title max 31 chars
        $base = mb_substr($base, 0, 31);

        return $base !== '' ? $base : 'Darbuotojas_' . $index;
    }

    private function centerRange(Worksheet $sheet, string $startCell, string $endCell): void
    {
        $range = $startCell . ':' . $endCell;
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    private function lithuanianMonth(int $month): string
    {
        $months = [
            1  => 'sausio',
            2  => 'vasario',
            3  => 'kovo',
            4  => 'balandžio',
            5  => 'gegužės',
            6  => 'birželio',
            7  => 'liepos',
            8  => 'rugpjūčio',
            9  => 'rugsėjo',
            10 => 'spalio',
            11 => 'lapkričio',
            12 => 'gruodžio',
        ];

        return $months[$month] ?? '';
    }

    private function colLetter(int $colNumber): string
    {
        return Coordinate::stringFromColumnIndex($colNumber);
    }
}