<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'ollekassa_product')]
class Product implements JsonSerializable
{
    public const INVENTORY_TYPE_WAREHOUSE = 'warehouse';
    public const INVENTORY_TYPE_STORAGE = 'storage';

    public static array $inventoryTypes = [
        self::INVENTORY_TYPE_WAREHOUSE,
        self::INVENTORY_TYPE_STORAGE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $productCode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = '0'; // TODO: remove from database

    #[ORM\Column(length: 50)]
    private ?string $amountType = Enum\ProductAmountType::PIECE->value;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $amount = '1.00';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = null; // TODO: remove from database

    #[ORM\Column(nullable: true)]
    private ?int $seq = null; // TODO: remove from database

    /**
     * @var Collection<int, ProductInfo>
     */
    #[ORM\OneToMany(targetEntity: ProductInfo::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    private Collection $productInfos;

    public static ?int $activeConventId;

    #[ORM\ManyToOne(inversedBy: 'products')]
    private ?ProductGroup $productGroup = null;

    public function __construct()
    {
        $this->productInfos = new ArrayCollection();
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

    public function getProductCode(): ?string
    {
        return $this->productCode;
    }

    public function setProductCode(?string $productCode): static
    {
        $this->productCode = $productCode;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->getActiveProductInfo()->getPrice();
    }

    /**
     * @see \App\Form\ProductType
     * @param string $price
     * @return $this
     */
    public function setPrice(string $price): static
    {
        $this->getActiveProductInfo()->setPrice($price);

        return $this;
    }

    public function getAmountType(): ?string
    {
        return $this->amountType;
    }

    public function setAmountType(string $amountType): static
    {
        $this->amountType = $amountType;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount === null ? null : (float)$this->amount;
    }

    public function setAmount(null|float|string $amount): static
    {
        $this->amount = $amount === null ? null : (string)(float)$amount;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->getActiveProductInfo()->getStatus();
    }

    public function setStatus($status): static
    {
        $this->getActiveProductInfo()->setStatus($status);

        return $this;
    }

    public function getSeq(): ?int
    {
        return $this->getActiveProductInfo()->getSeq();
    }

    public function setSeq(?int $seq): static
    {
        $this->getActiveProductInfo()->setSeq($seq);

        return $this;
    }

    /**
     * @return Collection<int, ProductInfo>
     */
    public function getProductInfos(): Collection
    {
        return $this->productInfos;
    }

    public function addProductInfo(ProductInfo $productInfo): static
    {
        if (!$this->productInfos->contains($productInfo)) {
            $this->productInfos->add($productInfo);
            $productInfo->setProduct($this);
        }

        return $this;
    }

    public function removeProductInfo(ProductInfo $productInfo): static
    {
        if ($this->productInfos->removeElement($productInfo)) {
            // set the owning side to null (unless already changed)
            if ($productInfo->getProduct() === $this) {
                $productInfo->setProduct(null);
            }
        }

        return $this;
    }

    public function getProductGroup(): ?ProductGroup
    {
        return $this->productGroup;
    }

    public function setProductGroup(?ProductGroup $productGroup): static
    {
        $this->productGroup = $productGroup;

        return $this;
    }

    public function getActiveProductInfo(): ProductInfo
    {
        foreach ($this->getProductInfos() as $productInfo) {
            if ($productInfo->getConventId() === self::$activeConventId) {
                return $productInfo;
            }
        }

        $productInfo = new ProductInfo();
        $productInfo->setConventId(self::$activeConventId);
        $this->addProductInfo($productInfo);

        return $productInfo;
    }

    public function getResourceType(): string
    {
        return $this->getActiveProductInfo()->getResourceType();
    }

    public function setResourceType(string $resourceType): self
    {
        $this->getActiveProductInfo()->setResourceType($resourceType);

        return $this;
    }

    public function getWarehouseCount(): ?float
    {
        return $this->getActiveProductInfo()->getWarehouseCount();
    }

    public function setWarehouseCount(?float $count): self
    {
        $this->getActiveProductInfo()->setWarehouseCount($count);

        return $this;
    }

    public function getStorageCount(): ?float
    {
        return $this->getActiveProductInfo()->getStorageCount();
    }

    public function setStorageCount(?float $count): self
    {
        $this->getActiveProductInfo()->setStorageCount($count);

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'productCodes' => !empty($this->getProductCode()) ? explode(',', $this->getProductCode()) : [],
            'price' => round((float)$this->getPrice(), 2),
            'amount' => round($this->getAmount(), 2),
            'amountType' => $this->getAmountType(),
            'status' => $this->getStatus(),
            'productGroup' => $this->getProductGroup()?->getId(),
            'warehouseCount' => $this->getWarehouseCount(),
            'storageCount' => $this->getStorageCount(),
            'resourceType' => $this->getResourceType(),
            'seq' => $this->getSeq(),
        ];
    }
}
