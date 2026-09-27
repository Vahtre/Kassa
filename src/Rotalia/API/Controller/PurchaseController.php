<?php

namespace Rotalia\API\Controller;

use App\Entity\Convent;
use App\Entity\Member;
use App\Entity\Product;
use App\Entity\Transaction;
use App\Exception\OutOfCreditException;
use App\Repository\MemberCreditRepository;
use App\Repository\MemberRepository;
use App\Repository\MemberStatusCreditLimitRepository;
use App\Repository\PointOfSaleRepository;
use App\Repository\ProductRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use App\Component\HttpFoundation\JSendResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Purchase products with credit or cash, or add credit by paying cash to the point of sale.
 */
class PurchaseController extends DefaultController
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    #[Route('/purchase/{payment}', methods: ['POST'])]
    public function payment(
        EntityManagerInterface $em,
        ProductRepository $productQuery,
        MemberRepository $memberQuery,
        MemberCreditRepository $memberCreditQuery,
        MemberStatusCreditLimitRepository $creditLimitQuery,
        SettingRepository $settingQuery,
        PointOfSaleRepository $pointOfSaleQuery,
        Request $request,
        string $payment,
    ): JsonResponse
    {
        switch (strtolower($payment)) {
            case 'cash':
                // Put cash into register to pay for products
                $transactionType = Transaction::TYPE_CASH_PURCHASE;
                $paymentType = 'Sularahamakse';
                break;
            case 'credit':
                // Use credit balance to pay for products
                $transactionType = Transaction::TYPE_CREDIT_PURCHASE;
                $paymentType = 'Krediidimakse';
                break;
            case 'refund':
                // Put money into cash register to receive credit
                $transactionType = Transaction::TYPE_CASH_PAYMENT;
                $paymentType = 'Krediidi lisamine';
                break;
            default:
                return JSendResponse::createError('Vigane makseviis: ' . $payment, 400);
        }

        $currentMember = $this->getMemberOrNull();
        $member = null;

        $memberId = $request->request->get('memberId');
        $basket = $request->request->all('basket');

        if ($payment !== 'refund' && empty($basket)) {
            return JSendResponse::createFail('Ostukorv puudub', 400);
        }

        $pos = $this->getPos($request, $pointOfSaleQuery);

        // User must be logged in or using a point of sale to proceed
        if ($currentMember) {
            $member = $currentMember;
        } elseif ($pos === null) {
            return JSendResponse::createFail('Ostmiseks peab olema sisse logitud', 403);
        }

        // Temporarily authenticated user via PoS
        if ($memberId) {
            $member = $memberQuery->find($memberId);
            if ($member === null) {
                return JSendResponse::createFail('Kasutajat ei leitud', 400);
            }
        }

        if ($member === null && $pos === null) {
            return JSendResponse::createError('Tehing ei ole lubatud, logi sisse', 403);
        }

        if ($member === null && $payment !== 'cash') {
            return JSendResponse::createError($paymentType . ' nõuab sisse logimist', 403);
        }

        if ($pos === null && $payment !== 'credit') {
            return JSendResponse::createError($paymentType . ' nõuab kassat (näiteks konvendi arvuti)', 403);
        }

        if ($pos !== null) {
            $conventId = $pos->getConventId();
            $requestedConventId = $request->request->get('conventId');
            if ($requestedConventId && (int)$requestedConventId !== $conventId) {
                $posConvent = $pos->getConvent()?->getName() ?? 'Tundmatu';
                return JSendResponse::createError('See brauser on määratud müügipunktiks (' . $posConvent . ') ja ei luba valitud konvendist ostu', 400);
            }
        } else {
            $conventId = (int)$request->request->get('conventId', $member->getConventId());
        }

        // Every Transaction row needs a Convent (convent_id NOT NULL) and a createdBy Member
        // (created_by NOT NULL). For POS-only cash purchases with no logged-in user, attribute the
        // transaction to whoever set up the POS.
        $transactionConvent = $em->getReference(Convent::class, $conventId);
        $transactionCreator = $currentMember ?? $pos?->getCreatedBy();

        // Use integer cents for summing to avoid floating point issues
        $totalSumCents = 0;

        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {
            if ($payment === 'refund') {
                $sum = (float)$request->request->get('sum', 0);
                if ($sum == 0.0) {
                    $connection->rollBack();
                    return JSendResponse::createError('Sisesta summa', 400);
                }

                $transaction = new Transaction();
                $transaction
                    ->setSum((string)$sum)
                    ->setCreatedBy($transactionCreator)
                    ->setMember($member)
                    ->setConvent($transactionConvent)
                    ->setType($transactionType)
                ;
                $em->persist($transaction);
                // Member pays cash into the register and receives credit.
                // totalSumCents is positive so cash increases and credit increases.
                $totalSumCents = (int)round(100 * $sum);
            } else {
                foreach ($basket as $item) {
                    try {
                        $this->validateBasketItem($item);
                    } catch (\Exception $e) {
                        $connection->rollBack();
                        return JSendResponse::createError('Ostukorvis on vigane toode: ' . $e->getMessage(), 400);
                    }

                    $product = $productQuery->find($item['id']);
                    if (!$product) {
                        $connection->rollBack();
                        return JSendResponse::createError('Ostukorvis on vigane toode: toodet ei leitud', 400);
                    }
                    Product::$activeConventId = $conventId;

                    $transaction = new Transaction();
                    $transaction
                        ->setCount((string)$item['count'])
                        ->setProduct($product)
                        ->setCurrentPrice((string)$product->getPrice())
                        ->setCreatedBy($transactionCreator)
                        ->setMember($member)
                        ->setConvent($transactionConvent)
                        ->setType($transactionType)
                    ;
                    $totalSumCents += (int)round(100 * $transaction->calculateSum());
                    $em->persist($transaction);

                    $product->setStorageCount(($product->getStorageCount() ?? 0) - (float)$item['count']);
                }
            }

            // Adjust member credit: purchases decrease it, refunds increase it
            if ($payment !== 'cash') {
                $creditLimitEntity = $creditLimitQuery->findOneBy(['status' => $member->getStatus()]);
                $creditLimit = $creditLimitEntity?->getCreditLimit();

                $convent = $pos?->getConvent();
                if ($convent === null && $member->getConvent()?->getId() === $conventId) {
                    $convent = $member->getConvent();
                }
                // conventId may differ from the member's own convent (e.g. super admin purchase elsewhere)
                $convent ??= $em->getReference(Convent::class, $conventId);

                $creditDelta = $payment === 'refund' ? $totalSumCents / 100 : -$totalSumCents / 100;
                $memberCredit = $memberCreditQuery->findOrCreateForMemberAndConvent($member, $convent);
                $memberCredit->adjustCredit($creditDelta, $creditLimit !== null ? (float)$creditLimit : null);
                $em->persist($memberCredit);
            }

            // Add convent cash
            if ($payment !== 'credit') {
                $setting = $settingQuery->getCurrentCashSetting($conventId);
                $currentCash = (float)$setting->getValue() * 100;
                $currentCash += $totalSumCents;
                $setting->setValue((string)($currentCash / 100));
                $em->persist($setting);
            }

            $em->flush();
            $connection->commit();

            return JSendResponse::createSuccess([
                'totalSumCents' => $totalSumCents,
                'newCredit' => $member?->getTotalCredit(),
            ]);
        } catch (OutOfCreditException $e) {
            $connection->rollBack();
            return JSendResponse::createError($e->getMessage(), 422);
        } catch (Throwable $e) {
            $connection->rollBack();
            return JSendResponse::createError($e->getMessage(), 500);
        }
    }

    /**
     * @throws \Exception
     */
    private function validateBasketItem(array $item): void
    {
        if (!isset($item['id'])) {
            throw new \Exception('ID puudub');
        }
        if (!isset($item['count']) || !is_numeric($item['count']) || (float)$item['count'] <= 0) {
            throw new \Exception('Vigane kogus ' . ($item['count'] ?? ''));
        }
        if (!isset($item['price'])) {
            throw new \Exception('Hind puudub');
        }
    }
}
