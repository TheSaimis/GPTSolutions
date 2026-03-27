<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WorkerRiskRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkerRiskRepository::class)]
#[ORM\Table(name: 'worker_risk')]
#[ORM\UniqueConstraint(name: 'uniq_worker_risk', columns: ['worker_id', 'risk_factor_id'])]
class WorkerRisk
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Worker::class, inversedBy: 'workerRisks')]
    #[ORM\JoinColumn(name: 'worker_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Worker $worker = null;

    #[ORM\ManyToOne(targetEntity: HealthRiskFactor::class, inversedBy: 'workerRisks')]
    #[ORM\JoinColumn(name: 'risk_factor_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?HealthRiskFactor $riskFactor = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getRiskFactor(): ?HealthRiskFactor
    {
        return $this->riskFactor;
    }

    public function setRiskFactor(?HealthRiskFactor $riskFactor): static
    {
        $this->riskFactor = $riskFactor;
        return $this;
    }
}

