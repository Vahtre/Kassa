<?php

namespace Rotalia\API\Services;

use App\Component\HttpFoundation\JSendResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Class ExceptionListener
 * Catches exceptions and returns JSendResponse instead of default error page
 */
#[AsEventListener(event: ExceptionEvent::class, method: 'onKernelException', priority: 20)]
class ExceptionListener
{
    private bool $isDebug;

    public function __construct(
        #[Autowire('%kernel.debug%')]
        bool $isDebug
    )
    {
        $this->isDebug = $isDebug;
    }

    /**
     * Catch any kernel exceptions and return a JSendResponse
     *
     * @param ExceptionEvent $event
     * @throws \Throwable
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if ($throwable instanceof BadRequestHttpException) {
            $statusCode = Response::HTTP_BAD_REQUEST;
            $message = 'Kontrolli sisestatud andmeid';
        } else if ($throwable instanceof UnauthorizedHttpException) {
            $statusCode = Response::HTTP_UNAUTHORIZED;
            $message = 'Logi sisse';
        } else if (
            $throwable instanceof AccessDeniedHttpException ||
            $throwable instanceof AccessDeniedException
        ) {
            $statusCode = Response::HTTP_FORBIDDEN;
            $message = 'Ligipääs puudub';
        } else if ($throwable instanceof NotFoundHttpException) {
            $statusCode = Response::HTTP_NOT_FOUND;
            $message = 'Lehekülge ei leitud';
        } else {
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
            $message = 'Tekkis tehniline viga';
        }

        $data = $this->isDebug ? [
            'errorMessage' => $throwable->getMessage(),
            'trace' => $throwable->getTraceAsString(),
        ] : null;
        $response = JSendResponse::createError($message, $statusCode, $data);

        // Send the modified response object to the event
        $event->setResponse($response);
    }
}
