<?php

namespace App\Entity;

use App\Repository\CompanyRequisiteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompanyRequisiteRepository::class)]
class CompanyRequisite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $companyType = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Įmonės pavadinimas yra privalomas.')]
    private ?string $companyName = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Įmonės kodas yra privalomas.')]
    #[Assert\Length(
        min: 9,
        max: 9,
        exactMessage: 'Įmonės kodas turi būti būtent {{ limit }} skaitmenų.'
    )]
    #[Assert\Regex(
        pattern: '/^\d+$/',
        message: 'Įmonės kodas gali susidėti tik iš skaitmenų.'
    )]
    private ?string $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cityOrDistrict = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $managerType = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $managerGender = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $managerFirstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $managerLastName = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $documentDate = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $directory = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int { return $this->id; }

    public function getCompanyType(): ?string { return $this->companyType; }
    public function setCompanyType(?string $v): static { $this->companyType = $v; return $this; }

    public function getCompanyName(): ?string { return $this->companyName; }
    public function setCompanyName(string $v): static { $this->companyName = $v; return $this; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(string $v): static { $this->code = $v; return $this; }
    public function getCategory(): ?string { return $this->category; }
    public function setCategory(?string $v): static { $this->category = $v; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $v): static { $this->address = $v; return $this; }

    public function getCityOrDistrict(): ?string { return $this->cityOrDistrict; }
    public function setCityOrDistrict(?string $v): static { $this->cityOrDistrict = $v; return $this; }

    public function getManagerType(): ?string { return $this->managerType; }
    public function setManagerType(?string $v): static { $this->managerType = $v; return $this; }

    public function getManagerGender(): ?string { return $this->managerGender; }
    public function setManagerGender(?string $v): static { $this->managerGender = $v; return $this; }

    public function getManagerFirstName(): ?string { return $this->managerFirstName; }
    public function setManagerFirstName(?string $v): static { $this->managerFirstName = $v; return $this; }

    public function getManagerLastName(): ?string { return $this->managerLastName; }
    public function setManagerLastName(?string $v): static { $this->managerLastName = $v; return $this; }

    public function getDocumentDate(): ?string { return $this->documentDate; }
    public function setDocumentDate(?string $v): static { $this->documentDate = $v; return $this; }

    public function getRole(): ?string { return $this->role; }
    public function setRole(?string $v): static { $this->role = $v; return $this; }

    public function getDirectory(): ?string { return $this->directory; }
    public function setDirectory(?string $v): static { $this->directory = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }
}
