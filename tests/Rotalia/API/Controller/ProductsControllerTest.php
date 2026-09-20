<?php

namespace Tests\Rotalia\API\Controller;

use App\Entity\Convent;
use App\Entity\Enum\ProductResourceType;
use App\Entity\Enum\ProductAmountType;
use App\Entity\Product;
use App\Entity\ProductGroup;
use App\Entity\Enum\ProductStatus;
use App\Entity\ProductInfo;
use Hautelook\AliceBundle\PhpUnit\FixtureStore;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Rotalia\API\Controller\DefaultController;
use Rotalia\API\Controller\ProductsController;
use Tests\Helpers\ControllerTestCase;
use Tests\Helpers\EntityManagerAwareTestCase;

#[CoversClass(ProductsController::class)]
#[UsesClass(DefaultController::class)]
class ProductsControllerTest extends ControllerTestCase
{
    use EntityManagerAwareTestCase;
    use RefreshDatabaseTrait;

    public function testListUnauthorized(): void
    {
        static::$client->request('GET', '/api/products');
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertResponseEqualsJsonPath('Ligipääs puudub', 'message');
    }

    public function testListSuccess(): void
    {
        $this->loginSimpleUser(); // Tallinn convent

        // Ordered by Tallinn's own sequence number: Premium (seq 1) comes before Aleksander (seq 2)
        /** @var Product $premium */
        $premium = FixtureStore::getFixtures()['Product_1'];
        /** @var ProductInfo $premiumTallinn */
        $premiumTallinn = FixtureStore::getFixtures()['ProductInfo_1'];

        static::$client->request('GET', '/api/products');
        $response = static::$client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertResponseEqualsJsonPath($premium->getName(), 'data.products.0.name');
        $this->assertResponseEqualsJsonPath($premiumTallinn->getStatus(), 'data.products.0.status');
        $this->assertResponseEqualsJsonPath($premiumTallinn->getPrice(), 'data.products.0.price');
    }

    public function testListSuccessOtherConvent(): void
    {
        $this->loginSimpleUser();
        $tartu = FixtureStore::getFixtures()['Convent_7'];

        static::$client->request('GET', '/api/products', [
            'conventId' => $tartu->getId(),
        ]);
        $response = static::$client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        // Aleksander has seq 1 for Tartu (but seq 2 for Tallinn), so it leads here but not in testListSuccess
        $this->assertResponseEqualsJsonPath('A le Coq Aleksander', 'data.products.0.name');
        // Status is different for Tartu
        $this->assertResponseEqualsJsonPath(ProductStatus::DISABLED->value, 'data.products.0.status');
    }

