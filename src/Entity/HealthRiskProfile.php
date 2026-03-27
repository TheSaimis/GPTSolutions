<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\HealthRiskProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HealthRiskProfileRepository::class)]
#[ORM\Table(name: 'health_risk_profile')]
class HealthRiskProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\Column(length: 120, options: ['default' => 'Tikrintis kas 2 metus'])]
    private string $checkupTerm = 'Tikrintis kas 2 metus';

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $lineNumber = 0;

    /** @var Collection<int, HealthRiskProfileFactor> */
    #[ORM\OneToMany(targetEntity: HealthRiskProfileFactor::class, mappedBy: 'profile', cascade: ['persist', 'remove'])]
    private Collection $factors;

    public function __construct()
    {
        $this->factors = new ArrayCollection();
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

    public function getCheckupTerm(): string
    {
        return $this->checkupTerm;
    }

    public function setCheckupTerm(string $checkupTerm): static
    {
        $this->checkupTerm = $checkupTerm;
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

    /** @return Collection<int, HealthRiskProfileFactor> */
    public function getFactors(): Collection
    {
        return $this->factors;
    }
}
