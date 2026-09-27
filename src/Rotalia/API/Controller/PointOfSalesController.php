<?php

namespace Rotalia\API\Controller;

use App\Entity\Convent;
use App\Entity\PointOfSale;
use App\Entity\User;
use App\Form\FormHelper;
use App\Form\PointOfSaleType;
use App\Repository\ConventRepository;
use App\Repository\PointOfSaleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use App\Component\HttpFoundation\JSendResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class PointOfSalesController extends DefaultController
{
    /**
     * Get list of PointOfSales. Super admin will get all, admin will get PointOfSales for his convent.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    #[Route('/pointOfSales', methods: ['GET'])]
    public function list(PointOfSaleRepository $pointOfSaleQuery): JsonResponse
    {
        $this->requireAdmin();

        /** @var User $user */
        $user = $this->getUser();

        if ($this->isGranted(User::ROLE_SUPER_ADMIN)) {
            $pointOfSales = $pointOfSaleQuery->findBy([], ['id' => 'ASC']);
        } else {
            $pointOfSales = $pointOfSaleQuery->findBy(
                ['convent' => $user->getMember()->getConventId()],
                ['id' => 'ASC']
            );
        }

        return $this->json([
            'pointOfSales' => $pointOfSales,
        ]);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    #[Route('/pointOfSales/{id}', methods: ['GET'])]
    public function get(PointOfSaleRepository $pointOfSaleQuery, int $id): JsonResponse
    {
        $this->requireAdmin();

        $pointOfSale = $pointOfSaleQuery->findOneBy(['id' => $id]);

        if (!$pointOfSale) {
            return JSendResponse::createFail('Müügikohta ei leitud', 404);
        }

        return $this->json(['pointOfSale' => $pointOfSale]);
    }

    /**
     * @throws Throwable
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    #[Route('/pointOfSales', methods: ['POST'])]
    public function create(
        EntityManagerInterface $em,
        PointOfSaleRepository $pointOfSaleQuery,
        ConventRepository $conventQuery,
        Request $request,
    ): JsonResponse
    {
        $hash = $request->cookies->get('pos_hash');

        if ($hash && $pointOfSaleQuery->findOneBy(['hash' => $hash])) {
            return JSendResponse::createFail('See brauser on juba müügipunkt', 400);
        }

        /** @var User $user */
        $user = $this->getUser();
        $memberConventId = $user->getMember()->getConventId();
        $conventId = (int)$request->request->get('conventId', $memberConventId);

        /** @var Convent|null $convent */
        $convent = $conventQuery->find($conventId);

        if (!$convent) {
            return JSendResponse::createFail('Koondist ei leitud', 404);
        }

        $newHash = md5($request->getClientIp() . microtime());
        $pointOfSale = new PointOfSale();
        $pointOfSale
            ->setCreatedBy($user->getMember())
            ->setHash($newHash)
            ->setDeviceInfo($request->headers->get('User-Agent'))
            ->setCreatedAt(new \DateTime())
            ->setConvent($convent)
        ;

        $response = $this->handleSubmit($pointOfSale, $request, $em, $convent, $memberConventId);

        if ($response->isSuccessful()) {
            $response->headers->setCookie(new Cookie('pos_hash', $pointOfSale->getHash(), new \DateTime('+1 year')));
        }

        return $response;
    }

    /**
     * @throws Throwable
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    #[Route('/pointOfSales/{id}', methods: ['PATCH'])]
    public function update(
        EntityManagerInterface $em,
        PointOfSaleRepository $pointOfSaleQuery,
        Request $request,
        int $id,
    ): JsonResponse
    {
        $pointOfSale = $pointOfSaleQuery->findOneBy(['id' => $id]);

        if (!$pointOfSale) {
            return JSendResponse::createFail('Müügipunkti ei leitud', 404);
        }

        /** @var User $user */
        $user = $this->getUser();
        $memberConventId = $user->getMember()->getConventId();

        return $this->handleSubmit($pointOfSale, $request, $em, $pointOfSale->getConvent(), $memberConventId);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    #[Route('/pointOfSales/{id}', methods: ['DELETE'])]
    public function delete(EntityManagerInterface $em, PointOfSaleRepository $pointOfSaleQuery, int $id): JsonResponse
    {
        $this->requireAdmin();

        $pointOfSale = $pointOfSaleQuery->findOneBy(['id' => $id]);

        if (!$pointOfSale) {
            return JSendResponse::createFail('Müügipunkti ei leitud', 404);
        }

        /** @var User $user */
        $user = $this->getUser();
        $memberConventId = $user->getMember()->getConventId();

        if ($pointOfSale->getConventId() !== $memberConventId && !$this->isGranted(User::ROLE_SUPER_ADMIN)) {
            return JSendResponse::createFail('Õigused puuduvad, vajab super admin õigusi', 403);
        }

        $em->remove($pointOfSale);
        $em->flush();

        return $this->json(['message' => 'Müügipunkt kustutatud']);
    }

    /**
     * @throws Throwable
     */
    private function handleSubmit(
        PointOfSale $pointOfSale,
        Request $request,
        EntityManagerInterface $em,
        ?Convent $convent,
        int $memberConventId,
    ): JSendResponse
    {
        $this->requireAdmin();

        if ($convent === null) {
            return JSendResponse::createFail('Koondist ei leitud', 404);
        }

        if ($convent->getId() !== $memberConventId && !$this->isGranted(User::ROLE_SUPER_ADMIN)) {
            return JSendResponse::createFail('Teise konvendi müügikohtasid saab hallata ainult super admin', 403);
        }

        $pointOfSale->setConvent($convent);

        $form = $this->createForm(PointOfSaleType::class, $pointOfSale, [
            'csrf_protection' => false,
            'method' => $request->getMethod(),
        ]);

        return FormHelper::handleFormSubmit($form, $request, $em);
    }
}
