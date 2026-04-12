<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AapEquipmentGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * AAP priemonių grupės aprašas (bendras): įmonių priskyrimai — per AapEquipmentCompanyGroup.
 * Word sąraše = viena lentelės eilutė vienai įmonei priskirtai grupei.
 */
#[ORM\Entity(repositoryClass: AapEquipmentGroupRepository::class)]
#[ORM\Table(name: 'aap_equipment_group')]
class AapEquipmentGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** @var Collection<int, AapEquipmentCompanyGroup> */
    #[ORM\OneToMany(targetEntity: AapEquipmentCompanyGroup::class, mappedBy: 'equipmentGroup', cascade: ['persist', 'remove'], orphanRemoval: false)]
    private Collection $companyLinks;

    /** @var Collection<int, AapEquipmentGroupWorker> */
    #[ORM\OneToMany(targetEntity: AapEquipmentGroupWorker::class, mappedBy: 'equipmentGroup', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $groupWorkers;

    /** @var Collection<int, AapEquipmentGroupEquipment> */
    #[ORM\OneToMany(targetEntity: AapEquipmentGroupEquipment::class, mappedBy: 'equipmentGroup', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $groupEquipment;

    public function __construct()
    {
        $this->companyLinks = new ArrayCollection();
        $this->groupWorkers = new ArrayCollection();
        $this->groupEquipment = new ArrayCollection();
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

    /**
     * @return Collection<int, AapEquipmentCompanyGroup>
     */
    public function getCompanyLinks(): Collection
    {
        return $this->companyLinks;
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
