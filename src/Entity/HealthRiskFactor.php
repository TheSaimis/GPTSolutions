<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\HealthRiskFactorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HealthRiskFactorRepository::class)]
#[ORM\Table(name: 'health_risk_factor')]
class HealthRiskFactor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 50)]
    private string $code = '';

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $lineNumber = 0;

    /** @var Collection<int, HealthRiskCommonFactor> */
    #[ORM\OneToMany(targetEntity: HealthRiskCommonFactor::class, mappedBy: 'factor')]
    private Collection $commonAssignments;

    /** @var Collection<int, HealthRiskProfileFactor> */
    #[ORM\OneToMany(targetEntity: HealthRiskProfileFactor::class, mappedBy: 'factor')]
    private Collection $profileAssignments;

    public function __construct()
    {
        $this->commonAssignments  = new ArrayCollection();
        $this->profileAssignments = new ArrayCollection();
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function setLineNumber(int $lineNumber): static
    {
        $this->lineNumber = $lineNumber;
        return $this;
    }

    /** @return Collection<int, HealthRiskCommonFactor> */
    public function getCommonAssignments(): Collection
    {
        return $this->commonAssignments;
    }

    /** @return Collection<int, HealthRiskProfileFactor> */
    public function getProfileAssignments(): Collection
    {
        return $this->profileAssignments;
    }
}
