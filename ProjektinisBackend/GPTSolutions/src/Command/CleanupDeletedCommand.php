<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CompanyRequisite;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-deleted',
    description: 'Pašalina senesnius nei 7 dienų soft-deleted įrašus iš DB ir /deleted/ katalogo.',
)]
final class CleanupDeletedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $threshold = new \DateTimeImmutable('-7 days');

        $deletedUsers = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.deleted = :del')
            ->andWhere('u.deletedDate <= :threshold')
            ->setParameter('del', true)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();

        foreach ($deletedUsers as $user) {
            $io->text("Šalinamas naudotojas ID: {$user->getId()}");
            $this->em->remove($user);
        }

        $deletedCompanies = $this->em->getRepository(CompanyRequisite::class)
            ->createQueryBuilder('c')
            ->where('c.deleted = :del')
            ->andWhere('c.deletedDate <= :threshold')
            ->setParameter('del', true)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();

        foreach ($deletedCompanies as $company) {
            $io->text("Šalinama įmonė ID: {$company->getId()} ({$company->getCompanyName()})");
            $this->em->remove($company);
        }

        $this->em->flush();

        $deletedDir = $this->projectDir . '/deleted';
        $filesRemoved = 0;
        if (is_dir($deletedDir)) {
            $filesRemoved = $this->cleanOldFiles($deletedDir, $threshold);
        }

        $io->success(sprintf(
            'Išvalyta: %d naudotojų, %d įmonių, %d failų/katalogų.',
            count($deletedUsers),
            count($deletedCompanies),
            $filesRemoved,
        ));

        return Command::SUCCESS;
    }

    private function cleanOldFiles(string $dir, \DateTimeImmutable $threshold): int
    {
        $count = 0;
        $thresholdTs = $threshold->getTimestamp();

        $items = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $count += $this->cleanOldFiles($path, $threshold);
                if (count(array_diff(scandir($path) ?: [], ['.', '..'])) === 0) {
                    rmdir($path);
                    $count++;
                }
            } elseif (is_file($path) && filemtime($path) < $thresholdTs) {
                unlink($path);
                $count++;
            }
        }

        return $count;
    }
}
