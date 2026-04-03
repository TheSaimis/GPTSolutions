<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use App\Entity\CompanyRequisite;
use App\Repository\CompanyRequisiteRepository;
use App\Services\ManagerGenderResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:seed-sample-companies',
    description: 'Įterpia 30 pavyzdinių įmonių su užpildytais reikalais (vardai, kodai, adresai ir kt.).',
)]
final class SeedSampleCompaniesCommand extends Command
{
    /** @return list<list{string,string,string,string,string,string,string,string,string,string}>
     */
    private static function companyRows(): array
    {
        return [
            ['UAB Šviesos projektai', '305000101', 'UAB', 'Saulės g. 12', 'Vilnius', 'Direktorius', 'Tomas', 'Venclova', '2023-04-18', 'Vadovaujantis asmuo'],
            ['UAB Baltijos logistika', '305000102', 'UAB', 'Jūros g. 45', 'Klaipėda', 'Direktorė', 'Rūta', 'Baltijė', '2022-11-02', 'Vadovaujantis asmuo'],
            ['AB Vilniaus pramonė', '305000103', 'AB', 'Pramonės pr. 88', 'Vilnius', 'Direktorius', 'Algirdas', 'Pramonkus', '2021-09-30', 'Vadovaujantis asmuo'],
            ['UAB Žalioji energetika', '305000104', 'UAB', 'Vėjo al. 3', 'Kaunas', 'Direktorė', 'Gintarė', 'Žaliauskienė', '2024-01-15', 'Vadovaujantis asmuo'],
            ['MB Kūrybos studija', '305000105', 'MB', 'Menų g. 7', 'Kaunas', 'Vadovas', 'Darius', 'Kūrėjas', '2020-07-22', 'Vadovaujantis asmuo'],
            ['UAB Skaitmeninė transformacija', '305000106', 'UAB', 'Technologijų g. 21', 'Vilnius', 'Direktorius', 'Mantas', 'Skaitmenis', '2023-08-01', 'Vadovaujantis asmuo'],
            ['UAB Maisto tinklas', '305000107', 'UAB', 'Taikos pr. 156', 'Panevėžys', 'Direktorė', 'Laura', 'Maisto', '2022-03-10', 'Vadovaujantis asmuo'],
            ['UAB Statybos partneriai', '305000108', 'UAB', 'Statybininkų g. 33', 'Šiauliai', 'Direktorius', 'Petras', 'Statulevičius', '2021-12-05', 'Vadovaujantis asmuo'],
            ['AB Transporto sistemos', '305000109', 'AB', 'Logistikos g. 9', 'Vilnius', 'Direktorius', 'Vytautas', 'Transports', '2024-06-12', 'Vadovaujantis asmuo'],
            ['UAB Sveikatos centras', '305000110', 'UAB', 'Sveikatos g. 4', 'Kaunas', 'Direktorė', 'Rasa', 'Sveikatytė', '2023-02-28', 'Vadovaujantis asmuo'],
            ['UAB IT konsultacijos', '305000111', 'UAB', 'Kibernetikos g. 15', 'Vilnius', 'Direktorius', 'Linas', 'Kodraitis', '2022-10-18', 'Vadovaujantis asmuo'],
            ['UAB Miško ištekliai', '305000112', 'UAB', 'Miško kel. 2', 'Utena', 'Vadovas', 'Andrius', 'Miškinis', '2020-05-14', 'Vadovaujantis asmuo'],
            ['UAB Odontologijos klinika', '305000113', 'UAB', 'Smiltinės g. 8', 'Vilnius', 'Direktorė', 'Monika', 'Dantytė', '2024-04-07', 'Vadovaujantis asmuo'],
            ['AB Finansų valdymas', '305000114', 'AB', 'Konstitucijos pr. 11', 'Vilnius', 'Direktorius', 'Donatas', 'Finansė', '2021-01-20', 'Vadovaujantis asmuo'],
            ['UAB Reklamos agentūra', '305000115', 'UAB', 'Kūrybos skg. 5', 'Kaunas', 'Direktorė', 'Ieva', 'Reklamaitė', '2023-09-09', 'Vadovaujantis asmuo'],
            ['UAB Auto servisas', '305000116', 'UAB', 'Mechanikų g. 44', 'Marijampolė', 'Vadovas', 'Saulius', 'Autoservisas', '2022-07-01', 'Vadovaujantis asmuo'],
            ['UAB Odos priežiūra', '305000117', 'UAB', 'Grožio g. 19', 'Vilnius', 'Direktorė', 'Austėja', 'Odaitytė', '2024-02-14', 'Vadovaujantis asmuo'],
            ['MB Architektūros biuras', '305000118', 'MB', 'Architektų g. 1', 'Vilnius', 'Direktorius', 'Mindaugas', 'Architektas', '2019-11-11', 'Vadovaujantis asmuo'],
            ['UAB Logistikos terminalas', '305000119', 'UAB', 'Terminalo g. 200', 'Klaipėda', 'Direktorius', 'Rimantas', 'Laivas', '2023-05-25', 'Vadovaujantis asmuo'],
            ['UAB Pieno produktai', '305000120', 'UAB', 'Pieno g. 17', 'Rokiškis', 'Direktorė', 'Jurgita', 'Pienė', '2022-12-12', 'Vadovaujantis asmuo'],
            ['UAB Elektros montavimas', '305000121', 'UAB', 'Elektrikų g. 6', 'Alytus', 'Vadovas', 'Kęstutis', 'Srovė', '2021-08-08', 'Vadovaujantis asmuo'],
            ['AB Laivyba', '305000122', 'AB', 'Uosto g. 1', 'Klaipėda', 'Direktorius', 'Giedrius', 'Laivynas', '2024-03-03', 'Vadovaujantis asmuo'],
            ['UAB Turizmo paslaugos', '305000123', 'UAB', 'Turgaus g. 9', 'Druskininkai', 'Direktorė', 'Kristina', 'Kelionė', '2020-06-19', 'Vadovaujantis asmuo'],
            ['UAB Siuvimo fabrikas', '305000124', 'UAB', 'Siuvėjų g. 28', 'Jonava', 'Direktorius', 'Edmundas', 'Siūlas', '2023-11-11', 'Vadovaujantis asmuo'],
            ['UAB Chemijos tiekimas', '305000125', 'UAB', 'Chemijos g. 13', 'Kėdainiai', 'Vadovas', 'Valdas', 'Chemininkas', '2022-04-04', 'Vadovaujantis asmuo'],
            ['UAB Baldų salonas', '305000126', 'UAB', 'Baldų g. 55', 'Vilnius', 'Direktorė', 'Jolanta', 'Baldo', '2024-07-21', 'Vadovaujantis asmuo'],
            ['UAB Saugos tarnyba', '305000127', 'UAB', 'Saugos g. 3', 'Kaunas', 'Direktorius', 'Ramūnas', 'Saugumas', '2021-10-10', 'Vadovaujantis asmuo'],
            ['UAB Žemės ūkio technika', '305000128', 'UAB', 'Lauko g. 90', 'Radviliškis', 'Vadovas', 'Dainius', 'Traktorius', '2023-01-30', 'Vadovaujantis asmuo'],
            ['UAB Vaikų lopšelis‑darželis „Žiedas"', '305000129', 'UAB', 'Vaikų g. 2', 'Trakai', 'Direktorė', 'Elena', 'Vaikaitė', '2022-08-15', 'Vadovaujantis asmuo'],
            ['UAB Verslo centras', '305000130', 'UAB', 'Verslo g. 100', 'Vilnius', 'Direktorius', 'Osvaldas', 'Centras', '2024-09-01', 'Vadovaujantis asmuo'],
        ];
    }

