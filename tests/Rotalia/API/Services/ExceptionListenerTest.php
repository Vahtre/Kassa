<?php

namespace Tests\Rotalia\API\Services;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rotalia\API\Services\ExceptionListener;
use App\Component\HttpFoundation\JSendResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

#[CoversClass(ExceptionListener::class)]
class ExceptionListenerTest extends TestCase
{
    /**
     * @throws Throwable
     */
    #[DataProvider('providerOnKernelEventDebug')]
    public function testOnKernelEvent(bool $isDebug, Throwable $throwable, string $expectedMessage, int $expectedStatus): void
    {
        $listener = new ExceptionListener($isDebug);
        $kernel = $this->getMockBuilder(HttpKernel::class)->disableOriginalConstructor()->getMock();
        $request = new Request();
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $throwable);
        $listener->onKernelException($event);

        $response = $event->getResponse();
        $content = $response->getContent();
        $this->assertSame($expectedStatus, $response->getStatusCode());
        $responseData = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(JSendResponse::JSEND_STATUS_ERROR, $responseData['status']);
        $this->assertEquals($expectedMessage, $responseData['message']);
        if ($isDebug) {
            $this->assertStringStartsWith('#0 ', $responseData['data']['trace']);
        } else {
            $this->assertArrayNotHasKey('data', $responseData);
        }
    }

    public static function providerOnKernelEventDebug(): array
    {
        return [
            [true, new Exception('test123'), 'Tekkis tehniline viga', Response::HTTP_INTERNAL_SERVER_ERROR],
            [false, new UnauthorizedHttpException('test'), 'Logi sisse', Response::HTTP_UNAUTHORIZED],
            [false, new BadRequestHttpException(), 'Kontrolli sisestatud andmeid', Response::HTTP_BAD_REQUEST],
            [false, new AccessDeniedHttpException(), 'Ligipääs puudub', Response::HTTP_FORBIDDEN],
            [false, new AccessDeniedException(), 'Ligipääs puudub', Response::HTTP_FORBIDDEN],
            [false, new NotFoundHttpException(), 'Lehekülge ei leitud', Response::HTTP_NOT_FOUND],
            [false, new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR), 'Tekkis tehniline viga', Response::HTTP_INTERNAL_SERVER_ERROR],
        ];
    }
}
