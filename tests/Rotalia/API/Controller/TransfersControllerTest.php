<?php

namespace Tests\Rotalia\API\Controller;

use App\Entity\Member;
use Hautelook\AliceBundle\PhpUnit\FixtureStore;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Rotalia\API\Controller\TransfersController;
use Tests\Helpers\ControllerTestCase;
use Tests\Helpers\EntityManagerAwareTestCase;

#[CoversClass(TransfersController::class)]
class TransfersControllerTest extends ControllerTestCase
{
    use EntityManagerAwareTestCase;
    use RefreshDatabaseTrait;

    public function testListUnauthorised(): void
    {
        static::$client->request('GET', '/api/transfers');
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testListOwnTransfers(): void
    {
        $user = $this->loginSimpleUser(); // Member_2123

        static::$client->request('GET', '/api/transfers', ['memberId' => $user->getMember()->getId()]);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertResponseCountJsonPath(1, 'data.transfers');
    }

    public function testCreateForbiddenForNonAdmin(): void
    {
        $this->loginSimpleUser();

        /** @var Member $member */
        $member = FixtureStore::getFixtures()['Member_2123'];

        static::$client->request('POST', '/api/transfers', [
            'transfer' => ['memberId' => $member->getId(), 'sum' => '5.00'],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCreateSuccess(): void
    {
        $this->loginAdmin(); // Tallinn admin

        /** @var Member $member */
        $member = FixtureStore::getFixtures()['Member_2123'];

        static::$client->request('POST', '/api/transfers', [
            'transfer' => [
                'memberId' => $member->getId(),
                'sum' => '7.50',
                'comment' => 'Boonus',
            ],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        $this->assertResponseEqualsJsonPath(7.5, 'data.transfer.sum');
        $this->assertResponseEqualsJsonPath('Boonus', 'data.transfer.comment');
    }

    public function testCreateZeroSumRejected(): void
    {
        $this->loginAdmin();

        /** @var Member $member */
        $member = FixtureStore::getFixtures()['Member_2123'];

        static::$client->request('POST', '/api/transfers', [
            'transfer' => ['memberId' => $member->getId(), 'sum' => '0'],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertResponseEqualsJsonPath('Summa peab olema nullist erinev', 'message');
    }

    public function testCreateMissingMemberRejected(): void
    {
        $this->loginAdmin();

        static::$client->request('POST', '/api/transfers', [
            'transfer' => ['sum' => '5.00'],
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertResponseEqualsJsonPath('Kasutajat ei sisestatud', 'message');
    }
}
