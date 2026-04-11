<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CompanyWorkerEquipmentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Apsaugos priemonės, priskirtos konkrečiai įmonei ir jos darbuotojų tipui (pareigybėms).
 */
#[ORM\Entity(repositoryClass: CompanyWorkerEquipmentRepository::class)]
#[ORM\Table(name: 'company_worker_equipment')]
#[ORM\UniqueConstraint(name: 'uniq_company_worker_equipment', columns: ['company_id', 'worker_id', 'equipment_id'])]
class CompanyWorkerEquipment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CompanyRequisite::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?CompanyRequisite $companyRequisite = null;

    #[ORM\ManyToOne(targetEntity: Worker::class)]
    #[ORM\JoinColumn(name: 'worker_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Worker $worker = null;

    #[ORM\ManyToOne(targetEntity: Equipment::class)]
    #[ORM\JoinColumn(name: 'equipment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Equipment $equipment = null;

    #[ORM\Column(options: ['default' => 1])]
    private int $quantity = 1;

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

    public function getWorker(): ?Worker
    {
        return $this->worker;
    }

    public function setWorker(?Worker $worker): static
    {
        $this->worker = $worker;

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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = Equipment::normalizeDocumentQuantity($quantity);

        return $this;
    }
}
