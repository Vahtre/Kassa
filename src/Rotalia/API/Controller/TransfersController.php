<?php

namespace Rotalia\API\Controller;

use App\Entity\Convent;
use App\Entity\Transfer;
use App\Entity\User;
use App\Repository\ConventRepository;
use App\Repository\MemberCreditRepository;
use App\Repository\MemberRepository;
use App\Repository\TransferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use App\Component\HttpFoundation\JSendResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Admin-only manual credit adjustments ("transfers"): moving credit onto (or off) a member's
 * balance without an underlying purchase or cash refund. Unlike purchases, these are not subject
 * to the member's status credit limit.
 */
class TransfersController extends DefaultController
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    #[Route('/transfers', methods: ['GET'])]
    public function list(
        TransferRepository $transferQuery,
        #[MapQueryParameter] ?int $conventId = null,
        #[MapQueryParameter] ?int $memberId = null,
        #[MapQueryParameter] ?string $memberName = null,
        #[MapQueryParameter] ?string $dateFrom = null,
        #[MapQueryParameter] ?string $dateUntil = null,
        #[MapQueryParameter] ?int $limit = 5,
        #[MapQueryParameter] int $offset = 0,
    ): JsonResponse
    {
        $this->requireUser();

        /** @var User $user */
        $user = $this->getUser();
        $memberConventId = $user->getMember()->getConventId();

        $query = $transferQuery->createQueryBuilder('t');

        if ($memberId === $user->getMember()->getId()) {
            $query->andWhere('t.member = :memberId')->setParameter('memberId', $memberId);

            if ($conventId !== null) {
                $query->andWhere('IDENTITY(t.convent) = :conventId')->setParameter('conventId', $conventId);
            }
        } elseif ($conventId === $memberConventId) {
            if (!$this->isGranted(User::ROLE_ADMIN)) {
                return JSendResponse::createFail('Ainult admin saab pärida teiste ülekandeid', 403);
            }

            $query->andWhere('IDENTITY(t.convent) = :conventId')->setParameter('conventId', $conventId);

            if ($memberId !== null) {
                $query->andWhere('t.member = :memberId')->setParameter('memberId', $memberId);
            }
        } else {
            if (!$this->isGranted(User::ROLE_SUPER_ADMIN)) {
                return JSendResponse::createFail('Ainult super admin saab pärida teiste ülekandeid teistes konventides', 403);
            }

            if ($conventId !== null) {
                $query->andWhere('IDENTITY(t.convent) = :conventId')->setParameter('conventId', $conventId);
            }

            if ($memberId !== null) {
                $query->andWhere('t.member = :memberId')->setParameter('memberId', $memberId);
            }
        }

        if (!empty($memberName)) {
            $query
                ->leftJoin('t.member', 'm')
                ->andWhere("CONCAT(m.firstName, ' ', m.lastName) LIKE :memberName")
                ->setParameter('memberName', $memberName . '%')
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

        /** @var Transfer[] $transfers */
        $transfers = $query->getQuery()->getResult();

        return $this->json(['transfers' => $transfers], 200, ['Content-Range' => 'transfers ' . $contentRange]);
    }

    /**
     * @throws Throwable
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    #[Route('/transfers', methods: ['POST'])]
    public function create(
        EntityManagerInterface $em,
        MemberRepository $memberQuery,
        MemberCreditRepository $memberCreditQuery,
        ConventRepository $conventQuery,
        Request $request,
    ): JsonResponse
    {
        if (!$this->isGranted(User::ROLE_ADMIN)) {
            return JSendResponse::createFail('Ülekandeid saab lisada ainult admin', 403);
        }

        /** @var User $user */
        $user = $this->getUser();
        $memberConventId = $user->getMember()->getConventId();
        $conventId = (int)$request->request->get('conventId', $memberConventId);

        if ($conventId !== $memberConventId && !$this->isGranted(User::ROLE_SUPER_ADMIN)) {
            return JSendResponse::createFail('Teise konventi ülekande lisamiseks peab olema super admin', 403);
        }

        $transferData = $request->request->all('transfer');

        if (!isset($transferData['memberId'])) {
            return JSendResponse::createFail('Kasutajat ei sisestatud', 400);
        }

        $member = $memberQuery->find($transferData['memberId']);
        if ($member === null) {
            return JSendResponse::createFail('Kasutajat ei leitud', 400);
        }

        $sum = (float)($transferData['sum'] ?? 0);
        if ($sum == 0.0) {
            return JSendResponse::createFail('Summa peab olema nullist erinev', 400);
        }

        /** @var Convent|null $convent */
        $convent = $conventQuery->find($conventId);
        if (!$convent) {
            return JSendResponse::createFail('Koondist ei leitud', 404);
        }

        $transfer = new Transfer();
        $transfer
            ->setConvent($convent)
            ->setMember($member)
            ->setCreatedBy($user->getMember())
            ->setSum((string)$sum)
            ->setComment($transferData['comment'] ?? null)
        ;

        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {
            $em->persist($transfer);

            // Transfers are a manual admin adjustment, not subject to the member's credit limit
            $memberCredit = $memberCreditQuery->findOrCreateForMemberAndConvent($member, $convent);
            $memberCredit->adjustCredit($sum);
            $em->persist($memberCredit);

            $em->flush();
            $connection->commit();

            return JSendResponse::createSuccess(['transfer' => $transfer], [], 201);
        } catch (Throwable $e) {
            $connection->rollBack();
            return JSendResponse::createError($e->getMessage(), 500);
        }
    }
}
