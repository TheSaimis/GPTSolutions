<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BodyPartCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BodyPartCategoryRepository::class)]
#[ORM\Table(name: 'body_part_category')]
class BodyPartCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** @var Collection<int, BodyPart> */
    #[ORM\OneToMany(targetEntity: BodyPart::class, mappedBy: 'category')]
    private Collection $bodyParts;

    public function __construct()
    {
        $this->bodyParts = new ArrayCollection();
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

    /** @return Collection<int, BodyPart> */
    public function getBodyParts(): Collection
    {
        return $this->bodyParts;
    }
}
