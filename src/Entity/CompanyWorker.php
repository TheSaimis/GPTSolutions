<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CompanyWorkerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyWorkerRepository::class)]
#[ORM\Table(name: 'company_worker')]
#[ORM\UniqueConstraint(name: 'company_worker_unique', columns: ['company_id', 'worker_id'])]
class CompanyWorker
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CompanyRequisite::class, inversedBy: 'companyWorkers')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?CompanyRequisite $companyRequisite = null;

    #[ORM\ManyToOne(targetEntity: Worker::class, inversedBy: 'companyWorkers')]
    #[ORM\JoinColumn(name: 'worker_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Worker $worker = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): ?CompanyRequisite
    {
        return $this->companyRequisite;
    }

    public function setCompany(?CompanyRequisite $company): static
    {
        $this->companyRequisite = $company;
        return $this;
    }

    public function getCompanyRequisite(): ?CompanyRequisite
    {
        return $this->companyRequisite;
    }

    public function setCompanyRequisite(?CompanyRequisite $companyRequisite): static
    {
        $this->companyRequisite = $companyRequisite;
        return $this;
    }

    public function getWorker(): ?Worker
    {
        return $this->worker;
    }

    public function setWorker(?Worker $worker): static
    {
        $this->worker = $worker;
        return $this;
    }
}
