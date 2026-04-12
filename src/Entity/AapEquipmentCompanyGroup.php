<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AapEquipmentCompanyGroupRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Įmonės ir AAP priemonių grupės ryšys (daug su daug): ta pati grupė gali būti priskirta kelioms įmonėms.
 */
#[ORM\Entity(repositoryClass: AapEquipmentCompanyGroupRepository::class)]
#[ORM\Table(name: 'aap_equipment_company_group')]
#[ORM\UniqueConstraint(name: 'uniq_aap_company_group', columns: ['company_id', 'group_id'])]
class AapEquipmentCompanyGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CompanyRequisite::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?CompanyRequisite $companyRequisite = null;

    #[ORM\ManyToOne(targetEntity: AapEquipmentGroup::class, inversedBy: 'companyLinks')]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?AapEquipmentGroup $equipmentGroup = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

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

    public function getEquipmentGroup(): ?AapEquipmentGroup
    {
        return $this->equipmentGroup;
    }

    public function setEquipmentGroup(?AapEquipmentGroup $equipmentGroup): static
    {
        $this->equipmentGroup = $equipmentGroup;

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
}
