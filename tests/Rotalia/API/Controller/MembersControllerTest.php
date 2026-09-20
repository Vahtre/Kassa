<?php

namespace Tests\Rotalia\API\Controller;

use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Rotalia\API\Controller\MembersController;
use Tests\Helpers\ControllerTestCase;
use Tests\Helpers\EntityManagerAwareTestCase;

#[CoversClass(MembersController::class)]
class MembersControllerTest extends ControllerTestCase
{
    use EntityManagerAwareTestCase;
    use RefreshDatabaseTrait;

    public function testListUnauthorised(): void
    {
        static::$client->request('GET', '/api/members');
        $response = static::$client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testListSuccess(): void
    {
        $this->loginSimpleUser();

        static::$client->request('GET', '/api/members', ['limit' => 0]);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        // Member_1886, Member_2122, Member_2123 from fixtures
        $this->assertResponseCountJsonPath(3, 'data.members');
    }

    public function testListFilterByName(): void
    {
        $this->loginSimpleUser();

        static::$client->request('GET', '/api/members', ['name' => 'Keegi']);
        $response = static::$client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertResponseCountJsonPath(1, 'data.members');
        $this->assertResponseEqualsJsonPath('Keegi Oluline', 'data.members.0.name');
    }

    public function testCreditBalanceHiddenForSimpleUser(): void
    {
        $this->loginSimpleUser();

        static::$client->request('GET', '/api/members', ['name' => 'Keegi']);

        $this->assertResponseEqualsJsonPath(null, 'data.members.0.creditBalance');
    }

    public function testCreditBalanceShownForAdminOwnConvent(): void
    {
        $this->loginAdmin(); // Tallinn

        static::$client->request('GET', '/api/members', ['name' => 'Mitte']);

        // Member_2123 ("Mitte Keegi") has 20 + (-10) = 10 total credit
        $this->assertResponseEqualsJsonPath(10.0, 'data.members.0.creditBalance');
    }
}
