<?php

namespace App\Entity;

use App\Repository\UserRightRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRightRepository::class)]
#[ORM\Table(name: 'users_rights')]
#[ORM\UniqueConstraint(name: 'users_rights_uq', columns: ['id', 'code'])]
class UserRight
{
    public const USER_RIGHT_KASSA_ADMIN = 'KASSAADMIN';
    public const USER_RIGHT_KASSA_SUPER_ADMIN = 'KASSASUPER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_pk')]
    private ?int $idPk = null; // auto-increment PK is required

    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $selgitus = null;

    #[ORM\ManyToOne(inversedBy: 'userRights')]
    #[ORM\JoinColumn(name: 'id', nullable: false)]
    private ?User $user = null;

    public function getIdPk(): ?int
    {
        return $this->idPk;
    }

    public function getId(): ?int
    {
        return $this->id ?? $this->user?->getId();
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getSelgitus(): ?string
    {
        return $this->selgitus;
    }

    public function setSelgitus(string $selgitus): static
    {
        $this->selgitus = $selgitus;

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
