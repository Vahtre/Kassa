<?php

namespace App\Entity;

use App\Repository\MemberRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MemberRepository::class)]
#[ORM\Table(name: 'liikmed')]
class Member
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'koondised_id')]
    private int $conventId = -1;

    #[ORM\Column(name: 'eesnimi', length: 50, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(name: 'perenimi', length: 50, nullable: true)]
    private ?string $lastName = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'koondised_id', nullable: false)]
    private ?Convent $convent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'koondised_alg', nullable: false)]
    private ?Convent $conventOrig = null;

    #[ORM\Column]
    private int $lahk_pohjused_id = 0;

    #[ORM\Column(length: 1)]
    private int $eemal = 0;

    #[ORM\Column(length: 200)]
    private ?string $tegevusala = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'staatused_id', nullable: false)]
    private ?MemberStatus $status = null;

    /**
     * @var Collection<int, MemberCredit>
     */
    #[ORM\OneToMany(targetEntity: MemberCredit::class, mappedBy: 'member', orphanRemoval: true)]
    private Collection $memberCredits;

    public function __construct()
    {
        $this->memberCredits = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return implode(' ', [$this->getFirstName(), $this->getLastName()]);
    }

    public function getTotalCredit(): float
    {
        $total = 0.0;

        foreach ($this->getMemberCredits() as $memberCredit) {
            $total += (float)$memberCredit->getCredit();
        }

        return $total;
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

    public function getConventOrig(): ?Convent
    {
        return $this->conventOrig;
    }

    public function setConventOrig(?Convent $conventOrig): static
    {
        $this->conventOrig = $conventOrig;

        return $this;
    }

    public function getLahkPohjusedId(): ?int
    {
        return $this->lahk_pohjused_id;
    }

    public function setLahkPohjusedId(int $lahk_pohjused_id): static
    {
        $this->lahk_pohjused_id = $lahk_pohjused_id;

        return $this;
    }

    public function getEemal(): ?int
    {
        return $this->eemal;
    }

    public function setEemal(int $eemal): static
    {
        $this->eemal = $eemal;

        return $this;
    }

    public function getTegevusala(): ?string
    {
        return $this->tegevusala;
    }

    public function setTegevusala(string $tegevusala): static
    {
        $this->tegevusala = $tegevusala;

        return $this;
    }

    public function getStatus(): ?MemberStatus
    {
        return $this->status;
    }

    public function setStatus(?MemberStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, MemberCredit>
     */
    public function getMemberCredits(): Collection
    {
        return $this->memberCredits;
    }

    public function addMemberCredit(MemberCredit $memberCredit): static
    {
        if (!$this->memberCredits->contains($memberCredit)) {
            $this->memberCredits->add($memberCredit);
            $memberCredit->setMember($this);
        }

        return $this;
    }

    public function removeMemberCredit(MemberCredit $memberCredit): static
    {
        if ($this->memberCredits->removeElement($memberCredit)) {
            // set the owning side to null (unless already changed)
            if ($memberCredit->getMember() === $this) {
                $memberCredit->setMember(null);
            }
        }

        return $this;
    }
}
