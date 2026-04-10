<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AapEquipmentGroupEquipmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AapEquipmentGroupEquipmentRepository::class)]
#[ORM\Table(name: 'aap_equipment_group_equipment')]
#[ORM\UniqueConstraint(name: 'uniq_aap_group_equipment', columns: ['group_id', 'equipment_id'])]
class AapEquipmentGroupEquipment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AapEquipmentGroup::class, inversedBy: 'groupEquipment')]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?AapEquipmentGroup $equipmentGroup = null;

    #[ORM\ManyToOne(targetEntity: Equipment::class)]
    #[ORM\JoinColumn(name: 'equipment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Equipment $equipment = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEquipmentGroup(): ?AapEquipmentGroup
    {
        return $this->equipmentGroup;
    }

    public function setEquipmentGroup(?AapEquipmentGroup $equipmentGroup): static
    {
        $this->equipmentGroup = $equipmentGroup;

        return $this;
    }

    public function getEquipment(): ?Equipment
    {
        return $this->equipment;
    }

    public function setEquipment(?Equipment $equipment): static
    {
        $this->equipment = $equipment;

        return $this;
    }
}
