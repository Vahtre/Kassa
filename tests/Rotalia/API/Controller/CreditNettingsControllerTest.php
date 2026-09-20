<?php

namespace Tests\Rotalia\API\Controller;

use App\Entity\CreditNetting;
use Hautelook\AliceBundle\PhpUnit\FixtureStore;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Rotalia\API\Controller\CreditNettingsController;
use Tests\Helpers\ControllerTestCase;
use Tests\Helpers\EntityManagerAwareTestCase;

#[CoversClass(CreditNettingsController::class)]
class CreditNettingsControllerTest extends ControllerTestCase
{
    use EntityManagerAwareTestCase;
    use RefreshDatabaseTrait;

    public function testListForbiddenForNonAdmin(): void
    {
        $this->loginSimpleUser();

        static::$client->request('GET', '/api/creditNettings');
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testListSuccess(): void
    {
        $this->loginAdmin();

        static::$client->request('GET', '/api/creditNettings');
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertResponseCountJsonPath(1, 'data.creditNettings');
        $this->assertResponseCountJsonPath(2, 'data.creditNettings.0.creditNettingRows');
    }

    public function testUpdateSuccess(): void
    {
        $this->loginAdmin(); // Tallinn

        /** @var CreditNetting $creditNetting */
        $creditNetting = FixtureStore::getFixtures()['CreditNetting_1'];

        static::$client->request('PATCH', '/api/creditNettings/' . $creditNetting->getId(), [
            'creditNettingRow' => ['nettingDone' => 1],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());

        $rows = $this->getResponseBodyJson()['data']['creditNetting']['creditNettingRows'];
        $tallinnRow = null;
        foreach ($rows as $row) {
            if ($row['convent'] === 'Tallinn') {
                $tallinnRow = $row;
            }
        }
        $this->assertNotNull($tallinnRow);
        $this->assertTrue($tallinnRow['nettingDone']);
    }

    public function testUpdateOtherConventForbidden(): void
    {
        $this->loginAdmin(); // Tallinn

        /** @var CreditNetting $creditNetting */
        $creditNetting = FixtureStore::getFixtures()['CreditNetting_1'];
        $tartu = FixtureStore::getFixtures()['Convent_7'];

        static::$client->request('PATCH', '/api/creditNettings/' . $creditNetting->getId(), [
            'conventId' => $tartu->getId(),
            'creditNettingRow' => ['nettingDone' => 1],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUpdateNotFound(): void
    {
        $this->loginAdmin();

        static::$client->request('PATCH', '/api/creditNettings/8888888', [
            'creditNettingRow' => ['nettingDone' => 1],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(404, $response->getStatusCode());
    }
}
