<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\HealthRiskProfileFactorRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HealthRiskProfileFactorRepository::class)]
#[ORM\Table(name: 'health_risk_profile_factor')]
#[ORM\UniqueConstraint(name: 'uniq_health_profile_factor', columns: ['profile_id', 'factor_id'])]
class HealthRiskProfileFactor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HealthRiskProfile::class, inversedBy: 'factors')]
    #[ORM\JoinColumn(name: 'profile_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?HealthRiskProfile $profile = null;

    #[ORM\ManyToOne(targetEntity: HealthRiskFactor::class, inversedBy: 'profileAssignments')]
    #[ORM\JoinColumn(name: 'factor_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?HealthRiskFactor $factor = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $lineNumber = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): ?HealthRiskProfile
    {
        return $this->profile;
    }

    public function setProfile(?HealthRiskProfile $profile): static
    {
        $this->profile = $profile;
        return $this;
    }

    public function getFactor(): ?HealthRiskFactor
    {
        return $this->factor;
    }

    public function setFactor(?HealthRiskFactor $factor): static
    {
        $this->factor = $factor;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;
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
}
