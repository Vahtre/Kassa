<?php

namespace Tests\Rotalia\API\Controller;

use App\Entity\PointOfSale;
use App\Entity\Product;
use App\Entity\Setting;
use Hautelook\AliceBundle\PhpUnit\FixtureStore;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Rotalia\API\Controller\PurchaseController;
use Symfony\Component\BrowserKit\Cookie;
use Tests\Helpers\ControllerTestCase;
use Tests\Helpers\EntityManagerAwareTestCase;

#[CoversClass(PurchaseController::class)]
class PurchaseControllerTest extends ControllerTestCase
{
    use EntityManagerAwareTestCase;
    use RefreshDatabaseTrait;

    public function testCreditPurchaseSuccess(): void
    {
        $this->loginSimpleUser(); // Member_2123, Tallinn, starts with 10 total credit, -25 limit

        /** @var Product $premium */
        $premium = FixtureStore::getFixtures()['Product_1']; // Tallinn price 1.00

        static::$client->request('POST', '/api/purchase/credit', [
            'basket' => [
                ['id' => $premium->getId(), 'count' => 2, 'price' => 1.00],
            ],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertResponseEqualsJsonPath(200, 'data.totalSumCents');
        $this->assertResponseEqualsJsonPath(8.0, 'data.newCredit'); // 10 - 2
    }

    public function testCreditPurchaseOutOfCredit(): void
    {
        $this->loginSimpleUser();

        /** @var Product $premium */
        $premium = FixtureStore::getFixtures()['Product_1']; // Tallinn price 1.00

        static::$client->request('POST', '/api/purchase/credit', [
            'basket' => [
                ['id' => $premium->getId(), 'count' => 40, 'price' => 1.00],
            ],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testCreditPurchaseRequiresLogin(): void
    {
        static::$client->request('POST', '/api/purchase/credit', [
            'basket' => [['id' => 1, 'count' => 1, 'price' => 1.00]],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCashPurchaseRequiresPointOfSale(): void
    {
        static::$client->request('POST', '/api/purchase/cash', [
            'basket' => [['id' => 1, 'count' => 1, 'price' => 1.00]],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCashPurchaseWithPointOfSale(): void
    {
        /** @var PointOfSale $pos */
        $pos = FixtureStore::getFixtures()['PointOfSale_1']; // Tallinn
        /** @var Product $premium */
        $premium = FixtureStore::getFixtures()['Product_1'];

        static::$client->getCookieJar()->set(new Cookie('pos_hash', $pos->getHash()));

        static::$client->request('POST', '/api/purchase/cash', [
            'basket' => [
                ['id' => $premium->getId(), 'count' => 1, 'price' => 1.00],
            ],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertResponseEqualsJsonPath(100, 'data.totalSumCents');
    }

    public function testRefundSuccess(): void
    {
        $this->loginSimpleUser();

        /** @var PointOfSale $pos */
        $pos = FixtureStore::getFixtures()['PointOfSale_1']; // Tallinn

        static::$client->getCookieJar()->set(new Cookie('pos_hash', $pos->getHash()));

        static::$client->request('POST', '/api/purchase/refund', [
            'sum' => 5,
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertResponseEqualsJsonPath(15.0, 'data.newCredit'); // 10 + 5
    }

    public function testInvalidPaymentType(): void
    {
        $this->loginSimpleUser();

        static::$client->request('POST', '/api/purchase/bitcoin', [
            'basket' => [],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
    }
}
