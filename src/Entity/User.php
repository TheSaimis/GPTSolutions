<?php
namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $deleted = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedDate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getFirstName(): ?string
    {return $this->firstName;}
    public function setFirstName(?string $firstName): static
    { $this->firstName = $firstName;return $this;}

    public function getLastName(): ?string
    {return $this->lastName;}
    public function setLastName(?string $lastName): static
    { $this->lastName = $lastName;return $this;}

    public function getEmail(): ?string
    {return $this->email;}
    public function setEmail(?string $email): static
    { $this->email = $email;return $this;}

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // For example: $this->plainPassword = null;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
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
}
