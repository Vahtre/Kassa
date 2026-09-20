<?php

namespace App\Entity;

use App\Repository\CreditNettingRowRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

#[ORM\Entity(repositoryClass: CreditNettingRowRepository::class)]
#[ORM\Table(name: 'ollekassa_credit_netting_row')]
class CreditNettingRow implements JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'creditNettingRows')]
    #[ORM\JoinColumn(name: 'credit_netting_id', nullable: false, onDelete: 'CASCADE')]
    private ?CreditNetting $creditNetting = null;

    #[ORM\Column(name: 'convent_id')]
    private ?int $conventId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'convent_id', nullable: false)]
    private ?Convent $convent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $sum = null;

    #[ORM\Column(nullable: true)]
    private ?int $nettingDone = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreditNetting(): ?CreditNetting
    {
        return $this->creditNetting;
    }

    public function setCreditNetting(?CreditNetting $creditNetting): static
    {
        $this->creditNetting = $creditNetting;

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

    public function getSum(): ?float
    {
        return $this->sum === null ? null : (float)$this->sum;
    }

    public function setSum(string $sum): static
    {
        $this->sum = (string)(float)$sum;

        return $this;
    }

    public function getNettingDone(): bool
    {
        return (bool)$this->nettingDone;
    }

    public function setNettingDone(bool|int $nettingDone): static
    {
        $this->nettingDone = (int)$nettingDone;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'conventId' => $this->getConventId(),
            'convent' => $this->getConvent()?->getName(),
            'sum' => $this->getSum(),
            'nettingDone' => $this->getNettingDone(),
        ];
    }
}
