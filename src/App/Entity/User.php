<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherAwareInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, PasswordHasherAwareInterface
{
    public const PLUGIN_OLD_PASSWORD = 'mysql_old_password';
    public const PLUGIN_NATIVE_PASSWORD = 'mysql_native_password';
    public const PLUGIN_PLAIN = 'plain';

    public const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_USER = 'ROLE_USER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $username = null;

    #[ORM\Column(length: 41)]
    private ?string $password = null;

    #[ORM\Column(type: 'string', columnDefinition: "enum('mysql_old_password', 'mysql_native_password', 'plain')")]
    private ?string $plugin = self::PLUGIN_OLD_PASSWORD;

    #[ORM\Column(insertable: false, updatable: false)]
    private ?int $liikmed_id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $lastlogin = null;

    #[ORM\Column]
    private ?int $jutukas_lastaccess = 0;

    #[ORM\Column(nullable: true)]
    private ?int $jutukas_firstmess = null;

    /**
     * @var Collection<int, UserRight>
     */
    #[ORM\OneToMany(targetEntity: UserRight::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userRights;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'liikmed_id', nullable: false)]
    private ?Member $member = null;

    public function __construct()
    {
        $this->userRights = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPlugin(): ?string
    {
        return $this->plugin;
    }

    public function setPlugin(string $plugin): static
    {
        $this->plugin = $plugin;

        return $this;
    }

    public function getLiikmedId(): ?int
    {
        return $this->liikmed_id;
    }

    public function setLiikmedId(int $liikmed_id): static
    {
        $this->liikmed_id = $liikmed_id;

        return $this;
    }

    public function getLastlogin(): ?\DateTimeInterface
    {
        return $this->lastlogin;
    }

    public function setLastlogin(\DateTimeInterface $lastlogin): static
    {
        $this->lastlogin = $lastlogin;

        return $this;
    }

    // Used by fixtures
    public function setLastLoginString(string $login): static
    {
        return $this->setLastlogin(new \DateTime($login));
    }

    public function getJutukasLastaccess(): ?int
    {
        return $this->jutukas_lastaccess;
    }

    public function setJutukasLastaccess(int $jutukas_lastaccess): static
    {
        $this->jutukas_lastaccess = $jutukas_lastaccess;

        return $this;
    }

    public function getJutukasFirstmess(): ?int
    {
        return $this->jutukas_firstmess;
    }

    public function setJutukasFirstmess(?int $jutukas_firstmess): static
    {
        $this->jutukas_firstmess = $jutukas_firstmess;

        return $this;
    }

    public function getRoles(): array
    {
        $roles = [self::ROLE_USER];

        /** @var UserRight $userRight */
        foreach ($this->userRights as $userRight) {
            switch($userRight->getCode()) {
                case UserRight::USER_RIGHT_KASSA_SUPER_ADMIN:
                    // Role hierarchy
                    $roles[] = self::ROLE_SUPER_ADMIN;
                    $roles[] = self::ROLE_ADMIN;
                    break;
                case UserRight::USER_RIGHT_KASSA_ADMIN:
                    $roles[] = self::ROLE_ADMIN;
                    break;
                default:
                    break;
            }
        }

        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }

    public function getPasswordHasherName(): ?string
    {
        // plugin name is mapped to proper PasswordHasher class in config/security.yaml
        return $this->getPlugin();
    }

    /**
     * @return Collection<int, UserRight>
     */
    public function getUserRights(): Collection
    {
        return $this->userRights;
    }

    public function addUserRight(UserRight $userRight): static
    {
        if (!$this->userRights->contains($userRight)) {
            $this->userRights->add($userRight);
            $userRight->setUser($this);
        }

        return $this;
    }

    public function removeUserRight(UserRight $userRight): static
    {
        if ($this->userRights->removeElement($userRight)) {
            // set the owning side to null (unless already changed)
            if ($userRight->getUser() === $this) {
                $userRight->setUser(null);
            }
        }

        return $this;
    }

    public function getMember(): Member
    {
        return $this->member;
    }

    public function setMember(Member $member): static
    {
        $this->member = $member;

        return $this;
    }
}
