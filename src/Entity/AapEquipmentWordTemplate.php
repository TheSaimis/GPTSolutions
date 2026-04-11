<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AapEquipmentWordTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * AAP Word šablonai (sąrašas / kortelės) — turinys saugomas DB; generavimas naudoja pirmiau DB, paskui failus diske.
 */
#[ORM\Entity(repositoryClass: AapEquipmentWordTemplateRepository::class)]
#[ORM\Table(name: 'aap_equipment_word_template')]
#[ORM\UniqueConstraint(name: 'uniq_aap_template_kind_locale', columns: ['template_kind', 'template_locale'])]
#[ORM\HasLifecycleCallbacks]
class AapEquipmentWordTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** „sarasas“ arba „korteles“ */
    #[ORM\Column(name: 'template_kind', length: 20)]
    private string $templateKind = '';

    /** lt | en | ru */
    #[ORM\Column(name: 'template_locale', length: 8)]
    private string $templateLocale = 'lt';

    #[ORM\Column(length: 255)]
    private string $originalFilename = '';

    /** @var string|resource|null */
    #[ORM\Column(type: Types::BLOB)]
    private mixed $content = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTemplateKind(): string
    {
        return $this->templateKind;
    }

    public function setTemplateKind(string $templateKind): static
    {
        $this->templateKind = $templateKind;

        return $this;
    }

    public function getTemplateLocale(): string
    {
        return $this->templateLocale;
    }

    public function setTemplateLocale(string $templateLocale): static
    {
        $this->templateLocale = $templateLocale;

        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getContent(): string
    {
        if ($this->content === null) {
            return '';
        }
        if (is_string($this->content)) {
            return $this->content;
        }
        if (is_resource($this->content)) {
            $s = stream_get_contents($this->content);
            $this->content = $s !== false ? $s : '';

            return $this->content;
        }

        return '';
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
