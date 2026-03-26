<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Darbuotojų rizikos modulio įmonė (atskira nuo CompanyRequisite).
 */
#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: 'company')]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** @var Collection<int, CompanyWorker> */
    #[ORM\OneToMany(targetEntity: CompanyWorker::class, mappedBy: 'company', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $companyWorkers;

    public function __construct()
    {
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

    /** @return Collection<int, CompanyWorker> */
    public function getCompanyWorkers(): Collection
    {
        return $this->companyWorkers;
    }
}
