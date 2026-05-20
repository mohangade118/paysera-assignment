<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TransactionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: "transactions")]
#[ORM\Index(name: "idx_transaction_from_ac_id", columns: ["from_account_id"])]
#[ORM\Index(name: "idx_transaction_to_ac_id", columns: ["to_account_id"])]
class Transaction
{
    /**
     * Populated by Doctrine when the entity is first persisted.
     *
     * @phpstan-ignore property.onlyRead
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id", type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(name: "amount", type: Types::FLOAT)]
    private float $amount;

    #[ORM\Column(name: "status", type: Types::TEXT, length: 25)]
    private string $status;

    #[ORM\Column(type: Types::TEXT)]
    private string $note;

    #[ORM\Column(length: 255)]
    private string $receipt;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'debitTransactions')]
    #[ORM\JoinColumn(name: 'from_account_id', referencedColumnName: 'id', nullable: false)]
    private Account $fromAccount;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'creditTransactions')]
    #[ORM\JoinColumn(name: 'to_account_id', referencedColumnName: 'id', nullable: false)]
    private Account $toAccount;

    #[ORM\Column(name: 'notifications_sent_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $notificationsSentAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getReceipt(): ?string
    {
        return $this->receipt;
    }

    public function setReceipt(string $receipt): static
    {
        $this->receipt = $receipt;

        return $this;
    }

    public function getFromAccount(): ?Account
    {
        return $this->fromAccount;
    }

    public function setFromAccount(?Account $fromAccount): static
    {
        $this->fromAccount = $fromAccount;

        return $this;
    }

    public function getToAccount(): ?Account
    {
        return $this->toAccount;
    }

    public function setToAccount(?Account $toAccount): static
    {
        $this->toAccount = $toAccount;

        return $this;
    }

    public function getNotificationsSentAt(): ?DateTimeImmutable
    {
        return $this->notificationsSentAt;
    }

    public function setNotificationsSentAt(?DateTimeImmutable $notificationsSentAt): static
    {
        $this->notificationsSentAt = $notificationsSentAt;

        return $this;
    }
}
