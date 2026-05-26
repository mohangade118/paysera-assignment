<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'accounts')]
#[ORM\Index(name: 'idx_accounts_user_id', columns: ['user_id'])]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    /** @phpstan-ignore-next-line property.onlyRead */
    private int $id;

    #[ORM\Column(name: 'balance', type: Types::FLOAT)]
    private float $balance;

    #[ORM\Column(name: 'currency_type', type: Types::STRING, length: 5)]
    private string $currencyType;

    #[ORM\Column(name: 'status', type: Types::INTEGER)]
    private int $status;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'accounts')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    private User $user;

    /**
     * @var Collection<int, Transaction>
     */
    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'from_account')]
    private Collection $debitTransactions;

    /**
     * @var Collection<int, Transaction>
     */
    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'to_account')]
    private Collection $creditTransactions;

    public function __construct()
    {
        $this->debitTransactions = new ArrayCollection();
        $this->creditTransactions = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getBalance(): float
    {
        return $this->balance;
    }

    public function setBalance(float $balance): static
    {
        $this->balance = $balance;

        return $this;
    }

    public function getCurrencyType(): string
    {
        return $this->currencyType;
    }

    public function setCurrencyType(string $currencyType): static
    {
        $this->currencyType = $currencyType;

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getDebitTransactions(): Collection
    {
        return $this->debitTransactions;
    }

    public function addDebitTransaction(Transaction $debitTransaction): static
    {
        if (!$this->debitTransactions->contains($debitTransaction)) {
            $this->debitTransactions->add($debitTransaction);
            $debitTransaction->setFromAccount($this);
        }

        return $this;
    }

    public function removeDebitTransaction(Transaction $debitTransaction): static
    {
        if ($this->debitTransactions->removeElement($debitTransaction)) {
            // set the owning side to null (unless already changed)
            if ($debitTransaction->getFromAccount() === $this) {
                $debitTransaction->setFromAccount(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getCreditTransactions(): Collection
    {
        return $this->creditTransactions;
    }

    public function addCreditTransaction(Transaction $creditTransaction): static
    {
        if (!$this->creditTransactions->contains($creditTransaction)) {
            $this->creditTransactions->add($creditTransaction);
            $creditTransaction->setToAccount($this);
        }

        return $this;
    }

    public function removeCreditTransaction(Transaction $creditTransaction): static
    {
        if ($this->creditTransactions->removeElement($creditTransaction)) {
            // set the owning side to null (unless already changed)
            if ($creditTransaction->getToAccount() === $this) {
                $creditTransaction->setToAccount(null);
            }
        }

        return $this;
    }
}
