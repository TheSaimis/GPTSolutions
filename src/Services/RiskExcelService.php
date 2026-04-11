<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\BodyPart;
use App\Entity\BodyPartCategory;
use App\Entity\CompanyRequisite;
use App\Entity\RiskCategory;
use App\Entity\RiskGroup;
use App\Entity\RiskList;
use App\Entity\RiskSubcategory;
use App\Entity\Worker;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet as SpreadsheetWorksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

final class RiskExcelService
{
    private const BORDER_THIN = 'thin';
    private const GRAY_FILL   = 'F2F2F2';
    private const TEMPLATE_ABSOLUTE_PATH = 'C:\\Users\\memeh\\Downloads\\AAP lentele nauja.xls';
    /** Vieno darbuotojo blokas (su parašų eilute) užima 27 eilutes. */
    private const TEMPLATE_BLOCK_HEIGHT = 27;
    /**
     * Keep worker blocks contiguous to avoid spacer-only printed pages between blocks.
     */
    private const TEMPLATE_BLOCK_STRIDE = self::TEMPLATE_BLOCK_HEIGHT;
    private const TEMPLATE_MAX_COL = 34;
    /** Kiekvienos lentelės 1 eilutė — vienas sujungimas A:AH (šablonas dažnai praranda kopijuojant blokus). */
    private const BLOCK_MAIN_TITLE = 'Profesinės rizikos veiksnių įvertinimo, parenkant asmenines apsaugos priemones';
    private const COMPANY_ROW_MERGE_END_COL = 10;
    private const WORKER_ROW_MERGE_END_COL   = 29;

    /**
     * Oficialaus AAP šablono parašų plotis: siauras (pareigos) D–G, vidurinis (parašas) H–Q,
     * platus (vardas) R–AH — kitaip ilgas tekstas lieka viename siaurame stulpelyje ir „išteka“ į tinklelį.
     */
    private const SIGNATURE_AAP_PAREIGOS_START_COL = 4;
    private const SIGNATURE_AAP_PAREIGOS_END_COL   = 7;
    private const SIGNATURE_AAP_PARASAS_START_COL  = 8;
    private const SIGNATURE_AAP_PARASAS_END_COL    = 17;
    private const SIGNATURE_AAP_VARDAS_START_COL   = 18;

    /** @var array<int, float> */
    private const SOURCE_WIDTHS = [
        1 => 9.29, 2 => 14.71, 3 => 3.29, 4 => 3.29, 5 => 3.29, 6 => 2.86, 7 => 3.43, 8 => 3.29, 9 => 2.71,
        10 => 2.86, 11 => 4.29, 12 => 4.29, 13 => 3.71, 14 => 3.29, 15 => 3.86, 16 => 4.57, 17 => 2.86,
        18 => 2.57, 19 => 3.86, 20 => 3.86, 21 => 3.86, 22 => 3.00, 23 => 3.57, 24 => 4.29, 25 => 4.14,
        26 => 4.14, 27 => 4.14, 28 => 4.29, 29 => 3.29, 30 => 2.57, 31 => 3.71, 32 => 3.43, 33 => 3.43,
        34 => 3.57,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $projectDir,
    ) {}