    public function __construct(
        private EntityManagerInterface $em,
        private CompanyRequisiteRepository $companyRepo,
        private ManagerGenderResolver $genderResolver,
        private ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $categories = $this->em->getRepository(Category::class)->findAll();
        $inserted = 0;
        $skipped  = 0;

        foreach (self::companyRows() as $i => $row) {
            [
                $companyName,
                $code,
                $companyType,
                $address,
                $cityOrDistrict,
                $managerType,
                $managerFirstName,
                $managerLastName,
                $documentDate,
                $role,
            ] = $row;

            if ($this->companyRepo->existsByName($companyName)) {
                ++$skipped;
                $output->writeln(sprintf('<comment>Praleista (jau yra): %s</comment>', $companyName));
                continue;
            }

            $cat = $categories !== [] ? $categories[$i % \count($categories)] : null;

            $company = new CompanyRequisite();
            $company->setCompanyName($companyName);
            $company->setCode($code);
            $company->setCompanyType($companyType);
            $company->setAddress($address);
            $company->setCityOrDistrict($cityOrDistrict);
            $company->setManagerType($managerType);
            $company->setManagerGender($this->genderResolver->resolve($managerType));
            $company->setManagerFirstName($managerFirstName);
            $company->setManagerLastName($managerLastName);
            $company->setDocumentDate($documentDate);
            $company->setRole($role);
            $company->setCompanyCategory($cat instanceof Category ? $cat : null);
            if ($cat instanceof Category) {
                $company->setCategory($cat->getName());
            }

            $categoryName = $cat instanceof Category ? $cat->getName() : '';
            $company->setDirectory($this->buildCompanyDirectory(
                $categoryName,
                $companyType,
                $companyName,
                $code
            ));

            $errors = $this->validator->validate($company);
            if (\count($errors) > 0) {
                $output->writeln(sprintf('<error>Validacijos klaida: %s</error>', $companyName));
                foreach ($errors as $v) {
                    $output->writeln('  - ' . $v->getPropertyPath() . ': ' . $v->getMessage());
                }

                return Command::FAILURE;
            }

            $this->em->persist($company);
            ++$inserted;
        }

        $this->em->flush();

        $output->writeln(sprintf('<info>Įterpta įmonių: %d, praleista (dublikatas): %d</info>', $inserted, $skipped));

        return Command::SUCCESS;
    }

    private function sanitizeForFilename(string $name): string
    {
        $s = trim($name);
        $s = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $s) ?? $s;
        $s = preg_replace('/\s+/', '_', trim($s)) ?? $s;

        return $s !== '' ? $s : '';
    }

    private function buildCompanyDirectory(string $categoryName, string $tipas, string $companyName, string $code): string
    {
        $categorySlug = $this->sanitizeForFilename($categoryName) ?: 'be_kategorijos';
        $tipasSlug    = $this->sanitizeForFilename($tipas) ?: 'Kita';
        $companySlug  = $this->sanitizeForFilename($companyName) ?: $code;

        return $categorySlug . '/' . $tipasSlug . '/' . $companySlug;
    }
}
