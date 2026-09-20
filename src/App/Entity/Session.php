<?php

namespace App\Entity;

use App\Repository\SessionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Legacy raw PHP session storage table. Unused anywhere in this codebase (Symfony manages its
 * own sessions separately) - mapped only for data availability/parity with the old Propel model.
 */
#[ORM\Entity(repositoryClass: SessionRepository::class)]
#[ORM\Table(name: 'sessions')]
class Session
{
    #[ORM\Id]
    #[ORM\Column(name: 'session', length: 40)]
    private string $session = '0';

    #[ORM\Column(nullable: true)]
    private ?int $lastaccess = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'usr_id', nullable: true)]
    private ?User $user = null;

    public function getSession(): string
    {
        return $this->session;
    }

    public function setSession(string $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function getLastaccess(): ?int
    {
        return $this->lastaccess;
    }

    public function setLastaccess(?int $lastaccess): static
    {
        $this->lastaccess = $lastaccess;

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
}
