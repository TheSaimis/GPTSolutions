<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RiskSubcategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RiskSubcategoryRepository::class)]
#[ORM\Table(name: 'risk_subcategories')]
class RiskSubcategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $lineNumber = 0;

    #[ORM\ManyToOne(targetEntity: RiskCategory::class, inversedBy: 'subcategories')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?RiskCategory $category = null;

    /** @var Collection<int, RiskList> */
    #[ORM\OneToMany(targetEntity: RiskList::class, mappedBy: 'riskSubcategory')]
    private Collection $riskLists;

    public function __construct()
    {
        $this->riskLists = new ArrayCollection();
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

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function setLineNumber(int $lineNumber): static
    {
        $this->lineNumber = $lineNumber;
        return $this;
    }

    public function getCategory(): ?RiskCategory
    {
        return $this->category;
    }

    public function setCategory(?RiskCategory $category): static
    {
        $this->category = $category;
        return $this;
    }

    /** @return Collection<int, RiskList> */
    public function getRiskLists(): Collection
    {
        return $this->riskLists;
    }
}
