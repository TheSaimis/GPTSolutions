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

    /** Matavimo vienetas dokumente (${vnt}): „vnt“, „poros“ ir pan. */
    #[ORM\Column(name: 'unit_of_measurement', length: 32)]
    private string $unitOfMeasurement = 'vnt';

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

    public function getUnitOfMeasurement(): string
    {
        return $this->unitOfMeasurement;
    }

    public function setUnitOfMeasurement(string $unitOfMeasurement): static
    {
        $this->unitOfMeasurement = self::normalizeUnitOfMeasurement($unitOfMeasurement);

        return $this;
    }

    /** Normalizuoja iš API: leidžiama „vnt“ arba „poros“. */
    public static function normalizeUnitOfMeasurement(string $raw): string
    {
        $u = strtolower(trim($raw));
        if ($u === 'poros' || $u === 'pora' || $u === 'porų') {
            return 'poros';
        }

        return 'vnt';
    }

    /** Tekstas Word šablono stulpeliui (${vnt}). */
    public static function documentUnitLabel(string $stored): string
    {
        return self::normalizeUnitOfMeasurement($stored) === 'poros' ? 'Poros' : 'Vnt';
    }

    /** @return Collection<int, WorkerItem> */
    public function getWorkerItems(): Collection
    {
        return $this->workerItems;
    }
}