    public function testListFilterActive(): void
    {
        $this->loginSimpleUser();
        $tartu = FixtureStore::getFixtures()['Convent_7'];

        static::$client->request('GET', '/api/products', ['active' => 1, 'conventId' => $tartu->getId()]);
        $response = static::$client->getResponse();

        // Aleksander and Pilsner not returned for Tartu
        $this->assertResponseEqualsJsonPath('A le Coq Premium', 'data.products.0.name');
        $this->assertResponseEqualsJsonPath('Tuc Küpsis', 'data.products.1.name');
        $this->assertResponseCountJsonPath(2, 'data.products');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testListFilterInactive(): void
    {
        $this->loginSimpleUser();
        $tartu = FixtureStore::getFixtures()['Convent_7'];

        static::$client->request('GET', '/api/products', ['active' => 'false', 'conventId' => $tartu->getId()]);
        $response = static::$client->getResponse();

        // Premium and Tuc not returned for Tartu
        $this->assertResponseEqualsJsonPath('A le Coq Aleksander', 'data.products.0.name');
        $this->assertResponseEqualsJsonPath('A le Coq Pilsner', 'data.products.1.name');
        $this->assertResponseCountJsonPath(2, 'data.products');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testListFilterName(): void
    {
        $this->loginSimpleUser();

        static::$client->request('GET', '/api/products', ['name' => 'Premium']);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertResponseEqualsJsonPath('A le Coq Premium', 'data.products.0.name');
    }

    public function testListFilterProductCode(): void
    {
        $this->loginSimpleUser();

        static::$client->request('GET', '/api/products', ['productCode' => '12345678']);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertResponseEqualsJsonPath(['12345678'], 'data.products.0.productCodes');
    }

    public function testListFilterProductGroupId(): void
    {
        $this->loginSimpleUser();

        /** @var ProductGroup $beer */
        $beer = FixtureStore::getFixtures()['ProductGroup_1'];

        static::$client->request('GET', '/api/products', ['productGroup' => $beer->getId()]);
        $response = static::$client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertResponseEqualsJsonPath($beer->getId(), 'data.products.0.productGroup');
        $this->assertResponseEqualsJsonPath($beer->getId(), 'data.products.1.productGroup');
    }

    public function testCreateUnauthorized(): void
    {
        $this->loginSimpleUser();

        static::$client->request('POST', '/api/products', ['name' => 'Test']);
        $response = static::$client->getResponse();
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertResponseEqualsJsonPath('Ligipääs puudub', 'message');
    }

    public function testCreateBadRequest(): void
    {
        $this->loginAdmin();

        static::$client->request('POST', '/api/products');
        $response = static::$client->getResponse();
        $this->assertResponseEqualsJsonPath('Andmete salvestamine ebaõnnestus', 'message');
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateSuccess(): void
    {
        $this->loginAdmin();

        /** @var ProductGroup $beer */
        $beer = FixtureStore::getFixtures()['ProductGroup_1'];
        /** @var Convent $tartu */
        $tartu = FixtureStore::getFixtures()['Convent_7'];

        static::$client->request('POST', '/api/products?conventId=' . $tartu->getId(), [
            'product' => [
                'name' => 'New Product',
                'price' => '2.33',
                'amount' => '5.4',
                'amountType' => ProductAmountType::LITRE->value,
                'status' => ProductStatus::ACTIVE->value,
                'resourceType' => ProductResourceType::LIMITED->value,
                'productCode' => '112233',
                'productGroup' => $beer->getId(),
            ],
        ]);
        $response = static::$client->getResponse();
        $this->assertResponseEqualsJsonPath([
            'name' => 'New Product',
            'price' => 2.33,
            'amount' => 5.4,
            'amountType' => ProductAmountType::LITRE->value,
            'status' => ProductStatus::ACTIVE->value,
            'resourceType' => ProductResourceType::LIMITED->value,
            'productCodes' => ['112233'],
            'productGroup' => $beer->getId(),
            'warehouseCount' => null,
            'storageCount' => null,
            'seq' => 1, // Default seq for the newly created Tartu ProductInfo
            'id' => 5, // Update when adding Products to 2_product.yaml
        ], 'data.product');
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testProductInfos(): void
    {
        /** @var Product $premium */
        $premium = $this->entityManager->getRepository(Product::class)->findOneBy(['productCode' => '12345678']);
//        $premium = FixtureStore::getFixtures()['Product_2'];
        $this->assertNotNull($premium);
        $premiumInfos = $this->entityManager->getRepository(ProductInfo::class)->findBy(['product' => $premium]);
        $this->assertNotNull($premiumInfos);
        $this->assertCount(2, $premiumInfos);
        $infos = $premium->getProductInfos(); // TODO: fix - not returning any objects
        $this->assertCount(2, $infos);
    }

    public function testUpdateSuccess(): void
    {
        $this->loginAdmin();

        /** @var Product $premium */
        $premium = FixtureStore::getFixtures()['Product_1'];
        /** @var Convent $tartu */
        $tartu = FixtureStore::getFixtures()['Convent_7'];
        /** @var ProductInfo $tartuPremInfo */
        $tartuPremInfo = FixtureStore::getFixtures()['ProductInfo_2'];
        // For some reason this relation is not populated in fixtures
        $premium->addProductInfo($tartuPremInfo);

        Product::$activeConventId = $tartu->getId(); // for jsonSerialize

        static::$client->request('PATCH', '/api/products/' . $premium->getId(), [
            'conventId' => $tartu->getId(),
            'product' => [
                'name' => 'New Name',
                'price' => '5.2',
                'amount' => '3',
            ],
        ]);
        $response = static::$client->getResponse();
        $expected = $premium->jsonSerialize();
        $expected['name'] = 'New Name';
        $expected['price'] = 5.2;
        $expected['amount'] = 3;
        $this->assertResponseEqualsJsonPath($expected, 'data.product');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUpdateNotFound(): void
    {
        $this->loginAdmin();

        static::$client->request('PATCH', '/api/products/12345678');
        $response = static::$client->getResponse();
        $this->assertResponseEqualsJsonPath('Toodet ei leitud', 'message');
        $this->assertEquals(404, $response->getStatusCode());
    }
}
