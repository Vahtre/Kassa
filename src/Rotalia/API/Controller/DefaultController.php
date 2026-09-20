<?php

namespace Rotalia\API\Controller;

use App\Entity\Member;
use App\Entity\PointOfSale;
use App\Entity\User;
use App\Repository\PointOfSaleRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use App\Component\HttpFoundation\JSendResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class DefaultController extends AbstractController
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    protected function json(mixed $data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        if ($status < 400) {
            return self::getJSendResponse($data, $headers, $status, $this->getParameter('kernel.debug'));
        }

        return parent::json($data, $status, $headers, $context);
    }

    /**
     * @param mixed $data
     * @param array $headers
     * @param int $status
     * @param bool $isDebug
     * @return JsonResponse
     * @throws Throwable
     */
    public static function getJSendResponse(mixed $data, array $headers, int $status, bool $isDebug = false): JsonResponse
    {
        try {
            return JSendResponse::createSuccess($data, $headers, $status);
        } catch (Throwable $e) {
            // Should be a bug in the system, ie JSON encode error
            return JSendResponse::createError('Süsteemi viga', 500, [
                'error' => $e->getMessage(),
                'trace' => $isDebug ? $e->getTraceAsString() : false,
            ], null, $headers);
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function get(string $service)
    {
        return $this->container->get($service);
    }

    /**
     * @throws AccessDeniedHttpException
     */
    protected function requireSuperAdmin(): void
    {
        if (!$this->isGranted(User::ROLE_SUPER_ADMIN)) {
            throw new AccessDeniedHttpException();
        }
    }

    /**
     * @throws AccessDeniedHttpException
     */
    protected function requireAdmin(): void
    {
        if (!$this->isGranted(User::ROLE_ADMIN)) {
            throw new AccessDeniedHttpException();
        }
    }

    /**
     * @throws AccessDeniedHttpException
     */
    protected function requireUser(): void
    {
        if (!$this->isGranted(User::ROLE_USER)) {
            throw new AccessDeniedHttpException();
        }
    }

    /**
     * The current authenticated Member, or null when not logged in (e.g. an anonymous point of
     * sale request).
     */
    protected function getMemberOrNull(): ?Member
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getMember();
    }

    /**
     * The PointOfSale for the current request's "pos_hash" cookie, if any.
     */
    protected function getPos(Request $request, PointOfSaleRepository $pointOfSaleQuery): ?PointOfSale
    {
        $hash = $request->cookies->get('pos_hash');

        if (!$hash) {
            return null;
        }

        return $pointOfSaleQuery->findOneBy(['hash' => $hash]);
    }

    /**
     * Applies limit/offset to the query (0/null limit means "no limit") and returns the
     * "offset-end/total" Content-Range header value the frontend's pagination footers expect
     * (see kassa-admin-aruanded.html, kassa-admin-ostud.html, etc).
     */
    protected function limitQuery(QueryBuilder $query, ?int $limit, int $offset): string
    {
        $rowCount = (new Paginator($query))->count();

        if ($offset >= $rowCount) {
            $offset = 0;
        }

        if ($limit) {
            $limit = min($limit, $rowCount);
            $query
                ->setFirstResult($offset)
                ->setMaxResults($limit)
            ;
        } else {
            $limit = $rowCount;
        }

        return sprintf('%d-%d/%d', $offset, $offset + $limit, $rowCount);
    }
}
