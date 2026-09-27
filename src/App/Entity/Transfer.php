<?php

namespace App\Entity;

use App\Repository\TransferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

#[ORM\Entity(repositoryClass: TransferRepository::class)]
#[ORM\Table(name: 'ollekassa_transfer')]
#[ORM\HasLifecycleCallbacks]
class Transfer implements JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'member_id', nullable: false)]
    private ?Member $member = null;

    #[ORM\Column(name: 'convent_id', insertable: false, updatable: false)]
    private ?int $conventId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'convent_id', nullable: false)]
    private ?Convent $convent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $sum = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by', nullable: false)]
    private ?Member $createdBy = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = $this->createdAt ?? new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getConventId(): ?int
    {
        return $this->conventId;
    }

    public function setConventId(?int $conventId): static
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

    public function getSum(): ?float
    {
        return $this->sum === null ? null : (float)$this->sum;
    }

    public function setSum(string $sum): static
    {
        $this->sum = (string)(float)$sum;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
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

    public function getCreatedBy(): ?Member
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Member $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'member' => $this->getMember()?->getFullName(),
            'convent' => $this->getConvent()?->getName(),
            'sum' => $this->getSum(),
            'createdAt' => $this->getCreatedAt()?->format('H:i d.m.Y'),
            'createdBy' => $this->getCreatedBy()?->getFullName(),
            'comment' => $this->getComment(),
        ];
    }
}
