<?php

namespace App\Entity;

use App\Repository\ReportRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\Table(name: 'ollekassa_report')]
#[ORM\HasLifecycleCallbacks]
class Report implements JsonSerializable
{
    public const TYPE_VERIFICATION = 'VERIFICATION'; // Physical verification report
    public const TYPE_UPDATE = 'UPDATE'; // Inventory update report

    public static array $types = [
        self::TYPE_VERIFICATION,
        self::TYPE_UPDATE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'member_id', nullable: true)]
    private ?Member $member = null;

    #[ORM\Column(name: 'convent_id')]
    private ?int $conventId = 6;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'convent_id', nullable: false)]
    private ?Convent $convent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $cash = '0';

    #[ORM\Column(length: 50)]
    private ?string $type = self::TYPE_VERIFICATION;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $target = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, ReportRow>
     */
    #[ORM\OneToMany(targetEntity: ReportRow::class, mappedBy: 'report', cascade: ['persist'], orphanRemoval: true)]
    private Collection $reportRows;

    private ?Report $previousVerification = null;

    public function __construct()
    {
        $this->reportRows = new ArrayCollection();
    }

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

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function setMember(?Member $member): static
    {
        $this->member = $member;

        if ($member !== null) {
            $this->setName($member->getFullName());
        }

        return $this;
    }

    public function getMemberName(): string
    {
        return $this->getMember()?->getFullName() ?? $this->getName();
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

    public function getCash(): float
    {
        return (float)$this->cash;
    }

    public function setCash(string $cash): static
    {
        $this->cash = (string)(float)$cash;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isUpdate(): bool
    {
        return $this->getType() === self::TYPE_UPDATE;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(?string $target): static
    {
        $this->target = $target;

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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = $this->createdAt ?? new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * @return Collection<int, ReportRow>
     */
    public function getReportRows(): Collection
    {
        return $this->reportRows;
    }

    public function addReportRow(ReportRow $reportRow): static
    {
        if (!$this->reportRows->contains($reportRow)) {
            $this->reportRows->add($reportRow);
            $reportRow->setReport($this);
        }

        return $this;
    }

    public function removeReportRow(ReportRow $reportRow): static
    {
        if ($this->reportRows->removeElement($reportRow)) {
            if ($reportRow->getReport() === $this) {
                $reportRow->setReport(null);
            }
        }

        return $this;
    }

    public function getReportRowForProduct(Product $product): ?ReportRow
    {
        foreach ($this->getReportRows() as $reportRow) {
            if ($reportRow->getProduct()?->getId() === $product->getId()) {
                return $reportRow;
            }
        }

        return null;
    }

    public function setPreviousVerification(?Report $previousVerification): static
    {
        $this->previousVerification = $previousVerification;

        return $this;
    }

    public function getPreviousVerification(): ?Report
    {
        return $this->previousVerification;
    }

    /**
     * Basic fields for the report.
     *
     * TODO: 'deficit' requires porting Rotalia\APIBundle\Classes\Updates (inventory delta
     * calculation) to Doctrine; stubbed to 0 until that follow-up lands.
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'source' => $this->getSource(),
            'target' => $this->getTarget(),
            'member' => $this->getMemberName(),
            'createdAt' => $this->getCreatedAt()?->format('H:i d.m.Y'),
            'cash' => $this->getCash(),
            'deficit' => 0, // TODO: see class docblock
        ];
    }

    /**
     * Includes report rows and the previous verification report.
     */
    public function getPartialAjaxData(): array
    {
        $reportRows = [];

        foreach ($this->getReportRows() as $reportRow) {
            $reportRows[] = $reportRow->jsonSerialize();
        }

        return [
            'id' => $this->getId(),
            'reportRows' => $reportRows,
            'previousReport' => $this->getPreviousVerification()?->getFullAjaxData(),
        ];
    }

    /**
     * Basic ajax data with report rows.
     *
     * TODO: does not yet include inventory 'updates' between this and the next verification
     * report; requires the Updates class port.
     */
    public function getFullAjaxData(): array
    {
        $reportRows = [];

        foreach ($this->getReportRows() as $reportRow) {
            $reportRows[] = $reportRow->jsonSerialize();
        }

        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'target' => $this->getTarget(),
            'member' => $this->getMemberName(),
            'createdAt' => $this->getCreatedAt()?->format('H:i d.m.Y'),
            'cash' => $this->getCash(),
            'reportRows' => $reportRows,
        ];
    }
}
