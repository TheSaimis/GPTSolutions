<?php

namespace App\Entity;

use App\Repository\CompanyRequisiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompanyRequisiteRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CompanyRequisite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CompanyType::class, inversedBy: 'companyRequisites')]
    #[ORM\JoinColumn(name: 'company_type_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?CompanyType $companyTypeRef = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Įmonės pavadinimas yra privalomas.')]
    private ?string $companyName = null;

    #[ORM\Column(name: 'company_name_en', length: 255, nullable: true)]
    private ?string $companyNameEn = null;

    #[ORM\Column(name: 'company_name_ru', length: 255, nullable: true)]
    private ?string $companyNameRu = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $modifiedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $category = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'companies')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $companyCategory = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(name: 'address_en', length: 255, nullable: true)]
    private ?string $addressEn = null;

    #[ORM\Column(name: 'address_ru', length: 255, nullable: true)]
    private ?string $addressRu = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cityOrDistrict = null;

    #[ORM\Column(name: 'city_or_district_en', length: 255, nullable: true)]
    private ?string $cityOrDistrictEn = null;

    #[ORM\Column(name: 'city_or_district_ru', length: 255, nullable: true)]
    private ?string $cityOrDistrictRu = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $managerType = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $managerGender = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $managerFirstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $managerLastName = null;

    #[ORM\Column(name: 'manager_last_name_en', length: 255, nullable: true)]
    private ?string $managerLastNameEn = null;

    #[ORM\Column(name: 'manager_last_name_ru', length: 255, nullable: true)]
    private ?string $managerLastNameRu = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $documentDate = null;

    /** Word žyma ${pagrindas} — „Pagrindas išduoti“ tekstas AAP kortelių lentelėje. */
    #[ORM\Column(name: 'aap_korteles_pagrindas', type: Types::TEXT, nullable: true)]
    private ?string $aapKortelesPagrindas = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(name: 'manager_first_name_en', length: 255, nullable: true)]
    private ?string $managerFirstNameEn = null;

    #[ORM\Column(name: 'manager_first_name_ru', length: 255, nullable: true)]
    private ?string $managerFirstNameRu = null;

    #[ORM\Column(name: 'role_en', length: 255, nullable: true)]
    private ?string $roleEn = null;

    #[ORM\Column(name: 'role_ru', length: 255, nullable: true)]
    private ?string $roleRu = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $directory = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $deleted = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedDate = null;

    /** @var Collection<int, CompanyWorker> */
    #[ORM\OneToMany(
        targetEntity: CompanyWorker::class,
        mappedBy: 'companyRequisite',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $companyWorkers;

    public function __construct()
    {
        $this->companyWorkers = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->modifiedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->modifiedAt = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Vilnius'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyType(): ?string
    {
        return $this->companyTypeRef?->getTypeShort();
    }

    public function getCompanyTypeRef(): ?CompanyType
    {
        return $this->companyTypeRef;
    }

    public function setCompanyTypeRef(?CompanyType $companyTypeRef): static
    {
        $this->companyTypeRef = $companyTypeRef;
        return $this;
    }

    /**
     * Pilnas teisinės formos tekstas dokumentams (${tipasPilnas}): company_types.type, kitaip senasis category laukas.
     */
    public function resolveTipasPilnasForDocuments(): string
    {
        $fromType = $this->companyTypeRef?->getType();
        if (is_string($fromType) && trim($fromType) !== '') {
            return trim($fromType);
        }

        $legacy = $this->category;

        return (is_string($legacy) && trim($legacy) !== '') ? trim($legacy) : '';
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $v): static
    {
        $this->companyName = $v;
        return $this;
    }

    public function getCompanyNameEn(): ?string
    {
        return $this->companyNameEn;
    }

    public function setCompanyNameEn(?string $v): static
    {
        $this->companyNameEn = $v;
        return $this;
    }

    public function getCompanyNameRu(): ?string
    {
        return $this->companyNameRu;
    }

    public function setCompanyNameRu(?string $v): static
    {
        $this->companyNameRu = $v;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $v): static
    {
        $this->code = $v !== null && trim($v) !== '' ? $v : null;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $v): static
    {
        $this->category = $v;
        return $this;
    }

    public function getCompanyCategory(): ?Category
    {
        return $this->companyCategory;
    }

    public function setCompanyCategory(?Category $category): static
    {
        $this->companyCategory = $category;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $v): static
    {
        $this->address = $v;
        return $this;
    }

    public function getAddressEn(): ?string
    {
        return $this->addressEn;
    }

    public function setAddressEn(?string $v): static
    {
        $this->addressEn = $v;
        return $this;
    }

    public function getAddressRu(): ?string
    {
        return $this->addressRu;
    }

    public function setAddressRu(?string $v): static
    {
        $this->addressRu = $v;
        return $this;
    }

    public function getCityOrDistrict(): ?string
    {
        return $this->cityOrDistrict;
    }

    public function setCityOrDistrict(?string $v): static
    {
        $this->cityOrDistrict = $v;
        return $this;
    }

    public function getCityOrDistrictEn(): ?string
    {
        return $this->cityOrDistrictEn;
    }

    public function setCityOrDistrictEn(?string $v): static
    {
        $this->cityOrDistrictEn = $v;
        return $this;
    }

    public function getCityOrDistrictRu(): ?string
    {
        return $this->cityOrDistrictRu;
    }

    public function setCityOrDistrictRu(?string $v): static
    {
        $this->cityOrDistrictRu = $v;
        return $this;
    }

    public function getManagerType(): ?string
    {
        return $this->managerType;
    }

    public function setManagerType(?string $v): static
    {
        $this->managerType = $v;
        return $this;
    }

    public function getManagerGender(): ?string
    {
        return $this->managerGender;
    }

    public function setManagerGender(?string $v): static
    {
        $this->managerGender = $v;
        return $this;
    }

    public function getManagerFirstName(): ?string
    {
        return $this->managerFirstName;
    }

    public function setManagerFirstName(?string $v): static
    {
        $this->managerFirstName = $v;
        return $this;
    }

    public function getManagerLastName(): ?string
    {
        return $this->managerLastName;
    }

    public function setManagerLastName(?string $v): static
    {
        $this->managerLastName = $v;
        return $this;
    }

    public function getManagerLastNameEn(): ?string
    {
        return $this->managerLastNameEn;
    }

    public function setManagerLastNameEn(?string $v): static
    {
        $this->managerLastNameEn = $v;
        return $this;
    }

    public function getManagerLastNameRu(): ?string
    {
        return $this->managerLastNameRu;
    }

    public function setManagerLastNameRu(?string $v): static
    {
        $this->managerLastNameRu = $v;
        return $this;
    }

    public function getDocumentDate(): ?string
    {
        return $this->documentDate;
    }

    public function setDocumentDate(?string $v): static
    {
        $this->documentDate = $v;
        return $this;
    }

    public function getAapKortelesPagrindas(): ?string
    {
        return $this->aapKortelesPagrindas;
    }

    public function setAapKortelesPagrindas(?string $v): static
    {
        $this->aapKortelesPagrindas = $v;

        return $this;
    }

    /**
     * Tekstas šablono žymei ${pagrindas}; jei DB tuščia — numatytasis sakinys.
     */
    public function resolveAapKortelesPagrindas(): string
    {
        $t = trim((string) ($this->aapKortelesPagrindas ?? ''));

        return $t !== '' ? $t : 'Vadovaujantis nemokamai išduodamų AAP sąrašu';
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $v): static
    {
        $this->role = $v;
        return $this;
    }

    public function getManagerFirstNameEn(): ?string
    {
        return $this->managerFirstNameEn;
    }

    public function setManagerFirstNameEn(?string $v): static
    {
        $this->managerFirstNameEn = $v;
        return $this;
    }

    public function getManagerFirstNameRu(): ?string
    {
        return $this->managerFirstNameRu;
    }

    public function setManagerFirstNameRu(?string $v): static
    {
        $this->managerFirstNameRu = $v;
        return $this;
    }

    public function getRoleEn(): ?string
    {
        return $this->roleEn;
    }

    public function setRoleEn(?string $v): static
    {
        $this->roleEn = $v;
        return $this;
    }

    public function getRoleRu(): ?string
    {
        return $this->roleRu;
    }

    public function setRoleRu(?string $v): static
    {
        $this->roleRu = $v;
        return $this;
    }

    public function getDirectory(): ?string
    {
        return $this->directory;
    }

    public function setDirectory(?string $v): static
    {
        $this->directory = $v;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $v): static
    {
        $this->createdAt = $v;
        return $this;
    }

    public function getModifiedAt(): ?\DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(?\DateTimeImmutable $modifiedAt): static
    {
        $this->modifiedAt = $modifiedAt;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): static
    {
        $this->deleted = $deleted;
        return $this;
    }

    public function getDeletedDate(): ?\DateTimeImmutable
    {
        return $this->deletedDate;
    }

    public function setDeletedDate(?\DateTimeImmutable $deletedDate): static
    {
        $this->deletedDate = $deletedDate;
        return $this;
    }

    /** @return Collection<int, CompanyWorker> */
    public function getCompanyWorkers(): Collection
    {
        return $this->companyWorkers;
    }

    public function addCompanyWorker(CompanyWorker $companyWorker): static
    {
        if (! $this->companyWorkers->contains($companyWorker)) {
            $this->companyWorkers->add($companyWorker);
            $companyWorker->setCompanyRequisite($this);
        }

        return $this;
    }

    public function removeCompanyWorker(CompanyWorker $companyWorker): static
    {
        if ($this->companyWorkers->removeElement($companyWorker)) {
            if ($companyWorker->getCompanyRequisite() === $this) {
                $companyWorker->setCompanyRequisite(null);
            }
        }

        return $this;
    }
}