<?php

namespace Tests\Rotalia\API\Controller;

use App\Entity\Convent;
use App\Entity\Member;
use App\Entity\PointOfSale;
use Hautelook\AliceBundle\PhpUnit\FixtureStore;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Rotalia\API\Controller\PurchasesController;
use Symfony\Component\BrowserKit\Cookie;
use Tests\Helpers\ControllerTestCase;
use Tests\Helpers\EntityManagerAwareTestCase;

#[CoversClass(PurchasesController::class)]
class PurchasesControllerTest extends ControllerTestCase
{
    use EntityManagerAwareTestCase;
    use RefreshDatabaseTrait;

    public function testListOwnPurchases(): void
    {
        $member = $this->loginSimpleUser(); // Member_2123

        static::$client->request('GET', '/api/purchase', ['memberId' => $member->getMember()->getId()]);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertResponseCountJsonPath(2, 'data.purchases');
    }

    public function testListAnonymousWithoutPosForbidden(): void
    {
        static::$client->request('GET', '/api/purchase');
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testListWithPointOfSale(): void
    {
        /** @var PointOfSale $pos */
        $pos = FixtureStore::getFixtures()['PointOfSale_1']; // Tallinn

        static::$client->getCookieJar()->set(new Cookie('pos_hash', $pos->getHash()));

        static::$client->request('GET', '/api/purchase');
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        // Point-of-sale listing is hardcoded to the last hour (kiosk receipt view); the fixture
        // purchases are from 2016, so none show up here.
        $this->assertResponseCountJsonPath(0, 'data.purchases');
    }

    public function testListPosCannotFilterByMember(): void
    {
        /** @var PointOfSale $pos */
        $pos = FixtureStore::getFixtures()['PointOfSale_1'];

        static::$client->getCookieJar()->set(new Cookie('pos_hash', $pos->getHash()));

        static::$client->request('GET', '/api/purchase', ['memberId' => 1]);
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testListOtherMemberForbiddenForNonAdmin(): void
    {
        $this->loginSimpleUser();

        /** @var Member $otherMember */
        $otherMember = FixtureStore::getFixtures()['Member_2122'];

        static::$client->request('GET', '/api/purchase', [
            'conventId' => $otherMember->getConventId(),
            'memberId' => $otherMember->getId(),
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testListOtherConventForbiddenForNonSuperAdmin(): void
    {
        $this->loginAdmin();

        /** @var Convent $tartu */
        $tartu = FixtureStore::getFixtures()['Convent_7'];

        static::$client->request('GET', '/api/purchase', ['conventId' => $tartu->getId()]);
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }
}
