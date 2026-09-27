<?php

namespace Tests\Rotalia\API\Controller;

use App\Entity\PointOfSale;
use App\Entity\User;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Rotalia\API\Controller\AuthenticationController;
use Rotalia\API\Services\Security\AuthenticationFailureHandler;
use Rotalia\API\Services\Security\UserLastLoginUpdater;
use App\Component\HttpFoundation\JSendResponse;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Tests\Helpers\ControllerTestCase;
use Tests\Helpers\EntityManagerAwareTestCase;

#[CoversClass(AuthenticationController::class)]
#[CoversClass(AuthenticationFailureHandler::class)]
#[CoversClass(UserLastLoginUpdater::class)]
class AuthenticationControllerTest extends ControllerTestCase
{
    use EntityManagerAwareTestCase;
    use RefreshDatabaseTrait;

    public function testCheckUnauthorized(): void
    {
        $client = self::$client;
        $client->request('GET', '/api/authentication');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertResponseEqualsJsonPath([
            'member' => null,
            'pointOfSaleId' => null,
        ], 'data');
    }

    public function testCheckSuccess(): void
    {
        $user = $this->loginSimpleUser();
        $member = $user->getMember();

        $client = self::$client;
        $client->request('GET', '/api/authentication');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertResponseEqualsJsonPath([
            'member' => [
                'id' => $user->getLiikmedId(),
                'name' => $member->getFullName(),
                'conventId' => $member->getConventId(),
                'creditBalance' => $member->getTotalCredit(),
                'roles' => $user->getRoles(),
            ],
            'pointOfSaleId' => null,
        ], 'data');
    }

    public function testCheckIncludesPointOfSaleId(): void
    {
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'user1']);

        $pos = (new PointOfSale())
            ->setName('Test kassa')
            ->setHash('test-pos-hash-1234567890abcdef')
            ->setCreatedBy($user->getMember())
            ->setCreatedAt(new \DateTime())
            ->setConvent($user->getMember()->getConvent())
        ;
        $this->entityManager->persist($pos);
        $this->entityManager->flush();

        $client = self::$client;
        $client->getCookieJar()->set(new Cookie('pos_hash', $pos->getHash()));
        $client->request('GET', '/api/authentication');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertResponseEqualsJsonPath($pos->getId(), 'data.pointOfSaleId');
    }

    public function testJsonSuccess(): void
    {
        $client = self::$client;
        $client->jsonRequest('POST', '/api/authentication', [
            'username' => 'user1',
            'password' => 'test123',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertBrowserHasCookie('MOCKSESSID');

        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'user1']);
        $this->assertNotEquals('2015-04-09 20:13:15', $user->getLastlogin()->format('Y-m-d H:i:s'));
    }

    public function testJsonWrongPass(): void
    {
        $client = self::$client;
        $client->jsonRequest('POST', '/api/authentication', [
            'username' => 'user1',
            'password' => 'test1234',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertResponseEqualsJsonPath([
            'message' => 'Vale kasutaja/parool',
            'status' => JSendResponse::JSEND_STATUS_FAIL,
        ]);
    }

    public function testLogoutSuccess(): void
    {
        $this->loginSimpleUser();

        $client = self::$client;
        $client->jsonRequest('DELETE', '/api/authentication');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertResponseEqualsJsonPath([
            'status' => JSendResponse::JSEND_STATUS_SUCCESS,
            'data' => 'Väljalogimine õnnestus',
        ]);
    }
}
