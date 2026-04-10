<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AapEquipmentGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * AAP priemonių priskyrimo grupė vienai įmonei — Word sąraše = viena lentelės eilutė.
 */
#[ORM\Entity(repositoryClass: AapEquipmentGroupRepository::class)]
#[ORM\Table(name: 'aap_equipment_group')]
class AapEquipmentGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CompanyRequisite::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?CompanyRequisite $companyRequisite = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    /** @var Collection<int, AapEquipmentGroupWorker> */
    #[ORM\OneToMany(targetEntity: AapEquipmentGroupWorker::class, mappedBy: 'equipmentGroup', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $groupWorkers;

    /** @var Collection<int, AapEquipmentGroupEquipment> */
    #[ORM\OneToMany(targetEntity: AapEquipmentGroupEquipment::class, mappedBy: 'equipmentGroup', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $groupEquipment;

    public function __construct()
    {
        $this->groupWorkers = new ArrayCollection();
        $this->groupEquipment = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    /**
     * @return Collection<int, AapEquipmentGroupWorker>
     */
    public function getGroupWorkers(): Collection
    {
        return $this->groupWorkers;
    }

    /**
     * @return Collection<int, AapEquipmentGroupEquipment>
     */
    public function getGroupEquipment(): Collection
    {
        return $this->groupEquipment;
    }
}
