<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RiskListRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Sąrašas: susieja darbuotoją, kūno dalį ir rizikos subkategoriją.
 */
#[ORM\Entity(repositoryClass: RiskListRepository::class)]
#[ORM\Table(name: 'risk_list')]
#[ORM\UniqueConstraint(name: 'risk_list_unique', columns: ['body_part_id', 'risk_subcategory_id', 'worker_id'])]
class RiskList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BodyPart::class, inversedBy: 'riskLists')]
    #[ORM\JoinColumn(name: 'body_part_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?BodyPart $bodyPart = null;

    #[ORM\ManyToOne(targetEntity: RiskSubcategory::class, inversedBy: 'riskLists')]
    #[ORM\JoinColumn(name: 'risk_subcategory_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?RiskSubcategory $riskSubcategory = null;

    #[ORM\ManyToOne(targetEntity: Worker::class, inversedBy: 'riskLists')]
    #[ORM\JoinColumn(name: 'worker_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Worker $worker = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBodyPart(): ?BodyPart
    {
        return $this->bodyPart;
    }

    public function setBodyPart(?BodyPart $bodyPart): static
    {
        $this->bodyPart = $bodyPart;
        return $this;
    }

    public function getRiskSubcategory(): ?RiskSubcategory
    {
        return $this->riskSubcategory;
    }

    public function setRiskSubcategory(?RiskSubcategory $riskSubcategory): static
    {
        $this->riskSubcategory = $riskSubcategory;
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
