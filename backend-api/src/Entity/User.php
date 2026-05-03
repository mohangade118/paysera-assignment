<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable as DateTimeImmutableAlias;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: "users", indexes: [
    new ORM\Index(name: "idx_users_email", columns: ["email"]),
])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 25)]
    private string $firstName;

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 25)]
    private string $lastName;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 50, unique: true)]
    private string $email;

    #[ORM\Column(name: 'password', type: Types::STRING, length: 255)]
    private string $password;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(name: 'contact_no', type: Types::STRING, length: 10, unique: true)]
    private string $contactNo;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutableAlias $deletedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutableAlias $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutableAlias $updatedAt;

    /**
     * @var Collection<int, Account>
     */
    #[ORM\OneToMany(targetEntity: Account::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $accounts;

    public function __construct()
    {
        $this->accounts = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getContactNo(): ?string
    {
        return $this->contactNo;
    }

    public function setContactNo(string $contactNo): static
    {
        $this->contactNo = $contactNo;

        return $this;
    }

    public function getDeletedAt(): ?DateTimeImmutableAlias
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutableAlias $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutableAlias
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutableAlias $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutableAlias
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutableAlias $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Account>
     */
    public function getAccounts(): Collection
    {
        return $this->accounts;
    }

    public function addAccount(Account $account): static
    {
        if (!$this->accounts->contains($account)) {
            $this->accounts->add($account);
            $account->setUser($this);
        }

        return $this;
    }

    public function removeAccount(Account $account): static
    {
        if ($this->accounts->removeElement($account)) {
            // set the owning side to null (unless already changed)
            if ($account->getUser() === $this) {
                $account->setUser(null);
            }
        }

        return $this;
    }
}
