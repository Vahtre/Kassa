<?php

namespace Rotalia\API\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\PointOfSaleRepository;
use App\Repository\TransactionRepository;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use App\Component\HttpFoundation\JSendResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class PurchasesController extends DefaultController
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    #[Route('/purchase', methods: ['GET'])]
    public function list(
        TransactionRepository $purchaseQuery,
        PointOfSaleRepository $pointOfSaleQuery,
        Request $request,
        #[MapQueryParameter] ?int $conventId = null,
        #[MapQueryParameter] ?int $memberId = null,
        #[MapQueryParameter] ?string $memberName = null,
        #[MapQueryParameter] ?string $createdByName = null,
        #[MapQueryParameter] ?string $dateFrom = null,
        #[MapQueryParameter] ?string $dateUntil = null,
        #[MapQueryParameter] ?int $limit = 5,
        #[MapQueryParameter] int $offset = 0,
    ): JsonResponse
    {
        $query = $purchaseQuery->createQueryBuilder('t');
        $query
            ->andWhere('t.type = :type')
            ->setParameter('type', Transaction::TYPE_CREDIT_PURCHASE)
        ;

        $member = $this->getMemberOrNull();

        if ($member === null) {
            $pos = $this->getPos($request, $pointOfSaleQuery);

            if ($pos === null) {
                return JSendResponse::createFail('Ostude nägemiseks peab olema kas sisse logitud või kasutama müügipunkti', 403);
            }

            if ($conventId !== null && $conventId !== $pos->getConventId()) {
                return JSendResponse::createFail('Müügipunktist näeb ainult sama konvendi oste', 403);
            }

            if ($memberId !== null) {
                return JSendResponse::createFail('Müügipunktist ei saa vaadata üksiku kasutaja oste', 403);
            }

            $query->andWhere('IDENTITY(t.convent) = :conventId')->setParameter('conventId', $pos->getConventId());

            // Set other filtering by hand, matching kiosk-mode behaviour
            $dateFrom = (new \DateTime())->modify('-1 hour')->format('Y-m-d H:i:s');
            $dateUntil = null;
            $limit = null;
            $offset = 0;
        } else {
            $memberConventId = $member->getConventId();

            if ($memberId === $member->getId()) {
                $query
                    ->andWhere('(t.member = :selfId OR t.createdBy = :selfId)')
                    ->setParameter('selfId', $memberId)
                ;

                if ($conventId !== null) {
                    $query->andWhere('IDENTITY(t.convent) = :conventId')->setParameter('conventId', $conventId);
                }
            } elseif ($conventId === $memberConventId) {
                if (!$this->isGranted(User::ROLE_ADMIN)) {
                    return JSendResponse::createFail('Ainult admin saab pärida teiste oste', 403);
                }

                $query->andWhere('IDENTITY(t.convent) = :conventId')->setParameter('conventId', $conventId);

                if ($memberId !== null) {
                    $query
                        ->andWhere('(t.member = :memberId OR t.createdBy = :memberId)')
                        ->setParameter('memberId', $memberId)
                    ;
                }
            } else {
                if (!$this->isGranted(User::ROLE_SUPER_ADMIN)) {
                    return JSendResponse::createFail('Ainult super admin saab pärida teiste oste teistes konventides', 403);
                }

                if ($conventId !== null) {
                    $query->andWhere('IDENTITY(t.convent) = :conventId')->setParameter('conventId', $conventId);
                }

                if ($memberId !== null) {
                    $query
                        ->andWhere('(t.member = :memberId OR t.createdBy = :memberId)')
                        ->setParameter('memberId', $memberId)
                    ;
                }
            }
        }

        if (!empty($memberName)) {
            $query
                ->leftJoin('t.member', 'm')
                ->andWhere("CONCAT(m.firstName, ' ', m.lastName) LIKE :memberName")
                ->setParameter('memberName', $memberName . '%')
            ;
        }

        if (!empty($createdByName)) {
            $query
                ->leftJoin('t.createdBy', 'cb')
                ->andWhere("CONCAT(cb.firstName, ' ', cb.lastName) LIKE :createdByName")
                ->setParameter('createdByName', $createdByName . '%')
            ;
        }

        if (!empty($dateFrom)) {
            try {
                $from = new \DateTime($dateFrom);
            } catch (\Exception $e) {
                return JSendResponse::createFail('Vigane alguskuupäev', 400, ['dateFrom' => $e->getMessage()]);
            }
            $query->andWhere('t.createdAt >= :dateFrom')->setParameter('dateFrom', $from);
        }

        if (!empty($dateUntil)) {
            try {
                $until = new \DateTime($dateUntil);
                $until->modify('+1 day');
            } catch (\Exception $e) {
                return JSendResponse::createFail('Vigane lõppkuupäev', 400, ['dateUntil' => $e->getMessage()]);
            }
            $query->andWhere('t.createdAt < :dateUntil')->setParameter('dateUntil', $until);
        }

        $query->orderBy('t.createdAt', 'DESC');

        $contentRange = $this->limitQuery($query, $limit, $offset);

        /** @var Transaction[] $purchases */
        $purchases = $query->getQuery()->getResult();

        return $this->json(['purchases' => $purchases], 200, ['Content-Range' => 'purchases ' . $contentRange]);
    }
}
