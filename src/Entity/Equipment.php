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

    #[ORM\Column(name: 'name_en', length: 255, nullable: true)]
    private ?string $nameEn = null;

    #[ORM\Column(name: 'name_ru', length: 255, nullable: true)]
    private ?string $nameRu = null;

    #[ORM\Column(name: 'expiration_date_en', length: 120, nullable: true)]
    private ?string $expirationDateEn = null;

    #[ORM\Column(name: 'expiration_date_ru', length: 120, nullable: true)]
    private ?string $expirationDateRu = null;

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

    public function getNameEn(): ?string
    {
        return $this->nameEn;
    }

    public function setNameEn(?string $nameEn): static
    {
        $this->nameEn = $nameEn;

        return $this;
    }

    public function getNameRu(): ?string
    {
        return $this->nameRu;
    }

    public function setNameRu(?string $nameRu): static
    {
        $this->nameRu = $nameRu;

        return $this;
    }

    public function getExpirationDateEn(): ?string
    {
        return $this->expirationDateEn;
    }

    public function setExpirationDateEn(?string $expirationDateEn): static
    {
        $this->expirationDateEn = $expirationDateEn;

        return $this;
    }

    public function getExpirationDateRu(): ?string
    {
        return $this->expirationDateRu;
    }

    public function setExpirationDateRu(?string $expirationDateRu): static
    {
        $this->expirationDateRu = $expirationDateRu;

        return $this;
    }

    /**
     * Dokumento kalba: lt | en | ru (žemiajame registre).
     */
    public function resolveNameForDocument(string $locale): string
    {
        $l = mb_strtolower(trim($locale));
        if ($l === 'en') {
            $t = trim((string) ($this->nameEn ?? ''));

            return $t !== '' ? $t : $this->name;
        }
        if ($l === 'ru') {
            $t = trim((string) ($this->nameRu ?? ''));

            return $t !== '' ? $t : $this->name;
        }

        return $this->name;
    }

    public function resolveExpirationDateForDocument(string $locale): string
    {
        $l = mb_strtolower(trim($locale));
        if ($l === 'en') {
            $t = trim((string) ($this->expirationDateEn ?? ''));

            return $t !== '' ? $t : $this->expirationDate;
        }
        if ($l === 'ru') {
            $t = trim((string) ($this->expirationDateRu ?? ''));

            return $t !== '' ? $t : $this->expirationDate;
        }

        return $this->expirationDate;
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
    public static function documentUnitLabel(string $stored, string $documentLanguage = 'LT'): string
    {
        $isPoros = self::normalizeUnitOfMeasurement($stored) === 'poros';
        $lang = mb_strtoupper(trim($documentLanguage));

        return match ($lang) {
            'EN' => $isPoros ? 'Pairs' : 'Pcs.',
            'RU' => $isPoros ? 'Пары' : 'шт.',
            default => $isPoros ? 'Poros' : 'Vnt',
        };
    }

    /** Dokumento ${kiekis}: sveikasis skaičius ≥ 1. */
    public static function normalizeDocumentQuantity(mixed $raw): int
    {
        if (is_bool($raw)) {
            return 1;
        }
        $n = (int) (is_string($raw) ? trim($raw) : $raw);
        if ($n < 1) {
            return 1;
        }
        if ($n > 99999) {
            return 99999;
        }

        return $n;
    }

    /** @return Collection<int, WorkerItem> */
    public function getWorkerItems(): Collection
    {
        return $this->workerItems;
    }
}

