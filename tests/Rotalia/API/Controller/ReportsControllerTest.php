<?php

namespace Tests\Rotalia\API\Controller;

use App\Entity\Convent;
use App\Entity\Product;
use App\Entity\Report;
use Hautelook\AliceBundle\PhpUnit\FixtureStore;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Rotalia\API\Controller\ReportsController;
use Tests\Helpers\ControllerTestCase;
use Tests\Helpers\EntityManagerAwareTestCase;

#[CoversClass(ReportsController::class)]
class ReportsControllerTest extends ControllerTestCase
{
    use EntityManagerAwareTestCase;
    use RefreshDatabaseTrait;

    public function testListUnauthorised(): void
    {
        static::$client->request('GET', '/api/reports');
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertResponseEqualsJsonPath('Ligipääs puudub', 'message');
    }

    public function testListSuccess(): void
    {
        $this->loginSimpleUser(); // Tallinn convent

        static::$client->request('GET', '/api/reports', ['limit' => 5]);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertResponseCountJsonPath(2, 'data.reports');
        // Most recent first
        $this->assertResponseEqualsJsonPath(15.5, 'data.reports.0.cash');
        // Deficit is computed (not the old stubbed 0) - just check the key is present and numeric
        $this->assertIsNumeric($this->getResponseBodyJson()['data']['reports'][0]['deficit']);
    }

    public function testListOtherConventForbidden(): void
    {
        $this->loginSimpleUser();

        /** @var Convent $tartu */
        $tartu = FixtureStore::getFixtures()['Convent_7'];

        static::$client->request('GET', '/api/reports', ['conventId' => $tartu->getId()]);
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGetSuccess(): void
    {
        $this->loginSimpleUser();

        /** @var Report $report */
        $report = FixtureStore::getFixtures()['Report_1'];

        static::$client->request('GET', '/api/reports/' . $report->getId());
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertResponseCountJsonPath(1, 'data.report.reportRows');
        $this->assertResponseEqualsJsonPath(10.0, 'data.report.reportRows.0.count');
        // 'updates' is now really computed rather than stubbed null
        $this->assertArrayHasKey('cash', $this->getResponseBodyJson()['data']['updates']);
        $this->assertArrayHasKey('products', $this->getResponseBodyJson()['data']['updates']);
    }

    public function testGetNotFound(): void
    {
        $this->loginSimpleUser();

        static::$client->request('GET', '/api/reports/8888888');
        $response = static::$client->getResponse();

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertResponseEqualsJsonPath('Aruannet ei leitud', 'message');
    }

    public function testGetLatestVerification(): void
    {
        $this->loginSimpleUser();

        static::$client->request('GET', '/api/reports/-1', ['target' => 'storage']);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        // Report_2 was created after Report_1
        $this->assertResponseEqualsJsonPath(15.5, 'data.report.cash');
    }

    public function testCreateSuccess(): void
    {
        $this->loginSimpleUser();

        /** @var Product $premium */
        $premium = FixtureStore::getFixtures()['Product_1'];

        static::$client->request('POST', '/api/reports', [
            'type' => Report::TYPE_VERIFICATION,
            'Report' => [
                'cash' => '12.34',
                'target' => 'storage',
                'reportRows' => [
                    ['productId' => $premium->getId(), 'count' => 13],
                ],
            ],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        $this->assertResponseEqualsJsonPath(12.34, 'data.report.cash');

        // Verification report should have set Product_1's Tallinn storage count to 13
        static::$client->request('GET', '/api/products', ['name' => $premium->getName()]);
        $this->assertResponseEqualsJsonPath(13.0, 'data.products.0.storageCount');
    }

    public function testCreateUpdateMovesInventoryBetweenTargets(): void
    {
        $this->loginAdmin(); // UPDATE reports require admin

        /** @var Product $premium */
        $premium = FixtureStore::getFixtures()['Product_1'];

        static::$client->request('POST', '/api/reports', [
            'type' => Report::TYPE_UPDATE,
            'Report' => [
                'cash' => '0',
                'source' => 'warehouse',
                'target' => 'storage',
                'reportRows' => [
                    ['productId' => $premium->getId(), 'count' => 3],
                ],
            ],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());

        static::$client->request('GET', '/api/products', ['name' => $premium->getName()]);
        $data = $this->getResponseBodyJson()['data']['products'][0];
        // ProductInfo_1 fixture starts at warehouseCount 15, storageCount 5
        $this->assertEquals(12.0, $data['warehouseCount']);
        $this->assertEquals(8.0, $data['storageCount']);
    }

    public function testCreateUpdateSameSourceAndTargetRejected(): void
    {
        $this->loginAdmin();

        static::$client->request('POST', '/api/reports', [
            'type' => Report::TYPE_UPDATE,
            'Report' => [
                'cash' => '0',
                'source' => 'storage',
                'target' => 'storage',
                'reportRows' => [],
            ],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateOtherConventForbidden(): void
    {
        $this->loginSimpleUser();

        /** @var Convent $tartu */
        $tartu = FixtureStore::getFixtures()['Convent_7'];

        static::$client->request('POST', '/api/reports', [
            'conventId' => $tartu->getId(),
            'Report' => [
                'cash' => '1.00',
                'target' => 'storage',
                'reportRows' => [],
            ],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCreateUpdateTypeRequiresAdmin(): void
    {
        $this->loginSimpleUser();

        static::$client->request('POST', '/api/reports', [
            'type' => Report::TYPE_UPDATE,
            'Report' => [
                'cash' => '1.00',
                'source' => 'warehouse',
                'target' => 'storage',
                'reportRows' => [],
            ],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }
}