    /**
     * @param string|null $nameAndSurname Tekstas virš parašo linijos dešinėje (nekeičia etiketės žemiau).
     * @param string|null $role            Tekstas virš parašo linijos kairėje (nekeičia „(pareigos)“ žemiau).
     */
    public function generate(int $companyId, ?string $nameAndSurname = null, ?string $role = null): string
    {
        $company = $this->em->getRepository(CompanyRequisite::class)->find($companyId);
        if ($company === null) {
            throw new \InvalidArgumentException("Įmonė nerastas: ID {$companyId}");
        }

        $workers = $this->getCompanyWorkers($company);
        $companyName = trim((string) $company->getCompanyName());
        if ($companyName === '') {
            $companyName = 'Nenurodyta įmonė';
        }

        if ($workers === []) {
            throw new \InvalidArgumentException("Įmonei \"{$companyName}\" nepriskirta darbuotojų");
        }

        $bodyParts  = $this->getOrderedBodyParts();

        $templatePath = $this->resolveTemplatePath();
        $spreadsheet  = IOFactory::load($templatePath);
        $sheet        = $spreadsheet->getSheet(0);
        $sheet->setTitle('Rizikos vertinimas');

        $highestRow = $sheet->getHighestRow();
        if ($highestRow > self::TEMPLATE_BLOCK_HEIGHT) {
            $sheet->removeRow(self::TEMPLATE_BLOCK_HEIGHT + 1, $highestRow - self::TEMPLATE_BLOCK_HEIGHT);
        }

        $templateMerges = $this->collectTemplateBlockMerges($sheet);
        $bodyRowByPart  = $this->buildBodyRowMap($bodyParts);

        foreach ($workers as $index => $worker) {
            $blockStart = 1 + ($index * self::TEMPLATE_BLOCK_STRIDE);
            if ($index > 0) {
                $this->copyTemplateBlock($sheet, 1, $blockStart, $templateMerges);
            }

            $riskPoints = $this->buildRiskPoints($worker, $bodyRowByPart);
            $this->fillWorkerBlock(
                $sheet,
                $blockStart,
                $company,
                $companyName,
                $worker,
                $riskPoints,
                $nameAndSurname,
                $role
            );
        }

        $this->applyRiskExportPrintLayout($sheet, count($workers));

        $this->removeYellowFill($sheet);

        $outputDir = $this->projectDir . '/var/risk_export';
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $slug     = preg_replace('/[^\w]+/', '_', $companyName) ?: 'company';
        $filename = 'rizikos_vertinimas_' . $slug . '.xlsx';
        $path     = $outputDir . '/' . $filename;

        $writer = new XlsxWriter($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    // ─── Data fetching ───────────────────────────────

    /** @return Worker[] */
    private function getCompanyWorkers(CompanyRequisite $company): array
    {
        $workers = [];
        foreach ($company->getCompanyWorkers() as $cw) {
            $workers[] = $cw->getWorker();
        }
        usort(
            $workers,
            static fn(Worker $a, Worker $b): int => ((int) $a->getId()) <=> ((int) $b->getId())
        );
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
            $groupColumns = [];
            foreach ($this->getCategoriesForGroup($group) as $cat) {
                foreach ($this->getSubcategoriesForCategory($cat) as $sub) {
                    $groupColumns[] = ['subcategory' => $sub, 'category' => $cat, 'group' => $group];
                }
            }
            foreach ($this->getDirectSubcategoriesForGroup($group) as $sub) {
                $groupColumns[] = ['subcategory' => $sub, 'category' => null, 'group' => $group];
            }

            usort(
                $groupColumns,
                static fn(array $a, array $b): int =>
                    $a['subcategory']->getLineNumber() <=> $b['subcategory']->getLineNumber()
            );

            foreach ($groupColumns as $item) {
                $columns[] = $item;
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

    /**
     * @param array<int, int> $bodyRowByPart
     * @return array<int, array{row:int, col:int}>
     */
    private function buildRiskPoints(Worker $worker, array $bodyRowByPart): array
    {
        $lists = $this->em->getRepository(RiskList::class)
            ->createQueryBuilder('rl')
            ->where('rl.worker = :w')
            ->setParameter('w', $worker)
            ->getQuery()
            ->getResult();

        $points = [];
        /** @var RiskList $rl */
        foreach ($lists as $rl) {
            $bodyPart = $rl->getBodyPart();
            $sub      = $rl->getRiskSubcategory();
            if ($bodyPart === null || $sub === null) {
                continue;
            }

            $partId = (int) $bodyPart->getId();
            if (! isset($bodyRowByPart[$partId])) {
                continue;
            }
            $line = (int) $sub->getLineNumber();
            if ($line < 3 || $line > self::TEMPLATE_MAX_COL) {
                continue;
            }

            $points[] = [
                'row' => $bodyRowByPart[$partId],
                'col' => $line,
            ];
        }

        return $points;
    }

    /** @return BodyPart[] */
    private function getOrderedBodyParts(): array
    {
        $ordered = [];
        foreach ($this->getBodyPartCategories() as $category) {
            foreach ($this->getBodyPartsForCategory($category) as $part) {
                $ordered[] = $part;
            }
        }
        return $ordered;
    }

    private function resolveTemplatePath(): string
    {
        if (is_file(self::TEMPLATE_ABSOLUTE_PATH)) {
            return self::TEMPLATE_ABSOLUTE_PATH;
        }

        $fallback = $this->projectDir . '/otherTemplates/AAP/AAP.xlsx';
        if (is_file($fallback)) {
            return $fallback;
        }

        throw new \RuntimeException('AAP šablono failas nerastas.');
    }

    /**
     * @param array{subcategory: RiskSubcategory, category: ?RiskCategory, group: RiskGroup}[] $columns
     * @return array<int, RiskSubcategory>
     */
    private function buildSubcategoryLineMap(array $columns): array
    {
        $map = [];
        foreach ($columns as $column) {
            $line = $column['subcategory']->getLineNumber();
            if ($line < 3 || $line > self::TEMPLATE_MAX_COL) {
                continue;
            }
            $map[$line] = $column['subcategory'];
        }
        return $map;
    }

    /**
     * @param BodyPart[] $bodyParts
     * @return array<int, int>
     */
    private function buildBodyRowMap(array $bodyParts): array
    {
        $map = [];
        foreach ($bodyParts as $part) {
            $line = $part->getLineNumber();
            if ($line < 1 || $line > 15) {
                continue;
            }
            $map[(int) $part->getId()] = $line;
        }
        return $map;
    }

    /**
     * Order columns exactly by subcategory lineNumber inside each group.
     * This preserves template-like gaps in category row (direct columns stay empty).
     *
     * @param RiskGroup[] $riskGroups
     * @return array<int, array{subcategory: RiskSubcategory, category: ?RiskCategory, group: RiskGroup}>
     */
    private function buildWebsiteColumns(array $riskGroups): array
    {
        $columns = [];
        foreach ($riskGroups as $group) {
            $groupColumns = [];

            foreach ($this->getCategoriesForGroup($group) as $category) {
                foreach ($this->getSubcategoriesForCategory($category) as $subcategory) {
                    $groupColumns[] = [
                        'subcategory' => $subcategory,
                        'category'    => $category,
                        'group'       => $group,
                    ];
                }
            }
            foreach ($this->getDirectSubcategoriesForGroup($group) as $subcategory) {
                $groupColumns[] = [
                    'subcategory' => $subcategory,
                    'category'    => null,
                    'group'       => $group,
                ];
            }

            usort(
                $groupColumns,
                static fn(array $a, array $b): int =>
                    $a['subcategory']->getLineNumber() <=> $b['subcategory']->getLineNumber()
            );

            foreach ($groupColumns as $item) {
                $columns[] = $item;
            }
        }

        return $columns;
    }

    /**
     * @return string[]
     */
    private function collectTemplateBlockMerges(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $ranges = [];
        foreach ($sheet->getMergeCells() as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);
            if ($start[1] >= 1 && $end[1] <= self::TEMPLATE_BLOCK_HEIGHT) {
                $ranges[] = $range;
            }
        }
        return $ranges;
    }

    /**
     * @param string[] $templateMerges
     */
    private function copyTemplateBlock(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $sourceStartRow,
        int $targetStartRow,
        array $templateMerges
    ): void {
        for ($r = 0; $r < self::TEMPLATE_BLOCK_HEIGHT; $r++) {
            $srcRow = $sourceStartRow + $r;
            $dstRow = $targetStartRow + $r;
            $sheet->getRowDimension($dstRow)->setRowHeight($sheet->getRowDimension($srcRow)->getRowHeight());

            for ($c = 1; $c <= self::TEMPLATE_MAX_COL; $c++) {
                $srcCell = $this->colLetter($c) . $srcRow;
                $dstCell = $this->colLetter($c) . $dstRow;
                $sheet->setCellValue($dstCell, $sheet->getCell($srcCell)->getValue());
                $sheet->duplicateStyle($sheet->getStyle($srcCell), $dstCell);
            }
        }

        foreach ($templateMerges as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);
            $shift = $targetStartRow - $sourceStartRow;
            $newRange = $this->colLetter($start[0]) . ($start[1] + $shift)
                . ':'
                . $this->colLetter($end[0]) . ($end[1] + $shift);
            $sheet->mergeCells($newRange);
        }
    }

    /**
     * @param array<int, array{row:int, col:int}> $riskPoints
     */
    private function fillWorkerBlock(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $blockStart,
        CompanyRequisite $company,
        string $companyName,
        Worker $worker,
        array $riskPoints,
        ?string $nameAndSurname = null,
        ?string $role = null,
    ): void {
        $companyRow   = $blockStart + 2;
        $dataStartRow = $blockStart + 7;
        $dataEndRow   = $blockStart + 21;

        $this->ensureMainTitleRowForBlock($sheet, $blockStart);

        $this->unmergeCellsIntersectingRange($sheet, 1, self::TEMPLATE_MAX_COL, $companyRow, $companyRow);

        $companyLabel = trim((string) ($company->getCompanyType() ?? ''));
        $companyText = trim($companyLabel . ' ' . $companyName);
        $sheet->setCellValue('B' . $companyRow, $companyText);
        $sheet->setCellValue('K' . $companyRow, $worker->getName());
        $sheet->setCellValue('AD' . $companyRow, 'darbo vietoje,');

        $bEnd = $this->colLetter(self::COMPANY_ROW_MERGE_END_COL);
        $kEnd = $this->colLetter(self::WORKER_ROW_MERGE_END_COL);
        $sheet->mergeCells('B' . $companyRow . ':' . $bEnd . $companyRow);
        $sheet->mergeCells('K' . $companyRow . ':' . $kEnd . $companyRow);
        $sheet->getStyle('B' . $companyRow . ':' . $bEnd . $companyRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle('K' . $companyRow . ':' . $kEnd . $companyRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle('AD' . $companyRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Keep template header/styling untouched; only fill dynamic values.
        $this->unmergeRiskDataArea($sheet, $dataStartRow, $dataEndRow);

        for ($r = $dataStartRow; $r <= $dataEndRow; $r++) {
            for ($c = 3; $c <= self::TEMPLATE_MAX_COL; $c++) {
                $sheet->setCellValue($this->colLetter($c) . $r, '');
            }
        }

        foreach ($riskPoints as $point) {
            $row = $dataStartRow + ($point['row'] - 1);
            $sheet->setCellValue($this->colLetter($point['col']) . $row, '+');
        }

        $bBodyRange = 'B' . $dataStartRow . ':B' . $dataEndRow;
        $sheet->getStyle($bBodyRange)->getAlignment()
            ->setWrapText(true)
            ->setShrinkToFit(true)
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $this->applyBodyPartCategoryVerticalMerges($sheet, $blockStart);
        $this->applyTemplateBlockFooterMerges($sheet, $blockStart, $role, $nameAndSurname);
    }

    /**
     * Pašalina tik A stulpelio kategorijų sujungimus duomenų zonoje ir perkuria pagal DB
     * (kaip šablone: „Galva“, „Įvairios“ ir kt. apjungtos vertikaliai).
     */
    private function applyBodyPartCategoryVerticalMerges(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $blockStart
    ): void {
        $dataStartRow = $blockStart + 7;
        $dataEndRow   = $blockStart + 21;

        $this->unmergeColumnAOnlyMergesInRowRange($sheet, $dataStartRow, $dataEndRow);

        foreach ($this->getBodyPartCategories() as $bpCat) {
            $lines = [];
            foreach ($this->getBodyPartsForCategory($bpCat) as $bp) {
                $ln = (int) $bp->getLineNumber();
                if ($ln >= 1 && $ln <= 15) {
                    $lines[] = $ln;
                }
            }
            if ($lines === []) {
                continue;
            }
            $lines = array_values(array_unique($lines));
            sort($lines);

            $runs   = [];
            $runMin = $lines[0];
            $runMax = $lines[0];
            for ($i = 1, $n = count($lines); $i < $n; $i++) {
                if ($lines[$i] === $runMax + 1) {
                    $runMax = $lines[$i];
                    continue;
                }
                $runs[] = [$runMin, $runMax];
                $runMin = $lines[$i];
                $runMax = $lines[$i];
            }
            $runs[] = [$runMin, $runMax];

            foreach ($runs as [$lineFrom, $lineTo]) {
                $r1 = $dataStartRow + ($lineFrom - 1);
                $r2 = $dataStartRow + ($lineTo - 1);
                if ($r1 < $dataStartRow || $r2 > $dataEndRow) {
                    continue;
                }
                $sheet->setCellValue('A' . $r1, $bpCat->getName());
                if ($r2 > $r1) {
                    $sheet->mergeCells('A' . $r1 . ':A' . $r2);
                }
                $sheet->getStyle('A' . $r1)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
            }
        }
    }

    /**
     * Nuima sujungimus, kurie yra tik A stulpelyje ir pilnai patenka į [minRow, maxRow].
     */
    private function unmergeColumnAOnlyMergesInRowRange(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $minRow,
        int $maxRow
    ): void {
        $toRemove = [];
        foreach (array_keys($sheet->getMergeCells()) as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);
            if ($start[0] !== 1 || $end[0] !== 1) {
                continue;
            }
            if ($start[1] < $minRow || $end[1] > $maxRow) {
                continue;
            }
            $toRemove[] = $range;
        }
        foreach ($toRemove as $range) {
            $sheet->unmergeCells($range);
        }
    }

    /**
     * Data ir parašų blokas sujungtais langeliais (kaip „AAP lentele nauja.xls“ / tavo pavyzdyje).
     */
    private function applyTemplateBlockFooterMerges(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $blockStart,
        ?string $roleText = null,
        ?string $nameAndSurnameText = null,
    ): void {
        $lastCol         = self::TEMPLATE_MAX_COL;
        $lastLetter      = $this->colLetter($lastCol);
        $dateRow         = $blockStart + 23;
        $filledByRow     = $blockStart + 24;
        $signatureLineRow  = $blockStart + 25;
        $signatureLabelRow = $blockStart + 26;

        $this->unmergeCellsIntersectingRange($sheet, 1, $lastCol, $dateRow, $signatureLabelRow);

        $dateText = date('Y') . 'm. ' . $this->lithuanianMonth((int) date('m')) . ' ' . date('d') . ' d';
        $sheet->setCellValue('A' . $dateRow, $dateText);
        $sheet->mergeCells('A' . $dateRow . ':' . $lastLetter . $dateRow);
        $sheet->getStyle('A' . $dateRow . ':' . $lastLetter . $dateRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Tik viena eilutė — negalima sujungti iki parašų eilučių (būtų persidengiantys merge).
        $filledByRange = 'A' . $filledByRow . ':B' . $filledByRow;
        $sheet->mergeCells($filledByRange);
        $sheet->setCellValue('A' . $filledByRow, 'Lentelę užpildė:');
        $sheet->getStyle($filledByRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        [
            $leftStartCol,
            $leftEndCol,
            $midStartCol,
            $midEndCol,
            $rightStartCol,
            $rightEndCol,
        ] = $this->resolveAapSignatureColumnBounds($lastCol);

        $leftStartLetter  = $this->colLetter($leftStartCol);
        $leftEndLetter    = $this->colLetter($leftEndCol);
        $midStartLetter   = $this->colLetter($midStartCol);
        $midEndLetter     = $this->colLetter($midEndCol);
        $rightStartLetter = $this->colLetter($rightStartCol);
        $rightEndLetter   = $this->colLetter($rightEndCol);

        // Viršutinė eilutė — įrašomas tekstas; linija — tos eilutės apatinis rėmelis; žemiau — šablono etiketės.
        $roleVal            = ($roleText !== null && $roleText !== '') ? $roleText : '';
        $nameAndSurnameVal = ($nameAndSurnameText !== null && $nameAndSurnameText !== '') ? $nameAndSurnameText : '';
        $this->writeSignatureFieldPair(
            $sheet,
            $leftStartLetter,
            $leftEndLetter,
            $signatureLineRow,
            $signatureLabelRow,
            $roleVal,
            '(pareigos)'
        );
        $this->writeSignatureFieldPair(
            $sheet,
            $midStartLetter,
            $midEndLetter,
            $signatureLineRow,
            $signatureLabelRow,
            '',
            '(parašas)'
        );
        $this->writeSignatureFieldPair(
            $sheet,
            $rightStartLetter,
            $rightEndLetter,
            $signatureLineRow,
            $signatureLabelRow,
            $nameAndSurnameVal,
            '(vardo raidė, pavardė)'
        );
    }

    /**
     * @return array{0:int,1:int,2:int,3:int,4:int,5:int} leftStart, leftEnd, midStart, midEnd, rightStart, rightEnd
     */
    private function resolveAapSignatureColumnBounds(int $totalCols): array
    {
        $last = min(max(1, $totalCols), self::TEMPLATE_MAX_COL);
        $ls   = self::SIGNATURE_AAP_PAREIGOS_START_COL;
        $le   = min(self::SIGNATURE_AAP_PAREIGOS_END_COL, $last);
        if ($ls > $last) {
            return [1, $last, 1, $last, 1, $last];
        }
        $ms = max($le + 1, min(self::SIGNATURE_AAP_PARASAS_START_COL, $last));
        $me = min(self::SIGNATURE_AAP_PARASAS_END_COL, $last);
        if ($ms > $me) {
            $me = $ms;
        }
        $rs = max($me + 1, min(self::SIGNATURE_AAP_VARDAS_START_COL, $last));
        $re = $last;
        if ($rs > $re) {
            $rs = $re;
        }

        return [$ls, $le, $ms, $me, $rs, $re];
    }

    /**
     * Parašo zona: viršutinė eilutė — $valueText (įrašas virš linijos); apatinė — $labelText (pvz. „(pareigos)“).
     * Horizontali linija — viršutinės eilutės apatinis rėmelis.
     */
    private function writeSignatureFieldPair(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $startColLetter,
        string $endColLetter,
        int $valueRow,
        int $labelRow,
        string $valueText,
        string $labelText,
    ): void {
        // Šablono eilutė virš parašų dažnai palieka apatinį rėmelį — atrodo kaip linija virš vardo / pareigų.
        if ($valueRow > 1) {
            $aboveRange = $startColLetter . ($valueRow - 1) . ':' . $endColLetter . ($valueRow - 1);
            $sheet->getStyle($aboveRange)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_NONE);
        }

        $valueRange = $startColLetter . $valueRow . ':' . $endColLetter . $valueRow;
        $sheet->mergeCells($valueRange);
        $sheet->setCellValue($startColLetter . $valueRow, $valueText);
        $sheet->getStyle($valueRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $vb = $sheet->getStyle($valueRange)->getBorders();
        $vb->getTop()->setBorderStyle(Border::BORDER_NONE);
        $vb->getBottom()->setBorderStyle(self::BORDER_THIN);

        $labelRange = $startColLetter . $labelRow . ':' . $endColLetter . $labelRow;
        $sheet->mergeCells($labelRange);
        $sheet->setCellValue($startColLetter . $labelRow, $labelText);
        $sheet->getStyle($labelRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $lb = $sheet->getStyle($labelRange)->getBorders();
        $lb->getTop()->setBorderStyle(Border::BORDER_NONE);
    }

    private function unmergeCellsIntersectingRange(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $minCol,
        int $maxCol,
        int $minRow,
        int $maxRow
    ): void {
        $toRemove = [];
        foreach (array_keys($sheet->getMergeCells()) as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);
            if ($end[0] < $minCol || $start[0] > $maxCol || $end[1] < $minRow || $start[1] > $maxRow) {
                continue;
            }
            $toRemove[] = $range;
        }
        foreach ($toRemove as $range) {
            $sheet->unmergeCells($range);
        }
    }

    private function unmergeRiskDataArea(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $dataStartRow,
        int $dataEndRow
    ): void {
        foreach (array_keys($sheet->getMergeCells()) as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);
            $intersectsRows = ! ($end[1] < $dataStartRow || $start[1] > $dataEndRow);
            $intersectsCols = ! ($end[0] < 3 || $start[0] > self::TEMPLATE_MAX_COL);
            if ($intersectsRows && $intersectsCols) {
                $sheet->unmergeCells($range);
            }
        }
    }

    private function removeYellowFill(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestRow();
        for ($r = 1; $r <= $highestRow; $r++) {
            for ($c = 1; $c <= self::TEMPLATE_MAX_COL; $c++) {
                $cell = $this->colLetter($c) . $r;
                $fill = $sheet->getStyle($cell)->getFill();
                if ($fill->getFillType() === Fill::FILL_NONE) {
                    continue;
                }
                $rgb = strtoupper((string) $fill->getStartColor()->getRGB());
                $argb = strtoupper((string) $fill->getStartColor()->getARGB());
                if ($rgb === 'FFFF00' || $argb === 'FFFFFF00') {
                    $fill->setFillType(Fill::FILL_NONE);
                }
            }
        }
    }

    /**
     * Spaudai: nuimti šablono lūžius; naują eilutės lūžį tik prieš kitą darbuotoją (ne mažinti mastelio / eilučių — lentelė lieka pilno dydžio).
     */
    private function applyRiskExportPrintLayout(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $workerCount): void
    {
        foreach (array_values($sheet->getRowBreaks()) as $break) {
            $sheet->setBreak($break->getCoordinate(), SpreadsheetWorksheet::BREAK_NONE);
        }
        foreach (array_values($sheet->getColumnBreaks()) as $break) {
            $sheet->setBreak($break->getCoordinate(), SpreadsheetWorksheet::BREAK_NONE);
        }

        for ($index = 1; $index < $workerCount; $index++) {
            $blockStart = 1 + ($index * self::TEMPLATE_BLOCK_STRIDE);
            $sheet->setBreak('A' . $blockStart - 1, SpreadsheetWorksheet::BREAK_ROW);
        }

        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setFitToPage(false);
        if ($pageSetup->getScale() !== null && $pageSetup->getScale() < 100) {
            $pageSetup->setScale(100);
        }
    }

    private function ensureMainTitleRowForBlock(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $blockStart): void
    {
        $r          = $blockStart;
        $lastLetter = $this->colLetter(self::TEMPLATE_MAX_COL);
        $this->unmergeCellsIntersectingRange($sheet, 1, self::TEMPLATE_MAX_COL, $r, $r);
        $range = 'A' . $r . ':' . $lastLetter . $r;
        $sheet->setCellValue('A' . $r, self::BLOCK_MAIN_TITLE);
        $sheet->mergeCells($range);
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    /**
     * @param array<int, array{subcategory: RiskSubcategory, category: ?RiskCategory, group: RiskGroup}> $websiteColumns
     */
    private function applyWebsiteHeaderLayout(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $blockStart,
        array $websiteColumns
    ): void {
        $groupRow    = $blockStart + 4;
        $categoryRow = $blockStart + 5;
        $subRow      = $blockStart + 6;

        // Remove existing merges in risk header area before creating new spans.
        foreach (array_keys($sheet->getMergeCells()) as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);
            $intersectsRows = ! ($end[1] < $groupRow || $start[1] > $subRow);
            $intersectsCols = ! ($end[0] < 3 || $start[0] > self::TEMPLATE_MAX_COL);
            if ($intersectsRows && $intersectsCols) {
                $sheet->unmergeCells($range);
            }
        }

        // Clear values in risk header area.
        for ($c = 3; $c <= self::TEMPLATE_MAX_COL; $c++) {
            $letter = $this->colLetter($c);
            $sheet->setCellValue($letter . $groupRow, '');
            $sheet->setCellValue($letter . $categoryRow, '');
            $sheet->setCellValue($letter . $subRow, '');
        }

        // Group labels with contiguous spans.
        $runStart = 3;
        $currentGroup = null;
        foreach ($websiteColumns as $index => $colDef) {
            $name = $colDef['group']->getName();
            $col  = 3 + $index;
            if ($currentGroup === null) {
                $currentGroup = $name;
                $runStart = $col;
                continue;
            }
            if ($name !== $currentGroup) {
                $startCell = $this->colLetter($runStart) . $groupRow;
                $endCell   = $this->colLetter($col - 1) . $groupRow;
                $sheet->setCellValue($startCell, $currentGroup);
                if ($startCell !== $endCell) {
                    $sheet->mergeCells($startCell . ':' . $endCell);
                }
                $currentGroup = $name;
                $runStart = $col;
            }
        }
        if ($websiteColumns !== []) {
            $startCell = $this->colLetter($runStart) . $groupRow;
            $endCell   = $this->colLetter(2 + count($websiteColumns)) . $groupRow;
            $sheet->setCellValue($startCell, (string) $currentGroup);
            if ($startCell !== $endCell) {
                $sheet->mergeCells($startCell . ':' . $endCell);
            }
        }

        // Category labels (direct subcategory columns stay empty on this row).
        $runStart = null;
        $currentCategoryId = null;
        $currentCategoryName = '';
        foreach ($websiteColumns as $index => $colDef) {
            $col      = 3 + $index;
            $category = $colDef['category'];

            if ($category === null) {
                if ($runStart !== null) {
                    $startCell = $this->colLetter((int) $runStart) . $categoryRow;
                    $endCell   = $this->colLetter($col - 1) . $categoryRow;
                    $sheet->setCellValue($startCell, $currentCategoryName);
                    if ($startCell !== $endCell) {
                        $sheet->mergeCells($startCell . ':' . $endCell);
                    }
                    $runStart = null;
                    $currentCategoryId = null;
                    $currentCategoryName = '';
                }
                continue;
            }

            $categoryId = (int) $category->getId();
            if ($currentCategoryId === null) {
                $runStart = $col;
                $currentCategoryId = $categoryId;
                $currentCategoryName = $category->getName();
                continue;
            }

            if ($categoryId !== $currentCategoryId) {
                $startCell = $this->colLetter((int) $runStart) . $categoryRow;
                $endCell   = $this->colLetter($col - 1) . $categoryRow;
                $sheet->setCellValue($startCell, $currentCategoryName);
                if ($startCell !== $endCell) {
                    $sheet->mergeCells($startCell . ':' . $endCell);
                }
                $runStart = $col;
                $currentCategoryId = $categoryId;
                $currentCategoryName = $category->getName();
            }
        }
        if ($runStart !== null) {
            $startCell = $this->colLetter((int) $runStart) . $categoryRow;
            $endCell   = $this->colLetter(2 + count($websiteColumns)) . $categoryRow;
            $sheet->setCellValue($startCell, $currentCategoryName);
            if ($startCell !== $endCell) {
                $sheet->mergeCells($startCell . ':' . $endCell);
            }
        }

        // Subcategory names in website order.
        foreach ($websiteColumns as $index => $colDef) {
            $col = 3 + $index;
            $sheet->setCellValue($this->colLetter($col) . $subRow, $colDef['subcategory']->getName());
        }
    }

    private function normalizeHeaderTextStyles(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $blockStart
    ): void {
        $groupRow    = $blockStart + 4;
        $categoryRow = $blockStart + 5;
        $subRow      = $blockStart + 6;

        // Group row: horizontal centered.
        $sheet->getStyle('C' . $groupRow . ':' . $this->colLetter(self::TEMPLATE_MAX_COL) . $groupRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(false)
            ->setTextRotation(0);

        // Category row: horizontal centered.
        $sheet->getStyle('C' . $categoryRow . ':' . $this->colLetter(self::TEMPLATE_MAX_COL) . $categoryRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(false)
            ->setTextRotation(0);

        // Subcategory row: vertical labels everywhere (as in source template).
        $sheet->getStyle('C' . $subRow . ':' . $this->colLetter(self::TEMPLATE_MAX_COL) . $subRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_BOTTOM)
            ->setWrapText(false)
            ->setTextRotation(90);
    }

    private function applyRiskColumnStriping(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $blockStart,
        int $dataEndRow
    ): void {
        $stripeStartRow = $blockStart + 6; // subcategory header row
        $lastColLetter  = $this->colLetter(self::TEMPLATE_MAX_COL);

        for ($c = 3; $c <= self::TEMPLATE_MAX_COL; $c++) {
            $letter = $this->colLetter($c);
            $range  = $letter . $stripeStartRow . ':' . $letter . $dataEndRow;
            $fill   = $sheet->getStyle($range)->getFill();

            if ((($c - 3) % 2) === 1) {
                $fill->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB(self::GRAY_FILL);
            } else {
                $fill->setFillType(Fill::FILL_NONE);
            }
        }

        // Keep left side (A:B) unchanged and ensure no accidental fill overrides.
        $sheet->getStyle('A' . $stripeStartRow . ':B' . $dataEndRow)
            ->getFill()
            ->setFillType(Fill::FILL_NONE);
        $sheet->getStyle('A' . $blockStart . ':' . $lastColLetter . ($blockStart + 5))
            ->getFill()
            ->setFillType(Fill::FILL_NONE);
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
        CompanyRequisite $company,
        string $companyName,
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

        // ── Title: "Profesinės rizikos veiksnių įvertinimo..."
        $sheet->setCellValue('A' . $row, 'Profesinės rizikos veiksnių įvertinimo, parenkant asmenines apsaugos priemones');
        $sheet->mergeCells('A' . $row . ':' . $lastColLetter . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $this->centerRange($sheet, 'A' . $row, $lastColLetter . $row);
        $sheet->getRowDimension($row)->setRowHeight(14);
        $row++;

        // Spacer row to match original template proportions
        $sheet->getRowDimension($row)->setRowHeight(9.75);
        $row++;

        // ── Company | Position | "darbo vietoje."
        $sheet->setCellValue('A' . $row, trim((string) ($company->getCompanyType() ?? '')) . ' ' . $companyName);
        $sheet->mergeCells('A' . $row . ':B' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(false);
        if ($totalCols > $dataStartCol) {
            $sheet->setCellValue($this->colLetter($dataStartCol) . $row, $worker->getName());
            $sheet->mergeCells(
                $this->colLetter($dataStartCol) . $row . ':' . $this->colLetter($totalCols - 1) . $row
            );
            $sheet->getStyle($this->colLetter($dataStartCol) . $row)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setShrinkToFit(false)
                ->setWrapText(false);
            $sheet->setCellValue($lastColLetter . $row, 'darbo vietoje');
        } else {
            $sheet->setCellValue($this->colLetter($dataStartCol) . $row, $worker->getName() . ' darbo vietoje');
        }
        $this->centerRange($sheet, 'A' . $row, $lastColLetter . $row);
        if ($totalCols > $dataStartCol) {
            $sheet->getStyle($this->colLetter($dataStartCol) . $row)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }
        $sheet->getRowDimension($row)->setRowHeight(18.75);
        $row++;

        // ══════════════ TABLE START ══════════════
        $tableStartRow = $row;

        // ── "Darbo aplinkos kenksmingi ir pavojingi veiksniai" merged
        $sheet->setCellValue($this->colLetter($dataStartCol) . $row, 'Darbo aplinkos kenksmingi ir pavojingi veiksniai');
        $sheet->mergeCells($this->colLetter($dataStartCol) . $row . ':' . $lastColLetter . $row);
        $this->centerRange($sheet, 'A' . $row, $lastColLetter . $row);
        $sheet->getRowDimension($row)->setRowHeight(12.75);
        $row++;

        // ── Group header row (Fiziniai, Fizikiniai, ...)
        $groupRow = $row;
        $sheet->setCellValue('A' . $row, 'Kūno dalys');
        $sheet->mergeCells('A' . $row . ':B' . ($row + 2));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $groupRunStartCol = $dataStartCol;
        $currentGroupId   = null;
        $currentGroupName = '';
        foreach ($columns as $index => $colDef) {
            $groupId = (int) $colDef['group']->getId();
            if ($currentGroupId === null) {
                $currentGroupId   = $groupId;
                $currentGroupName = $colDef['group']->getName();
                continue;
            }
            if ($groupId !== $currentGroupId) {
                $startColLetter = $this->colLetter($groupRunStartCol);
                $endColLetter   = $this->colLetter($dataStartCol + $index - 1);
                $sheet->setCellValue($startColLetter . $row, $currentGroupName);
                if ($startColLetter !== $endColLetter) {
                    $sheet->mergeCells($startColLetter . $row . ':' . $endColLetter . $row);
                }
                $groupRunStartCol = $dataStartCol + $index;
                $currentGroupId   = $groupId;
                $currentGroupName = $colDef['group']->getName();
            }
        }
        if ($columns !== []) {
            $startColLetter = $this->colLetter($groupRunStartCol);
            $endColLetter   = $this->colLetter($totalCols);
            $sheet->setCellValue($startColLetter . $row, $currentGroupName);
            if ($startColLetter !== $endColLetter) {
                $sheet->mergeCells($startColLetter . $row . ':' . $endColLetter . $row);
            }
        }
        $this->boldCenter($sheet, $this->colLetter($dataStartCol) . $row, $lastColLetter . $row);
        $sheet->getRowDimension($row)->setRowHeight(12.75);
        $row++;

        // ── Category header row (Mechaniniai, Skysčiai, ...)
        $categoryRow = $row;
        $directSubcategoryIds = [];
        $categoryRunStartCol = null;
        $categoryRunId       = null;
        $categoryRunName     = '';

        foreach ($columns as $index => $sc) {
            $colNumber = $dataStartCol + $index;
            $letter    = $this->colLetter($colNumber);
            $category  = $sc['category'];

            if ($category === null) {
                if ($categoryRunId !== null && $categoryRunStartCol !== null) {
                    $startColLetter = $this->colLetter($categoryRunStartCol);
                    $endColLetter   = $this->colLetter($colNumber - 1);
                    $sheet->setCellValue($startColLetter . $categoryRow, $categoryRunName);
                    if ($startColLetter !== $endColLetter) {
                        $sheet->mergeCells($startColLetter . $categoryRow . ':' . $endColLetter . $categoryRow);
                    }
                    $categoryRunStartCol = null;
                    $categoryRunId       = null;
                    $categoryRunName     = '';
                }

                $sheet->setCellValue($letter . $categoryRow, $sc['subcategory']->getName());
                $sheet->mergeCells($letter . $categoryRow . ':' . $letter . ($categoryRow + 1));
                $sheet->getStyle($letter . $categoryRow)->getAlignment()
                    ->setTextRotation(90)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_BOTTOM)
                    ->setWrapText(false);
                $directSubcategoryIds[(int) $sc['subcategory']->getId()] = true;
                continue;
            }

            $catId = (int) $category->getId();
            if ($categoryRunId === null) {
                $categoryRunStartCol = $colNumber;
                $categoryRunId       = $catId;
                $categoryRunName     = $category->getName();
                continue;
            }

            if ($categoryRunId !== $catId) {
                $startColLetter = $this->colLetter((int) $categoryRunStartCol);
                $endColLetter   = $this->colLetter($colNumber - 1);
                $sheet->setCellValue($startColLetter . $categoryRow, $categoryRunName);
                if ($startColLetter !== $endColLetter) {
                    $sheet->mergeCells($startColLetter . $categoryRow . ':' . $endColLetter . $categoryRow);
                }
                $categoryRunStartCol = $colNumber;
                $categoryRunId       = $catId;
                $categoryRunName     = $category->getName();
            }
        }

        if ($categoryRunId !== null && $categoryRunStartCol !== null) {
            $startColLetter = $this->colLetter($categoryRunStartCol);
            $endColLetter   = $this->colLetter($totalCols);
            $sheet->setCellValue($startColLetter . $categoryRow, $categoryRunName);
            if ($startColLetter !== $endColLetter) {
                $sheet->mergeCells($startColLetter . $categoryRow . ':' . $endColLetter . $categoryRow);
            }
        }
        $this->boldCenter($sheet, $this->colLetter($dataStartCol) . $row, $lastColLetter . $row);
        $row++;

        // ── Subcategory names (vertical text)
        $subHeaderRow = $row;
        $col = $dataStartCol;
        foreach ($columns as $sc) {
            $letter = $this->colLetter($col);
            $subId = (int) $sc['subcategory']->getId();
            if (! isset($directSubcategoryIds[$subId])) {
                $sheet->setCellValue($letter . $row, $sc['subcategory']->getName());
                $sheet->getStyle($letter . $row)->getAlignment()
                    ->setTextRotation(90)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_BOTTOM)
                    ->setWrapText(false);
            }
            $col++;
        }
        // Keep subcategory header cells left-aligned/wrapped; only align frozen left labels.
        $this->centerRange($sheet, 'A' . $row, 'B' . $row);
        $sheet->getRowDimension($row)->setRowHeight(183);
        $row++;

        // ── Data rows
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
                            ->getFont()
                            ->setBold(true);
                        $sheet->getStyle($this->colLetter($col) . $row)
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                            ->setVertical(Alignment::VERTICAL_CENTER);
                    }
                    $col++;
                }

                $sheet->getRowDimension($row)->setRowHeight(15.75);
                $row++;
            }

            $catEndRow = $row - 1;
            if ($catStartRow <= $catEndRow) {
                $sheet->setCellValue('A' . $catStartRow, $bpCat->getName());
                if ($catEndRow > $catStartRow) {
                    $sheet->mergeCells('A' . $catStartRow . ':A' . $catEndRow);
                }
                $sheet->getStyle('A' . $catStartRow)->getFont();
                $sheet->getStyle('A' . $catStartRow)->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
            }
        }
        $dataEndRow = $row - 1;

        if ($dataEndRow >= $subHeaderRow + 1) {
            $sheet->getStyle('B' . ($subHeaderRow + 1) . ':B' . $dataEndRow)->getAlignment()
                ->setWrapText(true)
                ->setShrinkToFit(true)
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // ── Borders for entire table (from "Darbo aplinkos..." to last data row)
        if ($dataEndRow >= $tableStartRow) {
            $tableRange = 'A' . $tableStartRow . ':' . $lastColLetter . $dataEndRow;
            $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(self::BORDER_THIN);
        }

        // ── Alternate gray by risk columns (include subcategory header + data rows)
        $this->applyAlternatingRiskColumnFill(
            $sheet,
            $categoryRow,
            $subHeaderRow,
            $dataEndRow,
            $dataStartCol,
            $columns,
            $directSubcategoryIds
        );

        // ── Column widths
        for ($c = 1; $c <= $totalCols; $c++) {
            $width = self::SOURCE_WIDTHS[$c] ?? 3.3;
            $sheet->getColumnDimension($this->colLetter($c))->setWidth($width);
        }

        // ── Footer (tie patys AAP stulpelių plotiai kaip fillWorkerBlock: D–G, H–Q, R–AH)
        $row++;
        $dateText = date('Y') . 'm. ' . $this->lithuanianMonth((int) date('m')) . ' ' . date('d') . ' d';
        $sheet->setCellValue('A' . $row, $dateText);
        $sheet->mergeCells('A' . $row . ':' . $lastColLetter . $row);
        $sheet->getStyle('A' . $row . ':' . $lastColLetter . $row)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $row += 2;
        $sheet->mergeCells('A' . $row . ':B' . $row);
        $sheet->setCellValue('A' . $row, 'Lentelę užpildė:');
        $sheet->getStyle('A' . $row . ':B' . $row)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $signatureLineRow  = $row + 1;
        $signatureLabelRow = $row + 2;

        [
            $leftStartCol,
            $leftEndCol,
            $midStartCol,
            $midEndCol,
            $rightStartCol,
            $rightEndCol,
        ] = $this->resolveAapSignatureColumnBounds($totalCols);

        $this->writeSignatureFieldPair(
            $sheet,
            $this->colLetter($leftStartCol),
            $this->colLetter($leftEndCol),
            $signatureLineRow,
            $signatureLabelRow,
            '',
            '(pareigos)'
        );
        $this->writeSignatureFieldPair(
            $sheet,
            $this->colLetter($midStartCol),
            $this->colLetter($midEndCol),
            $signatureLineRow,
            $signatureLabelRow,
            '',
            '(parašas)'
        );
        $this->writeSignatureFieldPair(
            $sheet,
            $this->colLetter($rightStartCol),
            $this->colLetter($rightEndCol),
            $signatureLineRow,
            $signatureLabelRow,
            '',
            '(vardo raidė, pavardė)'
        );

        return $signatureLabelRow;
    }

    private function boldCenter(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $startCell,
        string $endCell
    ): void {
        $range = $startCell . ':' . $endCell;
        $sheet->getStyle($range)->getFont();
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(false);
    }

    private function centerRange(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $startCell,
        string $endCell
    ): void {
        $range = $startCell . ':' . $endCell;
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * @param array<int, array{subcategory: RiskSubcategory, category: ?RiskCategory, group: RiskGroup}> $columns
     */
    private function applyAlternatingRiskColumnFill(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $categoryRow,
        int $subHeaderRow,
        int $dataEndRow,
        int $dataStartCol,
        array $columns,
        array $directSubcategoryIds
    ): void {
        if ($dataEndRow < $subHeaderRow) {
            return;
        }

        foreach ($columns as $index => $column) {
            if ($index % 2 === 0) {
                continue;
            }

            $colNumber = $dataStartCol + $index;
            $letter = $this->colLetter($colNumber);
            $subId = (int) $column['subcategory']->getId();
            $startRow = isset($directSubcategoryIds[$subId]) ? $categoryRow : $subHeaderRow;
            $range = $letter . $startRow . ':' . $letter . $dataEndRow;
            $sheet->getStyle($range)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB(self::GRAY_FILL);
        }
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