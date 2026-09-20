<?php

namespace App\Entity;

use App\Repository\ReportRowRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

#[ORM\Entity(repositoryClass: ReportRowRepository::class)]
#[ORM\Table(name: 'ollekassa_report_row')]
class ReportRow implements JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reportRows')]
    #[ORM\JoinColumn(name: 'report_id', nullable: false, onDelete: 'CASCADE')]
    private ?Report $report = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 1)]
    private ?string $count = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $currentPrice = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReport(): ?Report
    {
        return $this->report;
    }

    public function setReport(?Report $report): static
    {
        $this->report = $report;

        return $this;
    }

    public function getProduct(): ?Product
    {
        // Product's convent-scoped fields (price, status, ...) are resolved via this static,
        // matching the pattern used in Rotalia\API\Controller\ProductsController.
        if ($this->getReport()?->getConventId() !== null) {
            Product::$activeConventId = $this->getReport()->getConventId();
        }

        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getCount(): float
    {
        return (float)$this->count;
    }

    public function setCount(?string $count): static
    {
        // Convert empty string to 0
        $this->count = empty($count) ? '0' : (string)(float)$count;

        return $this;
    }

    public function getCurrentPrice(): float
    {
        return (float)$this->currentPrice;
    }

    public function setCurrentPrice(?string $currentPrice): static
    {
        $this->currentPrice = $currentPrice === null ? null : (string)(float)$currentPrice;

        return $this;
    }

    /**
     * Use the related Product's current price as the current price, if not already set.
     */
    public function updateCurrentPrice(): static
    {
        return $this->setCurrentPrice($this->getProduct()?->getPrice());
    }

    public function jsonSerialize(): array
    {
        return [
            'product' => $this->getProduct()?->jsonSerialize(),
            'count' => $this->getCount(),
            'currentPrice' => $this->getCurrentPrice(),
        ];
    }
}
