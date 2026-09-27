<?php

namespace Tests\Rotalia\API\Controller;

use App\Entity\User;
use AssertionError;
use Exception;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Rotalia\API\Controller\DefaultController;
use App\Component\HttpFoundation\JSendResponse;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Helpers\ControllerTestCase;
use Throwable;

/**
 * Provide public interface for protected methods
 */
interface DummyController
{
    public function testJson(int $status): JsonResponse;
    public function testRequireAdmin(): void;
    public function testRequireSuperAdmin(): void;
    public function testRequireUser(): void;
}

#[CoversClass(DefaultController::class)]
#[UsesClass(JSendResponse::class)]
class DefaultControllerTest extends ControllerTestCase
{
    private function getDummyController(): DummyController
    {
        $mockContainer = $this->getMockBuilder(ServiceLocator::class)->disableOriginalConstructor()
            ->onlyMethods(['has'])
            ->getMock();
        $mockContainer->method('has')->willReturn(false);

        $controller = new class extends DefaultController implements DummyController {
            protected function getParameter(string $name): string
            {
                return false; // debug
            }

            public function testJson(int $status): JsonResponse
            {
                return $this->json(['test'], $status);
            }

            public function testRequireAdmin(): void
            {
                $this->requireAdmin();
            }

            public function testRequireSuperAdmin(): void
            {
                $this->requireSuperAdmin();
            }

            public function testRequireUser(): void
            {
                $this->requireUser();
            }

            protected function isGranted(mixed $attribute, mixed $subject = null): bool
            {
                return false;
            }
        };

        $controller->setContainer($mockContainer);

        return $controller;
    }

    /**
     * @throws \JsonException
     */
    public function testJsonSuccess(): void
    {
        $response = $this->getDummyController()->testJson(200);
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => JSendResponse::JSEND_STATUS_SUCCESS,
            'data' => [
                'test',
            ],
        ], JSON_THROW_ON_ERROR), $response->getContent());
    }

    /**
     * @throws \JsonException
     */
    public function testJsonError(): void
    {
        $response = $this->getDummyController()->testJson(400);
        $this->assertJsonStringEqualsJsonString(json_encode([
            'test',
        ], JSON_THROW_ON_ERROR), $response->getContent());
    }

    /**
     * @throws Throwable
     */
    public function testGetJSendResponseSuccess(): void
    {
        $jsonData = [
            'dummy' => [
                'key' => [3, 4],
            ]
        ];
        $headers = ['header1' => 'value1'];

        $response = DefaultController::getJSendResponse($jsonData, $headers, 200, true);
        $this->assertJson($response->getContent());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => JSendResponse::JSEND_STATUS_SUCCESS,
            'data' => $jsonData,
        ], JSON_THROW_ON_ERROR), $response->getContent());
        $this->assertSame('value1', $response->headers->get('header1'));
    }

    /**
     * @throws Throwable
     */
    public function testGetJSendResponseError(): void
    {
        $errorObject = new class implements JsonSerializable {
            public function jsonSerialize(): array
            {
                throw new Exception('Test');
            }
        };

        $response = DefaultController::getJSendResponse($errorObject, ['h1' => 'v1'], 404);
        $this->assertJson($response->getContent());
        $this->assertJsonStringEqualsJsonString(json_encode([
            'status' => JSendResponse::JSEND_STATUS_ERROR,
            'message' => 'Süsteemi viga',
            'data' => [
                'error' => 'Test',
                'trace' => false,
            ],
        ], JSON_THROW_ON_ERROR), $response->getContent());
        $this->assertSame('v1', $response->headers->get('h1'));
    }

    public function testRequireAdmin(): void
    {
        $controller = $this->getDummyController();
        try {
            $controller->testRequireAdmin();
            throw new AssertionError('Not thrown');
        } catch (Throwable $e) {
            $this->assertInstanceOf(AccessDeniedHttpException::class, $e, $e->getMessage());
        }
    }

    public function testRequireSuperAdmin(): void
    {
        $controller = $this->getDummyController();
        try {
            $controller->testRequireSuperAdmin();
            throw new AssertionError('Not thrown');
        } catch (Throwable $e) {
            $this->assertInstanceOf(AccessDeniedHttpException::class, $e, $e->getMessage());
        }
    }

    public function testRequireUser(): void
    {
        $controller = $this->getDummyController();
        try {
            $controller->testRequireUser();
            throw new AssertionError('Not thrown');
        } catch (Throwable $e) {
            $this->assertInstanceOf(AccessDeniedHttpException::class, $e, $e->getMessage());
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testGet(): void
    {
        $controller = new DefaultController();
        $mockContainer = $this->getMockBuilder(Container::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock()
        ;
        $user = new User();
        $mockContainer->method('get')->willReturn($user);
        $controller->setContainer($mockContainer);
        $value = $controller->getService('user');
        $this->assertSame($user, $value);
    }
}
