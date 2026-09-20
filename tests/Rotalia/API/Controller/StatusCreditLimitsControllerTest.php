<?php

namespace Tests\Rotalia\API\Controller;

use App\Entity\MemberStatus;
use Hautelook\AliceBundle\PhpUnit\FixtureStore;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Rotalia\API\Controller\StatusCreditLimitsController;
use Tests\Helpers\ControllerTestCase;
use Tests\Helpers\EntityManagerAwareTestCase;

#[CoversClass(StatusCreditLimitsController::class)]
class StatusCreditLimitsControllerTest extends ControllerTestCase
{
    use EntityManagerAwareTestCase;
    use RefreshDatabaseTrait;

    public function testListUnauthorised(): void
    {
        static::$client->request('GET', '/api/statusCreditLimits');
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testListSuccess(): void
    {
        $this->loginSimpleUser();

        static::$client->request('GET', '/api/statusCreditLimits');
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertResponseCountJsonPath(6, 'data.creditLimits');
        $this->assertResponseEqualsJsonPath(-25, 'data.creditLimits.2.creditLimit'); // Status_3
    }

    public function testUpdateRequiresSuperAdmin(): void
    {
        $this->loginAdmin(); // regular admin, not super admin

        /** @var MemberStatus $status */
        $status = FixtureStore::getFixtures()['Status_1'];

        static::$client->request('PATCH', '/api/statusCreditLimits/' . $status->getId(), [
            'creditLimit' => 10,
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUpdateSuccessForcesNegative(): void
    {
        $this->loginSuperAdmin();

        /** @var MemberStatus $status */
        $status = FixtureStore::getFixtures()['Status_1'];

        static::$client->request('PATCH', '/api/statusCreditLimits/' . $status->getId(), [
            'creditLimit' => 10, // positive input should be forced negative
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertResponseEqualsJsonPath(-10, 'data.creditLimit');
    }

    public function testUpdateNotFound(): void
    {
        $this->loginSuperAdmin();

        static::$client->request('PATCH', '/api/statusCreditLimits/8888888', [
            'creditLimit' => 10,
        ]);
        $response = static::$client->getResponse();

        $this->assertEquals(404, $response->getStatusCode());
    }
}
