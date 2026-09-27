<?php

namespace App\Entity;

use App\Entity\Enum\ProductResourceType;
use App\Entity\Enum\ProductStatus;
use App\Repository\ProductInfoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductInfoRepository::class)]
#[ORM\Table(name: 'ollekassa_product_info')]
class ProductInfo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'productInfos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column]
    private ?int $conventId = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $warehouseCount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $storageCount = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = ProductStatus::DISABLED->value;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $resourceType = ProductResourceType::LIMITED->value;

    #[ORM\Column(nullable: true)]
    private ?int $seq = 1;

    private ?Convent $convent = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        // Keep the inverse side in sync. Alice fixtures only call setProduct() on the owning
        // side, so without this the Product's productInfos collection stays empty in memory
        // (Doctrine won't lazy-load it later because the entity is already in the identity map
        // with an unwrapped ArrayCollection). getActiveProductInfo() would then miss the real
        // row, create a phantom, and cascade-persist a duplicate.
        if ($product !== null) {
            $product->addProductInfo($this);
        }

        return $this;
    }

    public function getConventId(): ?int
    {
        return $this->conventId;
    }

    public function setConventId(?int $conventId): static
    {
        $this->conventId = $conventId;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = (string)(float)$price;

        return $this;
    }

    public function getWarehouseCount(): ?float
    {
        return $this->warehouseCount === null ? null : (float)$this->warehouseCount;
    }

    public function setWarehouseCount(null|float|string $warehouseCount): static
    {
        $this->warehouseCount = $warehouseCount === null ? null : (string)(float)$warehouseCount;

        return $this;
    }

    public function getStorageCount(): ?float
    {
        return $this->storageCount === null ? null : (float)$this->storageCount;
    }

    public function setStorageCount(null|float|string $storageCount): static
    {
        $this->storageCount = $storageCount === null ? null : (string)(float)$storageCount;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getResourceType(): ?string
    {
        return $this->resourceType;
    }

    public function setResourceType(?string $resourceType): static
    {
        $this->resourceType = $resourceType;

        return $this;
    }

    public function getSeq(): ?int
    {
        return $this->seq;
    }

    public function setSeq(?int $seq): static
    {
        $this->seq = $seq;

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
}
