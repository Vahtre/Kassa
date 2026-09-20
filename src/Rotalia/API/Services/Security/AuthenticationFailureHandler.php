<?php

namespace Rotalia\API\Services\Security;

use App\Component\HttpFoundation\JSendResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Event\AuthenticationEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

#[AsEventListener(event: AuthenticationEvent::class, method: 'onAuthenticationFailure')]
class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof UserNotFoundException) {
            return JSendResponse::createFail('Vale kasutaja', Response::HTTP_UNAUTHORIZED);
        }
        if ($exception instanceof BadCredentialsException) {
            return JSendResponse::createFail('Vale kasutaja/parool', Response::HTTP_UNAUTHORIZED);
        }

        return JSendResponse::createFail('Logimine ebaõnnestus. '.$exception->getMessage(), Response::HTTP_UNAUTHORIZED);
    }
}
