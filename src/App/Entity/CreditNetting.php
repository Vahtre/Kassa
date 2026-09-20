<?php

namespace App\Entity;

use App\Repository\CreditNettingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * A snapshot of cross-convent credit balances, generated periodically by the
 * `app:credit-netting` console command (App\Command\CreditNettingCommand). Rows are only
 * read/updated via the API; new CreditNetting/CreditNettingRow records are not created through
 * this controller.
 */
#[ORM\Entity(repositoryClass: CreditNettingRepository::class)]
#[ORM\Table(name: 'ollekassa_credit_netting')]
#[ORM\HasLifecycleCallbacks]
class CreditNetting implements JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * @var Collection<int, CreditNettingRow>
     */
    #[ORM\OneToMany(targetEntity: CreditNettingRow::class, mappedBy: 'creditNetting', cascade: ['persist'], orphanRemoval: true)]
    private Collection $creditNettingRows;

    public function __construct()
    {
        $this->creditNettingRows = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = $this->createdAt ?? new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * @return Collection<int, CreditNettingRow>
     */
    public function getCreditNettingRows(): Collection
    {
        return $this->creditNettingRows;
    }

    public function addCreditNettingRow(CreditNettingRow $row): static
    {
        if (!$this->creditNettingRows->contains($row)) {
            $this->creditNettingRows->add($row);
            $row->setCreditNetting($this);
        }

        return $this;
    }

    public function jsonSerialize(): array
    {
        $rows = [];

        foreach ($this->getCreditNettingRows() as $row) {
            $rows[] = $row->jsonSerialize();
        }

        return [
            'id' => $this->getId(),
            'createdAt' => $this->getCreatedAt()?->format('H:i d.m.Y'),
            'creditNettingRows' => $rows,
        ];
    }
}
