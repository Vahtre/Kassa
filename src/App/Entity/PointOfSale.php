<?php

namespace App\Entity;

use App\Repository\PointOfSaleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

#[ORM\Entity(repositoryClass: PointOfSaleRepository::class)]
#[ORM\Table(name: 'ollekassa_point_of_sale')]
class PointOfSale implements JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    private ?int $conventId = 6;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'convent_id', nullable: false)]
    private ?Convent $convent = null;

    #[ORM\Column(length: 100)]
    private ?string $hash = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $deviceInfo = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by', nullable: false)]
    private ?Member $createdBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getConventId(): ?int
    {
        return $this->conventId ?? $this->convent?->getId();
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

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(string $hash): static
    {
        $this->hash = $hash;

        return $this;
    }

    public function getDeviceInfo(): ?string
    {
        return $this->deviceInfo;
    }

    public function setDeviceInfo(?string $deviceInfo): static
    {
        $this->deviceInfo = $deviceInfo;

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
            'name' => $this->getName(),
            'deviceInfo' => $this->getDeviceInfo(),
            'convent' => $this->getConvent()?->getName(),
            'createdAt' => $this->getCreatedAt()?->format('H:i d.m.Y'),
            'createdBy' => $this->getCreatedBy()?->getFullName(),
        ];
    }
}
