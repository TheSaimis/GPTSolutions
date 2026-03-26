<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RiskCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RiskCategoryRepository::class)]
#[ORM\Table(name: 'risk_categories')]
class RiskCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $lineNumber = 0;

    /** @var Collection<int, RiskSubcategory> */
    #[ORM\OneToMany(targetEntity: RiskSubcategory::class, mappedBy: 'category')]
    private Collection $subcategories;

    public function __construct()
    {
        $this->subcategories = new ArrayCollection();
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

    /** @return Collection<int, RiskSubcategory> */
    public function getSubcategories(): Collection
    {
        return $this->subcategories;
    }
}
