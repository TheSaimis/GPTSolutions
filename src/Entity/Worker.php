<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WorkerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkerRepository::class)]
#[ORM\Table(name: 'worker')]
class Worker
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** @var Collection<int, RiskList> */
    #[ORM\OneToMany(targetEntity: RiskList::class, mappedBy: 'worker')]
    private Collection $riskLists;

    /** @var Collection<int, CompanyWorker> */
    #[ORM\OneToMany(targetEntity: CompanyWorker::class, mappedBy: 'worker')]
    private Collection $companyWorkers;

    public function __construct()
    {
        $this->riskLists      = new ArrayCollection();
        $this->companyWorkers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /** @return Collection<int, RiskList> */
    public function getRiskLists(): Collection
    {
        return $this->riskLists;
    }

    /** @return Collection<int, CompanyWorker> */
    public function getCompanyWorkers(): Collection
    {
        return $this->companyWorkers;
    }
}