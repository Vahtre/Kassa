<?php

namespace Tests\Rotalia\API\Services\Security;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rotalia\API\Services\Security\AuthenticationFailureHandler;
use App\Component\HttpFoundation\JSendResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

#[CoversClass(AuthenticationFailureHandler::class)]
class AuthenticationFailureHandlerTest extends TestCase
{
    /**
     * @throws \JsonException
     */
    #[DataProvider(methodName: 'providerOnAuthenticationFailure')]
    public function testOnAuthenticationFailure(AuthenticationException $exception, string $expectMessage): void
    {
        $handler = new AuthenticationFailureHandler();
        $response = $handler->onAuthenticationFailure(new Request(), $exception);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(JSendResponse::JSEND_STATUS_FAIL, $json['status']);
        $this->assertEquals($expectMessage, $json['message']);
    }

    public static function providerOnAuthenticationFailure(): array
    {
        return [
            [new UserNotFoundException(), 'Vale kasutaja'],
            [new BadCredentialsException(), 'Vale kasutaja/parool'],
            [new AuthenticationException('Test message'), 'Logimine ebaõnnestus. Test message'],
        ];
    }
}
