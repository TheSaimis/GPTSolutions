<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BodyPartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BodyPartRepository::class)]
#[ORM\Table(name: 'body_part')]
class BodyPart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: BodyPartCategory::class, inversedBy: 'bodyParts')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?BodyPartCategory $category = null;

    /** @var Collection<int, RiskList> */
    #[ORM\OneToMany(targetEntity: RiskList::class, mappedBy: 'bodyPart')]
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

    public function getCategory(): ?BodyPartCategory
    {
        return $this->category;
    }

    public function setCategory(?BodyPartCategory $category): static
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
