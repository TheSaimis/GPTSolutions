<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AapEquipmentGroupWorkerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AapEquipmentGroupWorkerRepository::class)]
#[ORM\Table(name: 'aap_equipment_group_worker')]
#[ORM\UniqueConstraint(name: 'uniq_aap_group_worker', columns: ['group_id', 'worker_id'])]
class AapEquipmentGroupWorker
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AapEquipmentGroup::class, inversedBy: 'groupWorkers')]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?AapEquipmentGroup $equipmentGroup = null;

    #[ORM\ManyToOne(targetEntity: Worker::class)]
    #[ORM\JoinColumn(name: 'worker_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Worker $worker = null;

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
