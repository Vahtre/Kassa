<?php

namespace App\Entity;

use App\Repository\GuardDutyCycleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A guard duty ("valve") rotation/cycle for a convent, e.g. "freshmen guard duty for spring
 * semester". Not currently exposed through the API - no old Propel controller routed it either -
 * but is queried internally by Report::getGuardDutyMembers().
 */
#[ORM\Entity(repositoryClass: GuardDutyCycleRepository::class)]
#[ORM\Table(name: 'valve_tsyklid')]
class GuardDutyCycle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'nimi', length: 100)]
    private ?string $name = 'Valve';

    #[ORM\Column(name: 'valvajad', length: 10)]
    private ?string $guardians = 'koik';

    #[ORM\Column(name: 'koondised_id')]
    private ?int $conventId = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'koondised_id', nullable: false)]
    private ?Convent $convent = null;

    #[ORM\Column(name: 'algaeg', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $startAt = null;

    #[ORM\Column(name: 'loppaeg', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $endAt = null;

    #[ORM\Column(name: 'etapp')]
    private ?int $stage = 0;

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

    public function getGuardians(): ?string
    {
        return $this->guardians;
    }

    public function setGuardians(string $guardians): static
    {
        $this->guardians = $guardians;

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

    public function getStartAt(): ?\DateTimeInterface
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeInterface $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeInterface
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeInterface $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getStage(): ?int
    {
        return $this->stage;
    }

    public function setStage(int $stage): static
    {
        $this->stage = $stage;

        return $this;
    }
}
