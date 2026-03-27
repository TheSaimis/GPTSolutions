<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\CompanyRequisite;
use App\Entity\CompanyWorker;
use App\Entity\Equipment;
use App\Entity\Worker;
use App\Entity\WorkerItem;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\IOFactory;

/**
 * Generuoja AAP saraso dokumenta:
 * - Is companyId paima imones rekvizitus
 * - Is company_worker paima visus imones darbuotojus
 * - Is worker_item paima visus equipment pagal darbuotoja
 * - Kiekvienam darbuotojui sukuria atskira lentele naujame puslapyje
 */
final class CreateEquipmentDocument
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $projectDir,
    ) {}

    /**
     * @return array{
     *   company: array{id:int,companyName:?string,code:?string,address:?string,cityOrDistrict:?string},
     *   workers: array<int, array{
     *      workerId:int,
     *      workerName:string,
     *      equipment: array<int, array{id:int,name:string,expirationDate:string}>
     *   }>
     * }
     */
    public function buildDataByCompanyId(int $companyId): array
    {
        /** @var CompanyRequisite|null $company */
        $company = $this->em->getRepository(CompanyRequisite::class)->find($companyId);
        if (! $company instanceof CompanyRequisite) {
            throw new \InvalidArgumentException('Imone nerasta');
        }

        $companyWorkers = $this->em->getRepository(CompanyWorker::class)
            ->createQueryBuilder('cw')
            ->join('cw.worker', 'w')
            ->where('cw.companyRequisite = :company')
            ->setParameter('company', $company)
            ->addOrderBy('w.id', 'ASC')
            ->getQuery()
            ->getResult();

        $workersData = [];
        /** @var CompanyWorker $cw */
        foreach ($companyWorkers as $cw) {
            $worker = $cw->getWorker();
            if (! $worker instanceof Worker || $worker->getId() === null) {
                continue;
            }

            $workerItems = $this->em->getRepository(WorkerItem::class)
                ->createQueryBuilder('wi')
                ->join('wi.equipment', 'e')
                ->where('wi.worker = :worker')
                ->setParameter('worker', $worker)
                ->addOrderBy('e.name', 'ASC')
                ->getQuery()
                ->getResult();

            $equipment = [];
            $seen = [];
            /** @var WorkerItem $wi */
            foreach ($workerItems as $wi) {
                $eq = $wi->getEquipment();
                if (! $eq instanceof Equipment || $eq->getId() === null) {
                    continue;
                }
                if (isset($seen[$eq->getId()])) {
                    continue; // UNIQUE pagal worker + equipment
                }
                $seen[$eq->getId()] = true;

                $equipment[] = [
                    'id'             => (int) $eq->getId(),
                    'name'           => $eq->getName(),
                    'expirationDate' => $eq->getExpirationDate(),
                ];
            }

            $workersData[] = [
                'workerId'   => (int) $worker->getId(),
                'workerName' => $worker->getName(),
                'equipment'  => $equipment,
            ];
        }

        return [
            'company' => [
                'id'             => (int) $company->getId(),
                'companyName'    => $company->getCompanyName(),
                'code'           => $company->getCode(),
                'address'        => $company->getAddress(),
                'cityOrDistrict' => $company->getCityOrDistrict(),
            ],
            'workers' => $workersData,
        ];
    }

    /**
     * Sugeneruoja DOCX su lentelemis, po viena darbuotojui (kiekvienas naujame puslapyje).
     */
    public function createByCompanyId(int $companyId): string
    {
        $payload = $this->buildDataByCompanyId($companyId);
        $company = $payload['company'];
        $workers = $payload['workers'];

        $phpWord = new PhpWord();
        $phpWord->getSettings()->setThemeFontLang(new Language(Language::LT_LT));

        $section = $phpWord->addSection();
        $isFirst = true;

        foreach ($workers as $workerData) {
            if (! $isFirst) {
                $section->addPageBreak();
            }
            $isFirst = false;

            $section->addText('Asmeniniu apsaugos priemoniu sarasas', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
            $section->addText('Imone: ' . (string) ($company['companyName'] ?? ''), ['size' => 11]);
            $section->addText('Kodas: ' . (string) ($company['code'] ?? ''), ['size' => 11]);
            $section->addText('Darbuotojas: ' . (string) $workerData['workerName'], ['size' => 11, 'bold' => true]);
            $section->addTextBreak(1);

            $table = $section->addTable([
                'borderSize' => 6,
                'borderColor' => '000000',
                'cellMargin' => 80,
                'alignment' => JcTable::CENTER,
            ]);

            // Header
            $table->addRow();
            $table->addCell(3000)->addText('Darbuotojas', ['bold' => true], ['alignment' => 'center']);
            $table->addCell(5000)->addText('Priemone', ['bold' => true], ['alignment' => 'center']);
            $table->addCell(3000)->addText('Tinkamumo periodas', ['bold' => true], ['alignment' => 'center']);

            $equipmentRows = $workerData['equipment'];
            if ($equipmentRows === []) {
                $table->addRow();
                $table->addCell(3000)->addText((string) $workerData['workerName']);
                $table->addCell(5000)->addText('-');
                $table->addCell(3000)->addText('-');
            } else {
                foreach ($equipmentRows as $eq) {
                    $table->addRow();
                    $table->addCell(3000)->addText((string) $workerData['workerName']);
                    $table->addCell(5000)->addText((string) $eq['name']);
                    $table->addCell(3000)->addText((string) $eq['expirationDate']);
                }
            }
        }

        if ($workers === []) {
            $section->addText('Imonei nepriskirta darbuotoju.', ['size' => 11]);
        }

        $slug = preg_replace('/[^\w]+/', '_', (string) ($company['companyName'] ?? 'imone')) ?: 'imone';
        $outDir = $this->projectDir . '/generated/equipment/' . $slug;
        if (! is_dir($outDir)) {
            mkdir($outDir, 0775, true);
        }

        $filePath = $outDir . '/AAP_sarasas_' . $slug . '_' . date('Ymd_His') . '.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filePath);

        return $filePath;
    }
}

