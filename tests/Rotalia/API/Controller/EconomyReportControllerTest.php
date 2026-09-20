<?php

namespace Tests\Rotalia\API\Controller;

use App\Entity\Convent;
use Hautelook\AliceBundle\PhpUnit\FixtureStore;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Rotalia\API\Controller\EconomyReportController;
use Tests\Helpers\ControllerTestCase;
use Tests\Helpers\EntityManagerAwareTestCase;

#[CoversClass(EconomyReportController::class)]
class EconomyReportControllerTest extends ControllerTestCase
{
    use EntityManagerAwareTestCase;
    use RefreshDatabaseTrait;

    public function testForbiddenForNonAdmin(): void
    {
        $this->loginSimpleUser();

        static::$client->request('GET', '/api/economyReport');
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testForbiddenOtherConventForNonSuperAdmin(): void
    {
        $this->loginAdmin(); // Tallinn

        /** @var Convent $tartu */
        $tartu = FixtureStore::getFixtures()['Convent_7'];

        static::$client->request('GET', '/api/economyReport', ['conventId' => $tartu->getId()]);
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testSuccess(): void
    {
        $this->loginAdmin(); // Tallinn admin

        static::$client->request('GET', '/api/economyReport', [
            'dateFrom' => '2016-01-01',
            'dateUntil' => '2016-02-01',
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());

        $data = $this->getResponseBodyJson()['data'];
        $this->assertArrayHasKey('LIMITED', $data);
        $this->assertArrayHasKey('UNLIMITED', $data);
        $this->assertArrayHasKey('cash', $data);
        $this->assertArrayHasKey('storage', $data['cash']);
        $this->assertArrayHasKey('warehouse', $data['cash']);
    }
}
