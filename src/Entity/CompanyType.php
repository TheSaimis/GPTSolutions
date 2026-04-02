<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CompanyTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyTypeRepository::class)]
#[ORM\Table(name: 'company_types')]
#[ORM\UniqueConstraint(name: 'UNIQ_company_types_type_short', columns: ['type_short'])]
class CompanyType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'type_short', length: 50)]
    private string $typeShort = '';

    #[ORM\Column(name: 'type_short_en', length: 50, nullable: true)]
    private ?string $typeShortEn = null;

    #[ORM\Column(name: 'type_short_ru', length: 50, nullable: true)]
    private ?string $typeShortRu = null;

    #[ORM\Column(length: 255, name: 'type')]
    private string $type = '';

    #[ORM\Column(length: 255, name: 'type_en', nullable: true)]
    private ?string $typeEn = null;

    #[ORM\Column(length: 255, name: 'type_ru', nullable: true)]
    private ?string $typeRu = null;

    /** @var Collection<int, CompanyRequisite> */
    #[ORM\OneToMany(targetEntity: CompanyRequisite::class, mappedBy: 'companyTypeRef')]
    private Collection $companyRequisites;

    public function __construct()
    {
        $this->companyRequisites = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeShort(): string
    {
        return $this->typeShort;
    }

    public function setTypeShort(string $typeShort): static
    {
        $this->typeShort = $typeShort;
        return $this;
    }

    public function getTypeShortEn(): ?string
    {
        return $this->typeShortEn;
    }

    public function setTypeShortEn(?string $typeShortEn): static
    {
        $this->typeShortEn = $typeShortEn;
        return $this;
    }

    public function getTypeShortRu(): ?string
    {
        return $this->typeShortRu;
    }

    public function setTypeShortRu(?string $typeShortRu): static
    {
        $this->typeShortRu = $typeShortRu;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTypeEn(): ?string
    {
        return $this->typeEn;
    }

    public function setTypeEn(?string $typeEn): static
    {
        $this->typeEn = $typeEn;
        return $this;
    }

    public function getTypeRu(): ?string
    {
        return $this->typeRu;
    }

    public function setTypeRu(?string $typeRu): static
    {
        $this->typeRu = $typeRu;
        return $this;
    }

    /** @return Collection<int, CompanyRequisite> */
    public function getCompanyRequisites(): Collection
    {
        return $this->companyRequisites;
    }
}
