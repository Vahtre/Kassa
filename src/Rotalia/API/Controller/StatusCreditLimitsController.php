<?php

namespace Rotalia\API\Controller;

use App\Entity\MemberStatus;
use App\Entity\MemberStatusCreditLimit;
use App\Repository\MemberStatusCreditLimitRepository;
use App\Repository\MemberStatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use App\Component\HttpFoundation\JSendResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class StatusCreditLimitsController extends DefaultController
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    #[Route('/statusCreditLimits', methods: ['GET'])]
    public function list(
        MemberStatusRepository $statusQuery,
        MemberStatusCreditLimitRepository $creditLimitQuery,
    ): JsonResponse
    {
        $this->requireUser();

        /** @var MemberStatus[] $statuses */
        $statuses = $statusQuery->findBy([], ['id' => 'ASC']);

        $data = [];
        foreach ($statuses as $status) {
            $creditLimit = $creditLimitQuery->findOneBy(['status' => $status]);

            $data[] = [
                'statusId' => $status->getId(),
                'statusName' => $status->getName(),
                'creditLimit' => $creditLimit?->getCreditLimit() ?? 0,
            ];
        }

        return $this->json(['creditLimits' => $data]);
    }

    /**
     * @throws Throwable
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    #[Route('/statusCreditLimits/{statusId}', methods: ['PATCH'])]
    public function update(
        EntityManagerInterface $em,
        MemberStatusRepository $statusQuery,
        MemberStatusCreditLimitRepository $creditLimitQuery,
        Request $request,
        int $statusId,
    ): JsonResponse
    {
        $this->requireSuperAdmin();

        $status = $statusQuery->find($statusId);

        if (!$status) {
            return JSendResponse::createFail('Staatust ei leitud', 404);
        }

        $statusCreditLimit = $creditLimitQuery->findOneBy(['status' => $status]);
        if (!$statusCreditLimit) {
            $statusCreditLimit = (new MemberStatusCreditLimit())->setStatus($status);
        }

        // Force a non-positive value for the credit limit
        $creditLimit = -1 * abs((int)$request->request->get('creditLimit'));
        $statusCreditLimit->setCreditLimit($creditLimit);

        $em->persist($statusCreditLimit);
        $em->flush();

        return $this->json([
            'statusId' => $status->getId(),
            'statusName' => $status->getName(),
            'creditLimit' => $statusCreditLimit->getCreditLimit(),
        ]);
    }
}
