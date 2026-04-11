<?php

declare (strict_types = 1);

namespace App\Command;

use App\Entity\BodyPart;
use App\Entity\BodyPartCategory;
use App\Entity\CompanyRequisite;
use App\Entity\CompanyWorker;
use App\Entity\RiskCategory;
use App\Entity\RiskGroup;
use App\Entity\RiskList;
use App\Entity\RiskSubcategory;
use App\Entity\Worker;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:aap:import-xls',
    description: 'Imports AAP XLS matrix into risk/body-part tables and assignments',
)]
final class ImportAapXlsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Absolute path to .xls file')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Reset AAP/risk/worker import tables before import');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $path = (string) $input->getArgument('path');
        if (! is_file($path)) {
            $io->error(sprintf('Failas nerastas: %s', $path));
            return Command::FAILURE;
        }
        if (! (bool) $input->getOption('reset')) {
            $io->warning('Use --reset for full replacement import.');
            return Command::FAILURE;
        }
        $this->resetImportTables();
        $io->writeln('Old AAP structure deleted.');
        $spreadsheet = IOFactory::load($path);
        $sheet       = $spreadsheet->getSheet(0);
        $riskColumns = $this->extractRiskColumns($sheet);
        $bodyRows    = $this->extractBodyRows($sheet);
        if ($riskColumns === [] || $bodyRows === []) {
            $io->error('Could not parse XLS structure (risk columns or body rows are empty).');
            return Command::FAILURE;
        }
        $taxonomy = $this->importTaxonomy($riskColumns, $bodyRows);
        $this->em->flush();
        $io->success(sprintf(
            'AAP structure replaced: groups=%d, categories=%d, subcategories=%d, bodyPartCategories=%d, bodyParts=%d',
            $taxonomy['createdGroups'],
            $taxonomy['createdCategories'],
            $taxonomy['createdSubcategories'],
            $taxonomy['createdBodyPartCategories'],
            $taxonomy['createdBodyParts']
        ));
        return Command::SUCCESS;
    }

    /**
     * @return array<int, array{col:int, group:string, category:?string, subcategory:string, lineNumber:int}>
     */
    private function extractRiskColumns(Worksheet $sheet): array
    {
        $result = [];

        $groupCarry    = '';
        $categoryCarry = '';

        for ($col = 3; $col <= 34; $col++) {
            $groupVal = $this->cell($sheet, 5, $col);
            if ($groupVal !== '') {
                $groupCarry = $groupVal;
            }

            $categoryVal = $this->cell($sheet, 6, $col);
            if ($categoryVal !== '') {
                $categoryCarry = $categoryVal;
            }

            $subcategoryVal = $this->cell($sheet, 7, $col);

            if ($groupCarry === '' || $categoryCarry === '') {
                continue;
            }

            // If row 7 is empty, this column is a direct subcategory under group.
            $isDirect     = $subcategoryVal === '';
            $result[$col] = [
                'col'         => $col,
                'group'       => $groupCarry,
                'category'    => $isDirect ? null : $categoryCarry,
                'subcategory' => $isDirect ? $categoryCarry : $subcategoryVal,
                'lineNumber'  => $col,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{row:int, category:string, name:string, lineNumber:int}>
     */
    private function extractBodyRows(Worksheet $sheet): array
    {
        $rows = [];
        for ($row = 8; $row <= 22; $row++) {
            $name = $this->cell($sheet, $row, 2);
            if ($name === '') {
                continue;
            }
            $rows[] = [
                'row'        => $row,
                'category'   => $this->cell($sheet, $row, 1),
                'name'       => $name,
                'lineNumber' => count($rows) + 1,
            ];
        }

        $firstCategory = '';
        foreach ($rows as $r) {
            if ($r['category'] !== '') {
                $firstCategory = $r['category'];
                break;
            }
        }

        $lastCategory = $firstCategory;
        foreach ($rows as $i => $r) {
            $category = $r['category'];
            if ($category !== '') {
                // Join split labels such as "Viršutinės" + "galūnės".
                if (
                    $i > 0
                    && mb_strtolower($category, 'UTF-8') === $category
                    && $rows[$i - 1]['category'] !== ''
                    && mb_strtolower($rows[$i - 1]['category'], 'UTF-8') !== $rows[$i - 1]['category']
                ) {
                    $category                 = $rows[$i - 1]['category'] . ' ' . $category;
                    $rows[$i - 1]['category'] = $category;
                }
                $lastCategory = $category;
            } else {
                $category = $lastCategory;
            }

            if (in_array($r['name'], ['Oda', 'Liemuo/ pilvas', 'Poodiniai audiniai', 'Visas kūnas', 'Dalis kūno'], true)) {
                $category = 'Įvairios';
            }

            $rows[$i]['category'] = $category;
        }

        return $rows;
    }

    /**
     * @param array<int, array{col:int, group:string, category:?string, subcategory:string, lineNumber:int}> $riskColumns
     * @param array<int, array{row:int, category:string, name:string, lineNumber:int}> $bodyRows
     * @return array{
     *   subByCol: array<int, RiskSubcategory>,
     *   bodyPartByName: array<string, BodyPart>,
     *   createdGroups:int,
     *   createdCategories:int,
     *   createdSubcategories:int,
     *   createdBodyPartCategories:int,
     *   createdBodyParts:int
     * }
     */
    private function importTaxonomy(array $riskColumns, array $bodyRows): array
    {
        $groupRepo        = $this->em->getRepository(RiskGroup::class);
        $categoryRepo     = $this->em->getRepository(RiskCategory::class);
        $subRepo          = $this->em->getRepository(RiskSubcategory::class);
        $bodyCategoryRepo = $this->em->getRepository(BodyPartCategory::class);
        $bodyPartRepo     = $this->em->getRepository(BodyPart::class);

        $subByCol       = [];
        $bodyPartByName = [];
        $groupCache     = [];
        $categoryCache  = [];
        $subCache       = [];
        $bodyCatCache   = [];

        $createdGroups             = 0;
        $createdCategories         = 0;
        $createdSubcategories      = 0;
        $createdBodyPartCategories = 0;
        $createdBodyParts          = 0;

        foreach ($riskColumns as $col => $def) {
            $groupKey = mb_strtolower($def['group'], 'UTF-8');
            if (! isset($groupCache[$groupKey])) {
                /** @var RiskGroup|null $group */
                $group = $groupRepo->findOneBy(['name' => $def['group']]);
                if ($group === null) {
                    $group = (new RiskGroup())
                        ->setName($def['group'])
                        ->setLineNumber($def['lineNumber']);
                    $this->em->persist($group);
                    $createdGroups++;
                }
                $groupCache[$groupKey] = $group;
            }
            $group = $groupCache[$groupKey];

            $category = null;
            if ($def['category'] !== null) {
                $categoryKey = $groupKey . '|' . mb_strtolower($def['category'], 'UTF-8');
                if (! isset($categoryCache[$categoryKey])) {
                    /** @var RiskCategory|null $foundCategory */
                    $foundCategory = $categoryRepo->findOneBy([
                        'name'  => $def['category'],
                        'group' => $group,
                    ]);
                    if ($foundCategory === null) {
                        $foundCategory = (new RiskCategory())
                            ->setName($def['category'])
                            ->setGroup($group)
                            ->setLineNumber($def['lineNumber']);
                        $this->em->persist($foundCategory);
                        $createdCategories++;
                    }
                    $categoryCache[$categoryKey] = $foundCategory;
                }
                $category = $categoryCache[$categoryKey];
            }

            $subKey = $def['category'] !== null
                ? 'cat|' . mb_strtolower($def['subcategory'], 'UTF-8') . '|' . mb_strtolower($def['category'], 'UTF-8')
                : 'grp|' . mb_strtolower($def['subcategory'], 'UTF-8') . '|' . $groupKey;

            if (! isset($subCache[$subKey])) {
                /** @var RiskSubcategory|null $sub */
                $sub = $subRepo->findOneBy([
                    'name'     => $def['subcategory'],
                    'category' => $category,
                ]);
                if ($sub === null && $category === null) {
                    $sub = $subRepo->findOneBy([
                        'name'  => $def['subcategory'],
                        'group' => $group,
                    ]);
                }

                if ($sub === null) {
                    $sub = (new RiskSubcategory())
                        ->setName($def['subcategory'])
                        ->setCategory($category)
                        ->setLineNumber($def['lineNumber']);
                    if ($category === null) {
                        $sub->setGroup($group);
                    }
                    $this->em->persist($sub);
                    $createdSubcategories++;
                }

                $subCache[$subKey] = $sub;
            }

            $subByCol[$col] = $subCache[$subKey];
        }

        foreach ($bodyRows as $def) {
            $bodyCategoryKey = mb_strtolower($def['category'], 'UTF-8');
            if (! isset($bodyCatCache[$bodyCategoryKey])) {
                /** @var BodyPartCategory|null $bodyCategory */
                $bodyCategory = $bodyCategoryRepo->findOneBy(['name' => $def['category']]);
                if ($bodyCategory === null) {
                    $bodyCategory = (new BodyPartCategory())
                        ->setName($def['category'])
                        ->setLineNumber($def['lineNumber']);
                    $this->em->persist($bodyCategory);
                    $createdBodyPartCategories++;
                }
                $bodyCatCache[$bodyCategoryKey] = $bodyCategory;
            }

            $bodyCategory = $bodyCatCache[$bodyCategoryKey];

            /** @var BodyPart|null $bodyPart */
            $bodyPart = $bodyPartRepo->findOneBy([
                'name'     => $def['name'],
                'category' => $bodyCategory,
            ]);
            if ($bodyPart === null) {
                $bodyPart = (new BodyPart())
                    ->setName($def['name'])
                    ->setCategory($bodyCategory)
                    ->setLineNumber($def['lineNumber']);
                $this->em->persist($bodyPart);
                $createdBodyParts++;
            }

            if (! isset($bodyPartByName[$def['name']])) {
                $bodyPartByName[$def['name']] = $bodyPart;
            }
        }

        $this->em->flush();

        return [
            'subByCol'                  => $subByCol,
            'bodyPartByName'            => $bodyPartByName,
            'createdGroups'             => $createdGroups,
            'createdCategories'         => $createdCategories,
            'createdSubcategories'      => $createdSubcategories,
            'createdBodyPartCategories' => $createdBodyPartCategories,
            'createdBodyParts'          => $createdBodyParts,
        ];
    }

    /**
     * @param array<int, array{row:int, category:string, name:string, lineNumber:int}> $bodyRows
     * @param array<int, array{col:int, group:string, category:?string, subcategory:string, lineNumber:int}> $riskColumns
     * @return array<int, array{company:string, worker:string, pluses:array<int, array{part:string,col:int}>}>
     */
    private function extractWorkerBlocks(Worksheet $sheet, int $bodyRowCount, array $riskColumns): array
    {
        $highestRow = $sheet->getHighestRow();
        $starts     = [];

        for ($row = 1; $row <= $highestRow; $row++) {
            $title = $this->cell($sheet, $row, 9);
            if (str_contains(mb_strtolower($title, 'UTF-8'), 'profesinės rizikos veiksnių įvertinimo')) {
                $starts[] = $row;
            }
        }

        $blocks = [];
        foreach ($starts as $start) {
            $metaRow = $start + 2;
            $company = $this->cell($sheet, $metaRow, 2);
            $worker  = $this->guessWorkerNameFromMetaRow($sheet, $metaRow);
            if ($worker === '') {
                $worker = sprintf('Darbuotojas %d', count($blocks) + 1);
            }

            $pluses = [];
            $row    = $start + 7;
            for ($i = 0; $i < $bodyRowCount; $i++, $row++) {
                $part = $this->cell($sheet, $row, 2);
                if ($part === '') {
                    continue;
                }
                foreach ($riskColumns as $col => $_colDef) {
                    $v = $this->cell($sheet, $row, $col);
                    if (str_contains($v, '+')) {
                        $pluses[] = ['part' => $part, 'col' => $col];
                    }
                }
            }

            $blocks[] = [
                'company' => $company !== '' ? $company : 'UAB "XXXXX"',
                'worker'  => $worker,
                'pluses'  => $pluses,
            ];
        }

        return $blocks;
    }

    /**
     * @param array<int, array{company:string, worker:string, pluses:array<int, array{part:string,col:int}>}> $blocks
     * @param array<int, array{row:int, category:string, name:string, lineNumber:int}> $bodyRows
     * @param array<int, RiskSubcategory> $subByCol
     * @param array<string, BodyPart> $bodyPartByName
     * @return array{companies:int, workers:int, riskLists:int}
     */
    private function importWorkerData(array $blocks, array $bodyRows, array $subByCol, array $bodyPartByName): array
    {
        $companyRepo       = $this->em->getRepository(CompanyRequisite::class);
        $workerRepo        = $this->em->getRepository(Worker::class);
        $companyWorkerRepo = $this->em->getRepository(CompanyWorker::class);
        $riskListRepo      = $this->em->getRepository(RiskList::class);

        $createdCompanies = 0;
        $createdWorkers   = 0;
        $createdRiskLists = 0;

        $defaultBodyByOrder = array_values(array_unique(array_map(static fn(array $r) => $r['name'], $bodyRows)));

        foreach ($blocks as $block) {
            /** @var CompanyRequisite|null $company */
            $company = $companyRepo->findOneBy(['companyName' => $block['company'], 'deleted' => false]);
            if ($company === null) {
                $company = (new CompanyRequisite())
                    ->setCompanyName($block['company'])
                    ->setCode(null);
                $this->em->persist($company);
                $createdCompanies++;
            }

            /** @var Worker|null $worker */
            $worker = $workerRepo->findOneBy(['name' => $block['worker']]);
            if ($worker === null) {
                $worker = (new Worker())->setName($block['worker']);
                $this->em->persist($worker);
                $createdWorkers++;
            }

            /** @var CompanyWorker|null $companyWorker */
            $companyWorker = $companyWorkerRepo->findOneBy([
                'companyRequisite' => $company,
                'worker'           => $worker,
            ]);
            if ($companyWorker === null) {
                $companyWorker = (new CompanyWorker())
                    ->setCompanyRequisite($company)
                    ->setWorker($worker);
                $this->em->persist($companyWorker);
            }

            foreach ($block['pluses'] as $idx => $plus) {
                $partName = $plus['part'] !== '' ? $plus['part'] : ($defaultBodyByOrder[$idx] ?? null);
                if ($partName === null || ! isset($bodyPartByName[$partName]) || ! isset($subByCol[$plus['col']])) {
                    continue;
                }

                $bodyPart    = $bodyPartByName[$partName];
                $subcategory = $subByCol[$plus['col']];

                /** @var RiskList|null $existing */
                $existing = $riskListRepo->findOneBy([
                    'worker'          => $worker,
                    'bodyPart'        => $bodyPart,
                    'riskSubcategory' => $subcategory,
                ]);
                if ($existing !== null) {
                    continue;
                }

                $riskList = (new RiskList())
                    ->setWorker($worker)
                    ->setBodyPart($bodyPart)
                    ->setRiskSubcategory($subcategory);
                $this->em->persist($riskList);
                $createdRiskLists++;
            }
        }

        return [
            'companies' => $createdCompanies,
            'workers'   => $createdWorkers,
            'riskLists' => $createdRiskLists,
        ];
    }

    private function guessWorkerNameFromMetaRow(Worksheet $sheet, int $row): string
    {
        $best = '';
        for ($col = 3; $col <= 34; $col++) {
            $v = $this->cell($sheet, $row, $col);
            if (
                $v !== ''
                && ! str_contains(mb_strtolower($v, 'UTF-8'), 'darbo vietoje')
                && mb_strlen($v, 'UTF-8') > mb_strlen($best, 'UTF-8')
            ) {
                $best = $v;
            }
        }
        return $best;
    }

    private function resetImportTables(): void
    {
        $conn = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            $conn->executeStatement('DELETE FROM risk_list');
            $conn->executeStatement('DELETE FROM body_part');
            $conn->executeStatement('DELETE FROM body_part_category');
            $conn->executeStatement('DELETE FROM risk_subcategories');
            $conn->executeStatement('DELETE FROM risk_categories');
            $conn->executeStatement('DELETE FROM risk_groups');
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    private function cell(Worksheet $sheet, int $row, int $col): string
    {
        $raw = $sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getFormattedValue();
        $v   = is_string($raw) ? trim($raw) : trim((string) $raw);
        return preg_replace('/\s+/u', ' ', $v) ?? '';
    }
}
