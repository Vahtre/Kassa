<?php

namespace App\Entity;

use App\Exception\OutOfCreditException;
use App\Repository\MemberCreditRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MemberCreditRepository::class)]
#[ORM\Table(name: 'ollekassa_member_credit')]
class MemberCredit
{
    public const STATUS_NEGATIVE = 'negative';
    public const STATUS_NULL = 'null';
    public const STATUS_POSITIVE = 'positive';
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'memberCredits')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Member $member = null;

    #[ORM\ManyToOne(inversedBy: 'memberCredits')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Convent $convent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $credit = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comment = null;

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

    public function getConvent(): ?Convent
    {
        return $this->convent;
    }

    public function setConvent(?Convent $convent): static
    {
        $this->convent = $convent;

        return $this;
    }

    public function getCredit(): ?string
    {
        return $this->credit;
    }

    public function setCredit(string $credit): static
    {
        $this->credit = $credit;

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

    public function getCreditStatus(): string
    {
        $credit = (float)$this->getCredit();

        if ($credit > 0) {
            return self::STATUS_POSITIVE;
        }

        if ($credit < 0) {
            return self::STATUS_NEGATIVE;
        }

        return self::STATUS_NULL;
    }

    /**
     * @throws OutOfCreditException
     */
    public function adjustCredit(float $amount, ?float $creditLimit = null): static
    {
        $currentCredit = (float)$this->getCredit();
        $newCredit = round($currentCredit + $amount, 2);

        if ($creditLimit !== null) {
            // The MemberCredit is per convent. Compare total credit against the credit limit
            $totalCredit = $this->getMember()->getTotalCredit();
            $newTotalCredit = round($totalCredit + $amount, 2);

            if ($newTotalCredit < $creditLimit) {
                throw new OutOfCreditException($newTotalCredit, $creditLimit);
            }
        }

        $this->setCredit((string)$newCredit);

        return $this;
    }
}
