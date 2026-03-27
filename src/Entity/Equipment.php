<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EquipmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EquipmentRepository::class)]
#[ORM\Table(name: 'equipment')]
class Equipment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    // Pagal reikalavima: privalomai string.
    #[ORM\Column(length: 120)]
    private string $expirationDate = '';

    /** @var Collection<int, WorkerItem> */
    #[ORM\OneToMany(targetEntity: WorkerItem::class, mappedBy: 'equipment')]
    private Collection $workerItems;

    public function __construct()
    {
        $this->workerItems = new ArrayCollection();
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

    public function getExpirationDate(): string
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(string $expirationDate): static
    {
        $this->expirationDate = $expirationDate;
        return $this;
    }

    /** @return Collection<int, WorkerItem> */
    public function getWorkerItems(): Collection
    {
        return $this->workerItems;
    }
}

