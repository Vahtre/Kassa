<?php

namespace Tests\Rotalia\API\Controller;

use App\Entity\Convent;
use App\Entity\PointOfSale;
use Symfony\Component\BrowserKit\Cookie;
use Hautelook\AliceBundle\PhpUnit\FixtureStore;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Rotalia\API\Controller\PointOfSalesController;
use Tests\Helpers\ControllerTestCase;
use Tests\Helpers\EntityManagerAwareTestCase;

#[CoversClass(PointOfSalesController::class)]
class PointOfSalesControllerTest extends ControllerTestCase
{
    use EntityManagerAwareTestCase;
    use RefreshDatabaseTrait;

    public function testListUnauthorised(): void
    {
        static::$client->request('GET', '/api/pointOfSales');
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertResponseEqualsJsonPath('Ligipääs puudub', 'message');
    }

    public function testListSimpleUserForbidden(): void
    {
        $this->loginSimpleUser();

        static::$client->request('GET', '/api/pointOfSales');
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testListAdminOwnConventOnly(): void
    {
        $this->loginAdmin(); // Tallinn convent

        static::$client->request('GET', '/api/pointOfSales');
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertResponseCountJsonPath(1, 'data.pointOfSales');
        $this->assertResponseEqualsJsonPath('Tallinna kassa', 'data.pointOfSales.0.name');
    }

    public function testListSuperAdminAllConvents(): void
    {
        $this->loginSuperAdmin();

        static::$client->request('GET', '/api/pointOfSales');
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertResponseCountJsonPath(2, 'data.pointOfSales');
    }

    public function testCreateSuccess(): void
    {
        $this->loginAdmin(); // Tallinn convent

        static::$client->request('POST', '/api/pointOfSales', [
            'pointOfSale' => [
                'name' => 'Uus kassa',
            ],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        $this->assertResponseEqualsJsonPath('Uus kassa', 'data.pointOfSale.name');
        $this->assertResponseEqualsJsonPath('Tallinn', 'data.pointOfSale.convent');
        $this->assertTrue($response->headers->getCookies() !== []);
    }

    public function testCreateAlreadyPointOfSale(): void
    {
        $this->loginAdmin();

        /** @var PointOfSale $existing */
        $existing = FixtureStore::getFixtures()['PointOfSale_1'];

        static::$client->getCookieJar()->set(new Cookie('pos_hash', $existing->getHash()));

        static::$client->request('POST', '/api/pointOfSales', [
            'pointOfSale' => [
                'name' => 'Teine kassa',
            ],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertResponseEqualsJsonPath('See brauser on juba müügipunkt', 'message');
    }

    public function testCreateOtherConventForbidden(): void
    {
        $this->loginAdmin(); // Tallinn convent

        /** @var Convent $tartu */
        $tartu = FixtureStore::getFixtures()['Convent_7'];

        static::$client->request('POST', '/api/pointOfSales', [
            'conventId' => $tartu->getId(),
            'pointOfSale' => [
                'name' => 'Tartu uus kassa',
            ],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUpdateSuccess(): void
    {
        $this->loginAdmin();

        /** @var PointOfSale $pos */
        $pos = FixtureStore::getFixtures()['PointOfSale_1'];

        static::$client->request('PATCH', '/api/pointOfSales/' . $pos->getId(), [
            'pointOfSale' => [
                'name' => 'Muudetud nimi',
            ],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertResponseEqualsJsonPath('Muudetud nimi', 'data.pointOfSale.name');
    }

    public function testUpdateNotFound(): void
    {
        $this->loginAdmin();

        static::$client->request('PATCH', '/api/pointOfSales/8888888', [
            'pointOfSale' => [
                'name' => 'Muudetud nimi',
            ],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertResponseEqualsJsonPath('Müügipunkti ei leitud', 'message');
    }

    public function testDeleteSuccess(): void
    {
        $this->loginAdmin();

        /** @var PointOfSale $pos */
        $pos = FixtureStore::getFixtures()['PointOfSale_1'];

        static::$client->request('DELETE', '/api/pointOfSales/' . $pos->getId());
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertResponseEqualsJsonPath('Müügipunkt kustutatud', 'data.message');
    }

    public function testDeleteNotFound(): void
    {
        $this->loginAdmin();

        static::$client->request('DELETE', '/api/pointOfSales/8888888');
        $response = static::$client->getResponse();

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertResponseEqualsJsonPath('Müügipunkti ei leitud', 'message');
    }
}
