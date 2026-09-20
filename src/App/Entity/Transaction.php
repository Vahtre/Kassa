<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'ollekassa_transaction')]
#[ORM\HasLifecycleCallbacks]
class Transaction implements JsonSerializable
{
    public const TYPE_CASH_PURCHASE = 'CASH_PURCHASE';
    public const TYPE_CREDIT_PURCHASE = 'CREDIT_PURCHASE';
    public const TYPE_CASH_PAYMENT = 'CASH_PAYMENT';
    public const TYPE_CREDIT_PAYMENT = 'CREDIT_PAYMENT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'product_id', nullable: true, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'member_id', nullable: true)]
    private ?Member $member = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by', nullable: false)]
    private ?Member $createdBy = null;

    #[ORM\Column(name: 'convent_id')]
    private ?int $conventId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'convent_id', nullable: false)]
    private ?Convent $convent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 1, nullable: true)]
    private ?string $count = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $currentPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $sum = '0';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = $this->createdAt ?? new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTypeText(): string
    {
        return match ($this->getType()) {
            self::TYPE_CASH_PURCHASE => 'Sularahamakse',
            self::TYPE_CREDIT_PURCHASE => 'Krediidimakse',
            self::TYPE_CASH_PAYMENT => 'Tagasimakse kassasse',
            self::TYPE_CREDIT_PAYMENT => 'Tagasimakse krediit',
            default => 'Tundmatu tüüp',
        };
    }

    public function isPayment(): bool
    {
        return in_array($this->getType(), [self::TYPE_CREDIT_PAYMENT, self::TYPE_CASH_PAYMENT], true);
    }

    public function getProduct(): ?Product
    {
        if ($this->product !== null && $this->getConventId() !== null) {
            Product::$activeConventId = $this->getConventId();
        }

        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function setMember(?Member $member): static
    {
        $this->member = $member;

        return $this;
    }

    public function getCreatedBy(): ?Member
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Member $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getConventId(): ?int
    {
        return $this->conventId;
    }

    public function setConventId(int $conventId): static
    {
        $this->conventId = $conventId;

        return $this;
    }

    public function getConvent(): ?Convent
    {
        return $this->convent;
    }

    public function setConvent(?Convent $convent): static
    {
        $this->convent = $convent;
        $this->setConventId($convent?->getId());

        return $this;
    }

    public function getCount(): ?float
    {
        return $this->count === null ? null : (float)$this->count;
    }

    public function setCount(?string $count): static
    {
        $this->count = $count === null ? null : (string)(float)$count;

        return $this;
    }

    public function getCurrentPrice(): ?float
    {
        return $this->currentPrice === null ? null : (float)$this->currentPrice;
    }

    public function setCurrentPrice(?string $currentPrice): static
    {
        $this->currentPrice = $currentPrice === null ? null : (string)(float)$currentPrice;

        return $this;
    }

    public function getSum(): float
    {
        return (float)$this->sum;
    }

    public function setSum(string $sum): static
    {
        $this->sum = (string)(float)$sum;

        return $this;
    }

    /**
     * Calculates and stores the transaction's sum from count * currentPrice.
     */
    public function calculateSum(): float
    {
        $sum = round(($this->getCount() ?? 0) * ($this->getCurrentPrice() ?? 0), 2);
        $this->setSum((string)$sum);

        return $sum;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    // Used by fixtures
    public function setCreatedAtString(string $createdAt): static
    {
        return $this->setCreatedAt(new \DateTime($createdAt));
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'member' => $this->getMember()?->getFullName(),
            'createdBy' => $this->getCreatedBy()?->getFullName(),
            'count' => $this->getCount() !== null ? (int)$this->getCount() : null,
            'price' => $this->getCurrentPrice(),
            'product' => $this->getProduct()?->jsonSerialize(),
            'convent' => $this->getConvent()?->getName(),
            'createdAt' => $this->getCreatedAt()?->format('H:i d.m.Y'),
        ];
    }
}
