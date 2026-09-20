<?php

namespace App\Entity;

use App\Repository\GuardDutyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A single guard duty ("valve") shift assignment within a GuardDutyCycle. Not currently exposed
 * through the API - see GuardDutyCycle's docblock.
 */
#[ORM\Entity(repositoryClass: GuardDutyRepository::class)]
#[ORM\Table(name: 'valved')]
class GuardDuty
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'valve_tsyklid_id', nullable: false)]
    private ?GuardDutyCycle $guardDutyCycle = null;

    #[ORM\Column(name: 'kuupaev', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(name: 'aeg')]
    private ?int $time = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'liikmed_id', nullable: false)]
    private ?Member $member = null;

    #[ORM\Column(name: 'majavan')]
    private ?int $isAssigned = 0;

    #[ORM\Column(name: 'puudus')]
    private ?int $wasAbsent = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGuardDutyCycle(): ?GuardDutyCycle
    {
        return $this->guardDutyCycle;
    }

    public function setGuardDutyCycle(?GuardDutyCycle $guardDutyCycle): static
    {
        $this->guardDutyCycle = $guardDutyCycle;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getTime(): ?int
    {
        return $this->time;
    }

    public function setTime(int $time): static
    {
        $this->time = $time;

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

    public function isAssigned(): bool
    {
        return (bool)$this->isAssigned;
    }

    public function setIsAssigned(bool $isAssigned): static
    {
        $this->isAssigned = (int)$isAssigned;

        return $this;
    }

    public function wasAbsent(): bool
    {
        return (bool)$this->wasAbsent;
    }

    public function setWasAbsent(bool $wasAbsent): static
    {
        $this->wasAbsent = (int)$wasAbsent;

        return $this;
    }
}
