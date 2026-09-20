<?php

namespace Rotalia\API\Controller;

use App\Component\HttpFoundation\JSendResponse;
use App\Entity\Enum\ProductResourceType;
use App\Entity\Product;
use App\Entity\Report;
use App\Entity\User;
use App\Repository\ReportRowRepository;
use App\Service\Updates;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class EconomyReportController extends DefaultController
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    #[Route('/economyReport', methods: ['GET'])]
    public function get(
        Updates $updates,
        ReportRowRepository $reportRowQuery,
        #[MapQueryParameter] ?int $conventId = null,
        #[MapQueryParameter] ?string $dateFrom = null,
        #[MapQueryParameter] ?string $dateUntil = null,
    ): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $memberConventId = $user->getMember()->getConventId();
        $activeConventId = $conventId ?? $memberConventId;

        if (!$this->isGranted(User::ROLE_ADMIN)) {
            return JSendResponse::createFail('Ainult admin saab näha majandus aruandeid', 403);
        }

        if ($activeConventId !== $memberConventId && !$this->isGranted(User::ROLE_SUPER_ADMIN)) {
            return JSendResponse::createFail('Teise konvendi majandus aruandeid saab näha ainult super admin', 403);
        }

        try {
            $from = new \DateTime($dateFrom ?? '2000-01-01'); // ancient history
            $until = new \DateTime($dateUntil ?? 'now');
        } catch (\Exception $e) {
            return JSendResponse::createFail('Vigane kuupäev', 400, ['error' => $e->getMessage()]);
        }
        // Makes sure that the end date is included in the filter.
        $until->modify('+1 day');

        // LIMITED products
        $initialAmounts = [
            Product::INVENTORY_TYPE_STORAGE => $updates->findAmountsAtDate(Product::INVENTORY_TYPE_STORAGE, $activeConventId, $from),
            Product::INVENTORY_TYPE_WAREHOUSE => $updates->findAmountsAtDate(Product::INVENTORY_TYPE_WAREHOUSE, $activeConventId, $from),
        ];

        $finalAmounts = [
            Product::INVENTORY_TYPE_STORAGE => $updates->findAmountsAtDate(Product::INVENTORY_TYPE_STORAGE, $activeConventId, $until),
            Product::INVENTORY_TYPE_WAREHOUSE => $updates->findAmountsAtDate(Product::INVENTORY_TYPE_WAREHOUSE, $activeConventId, $until),
        ];

        $periodUpdates = [
            Product::INVENTORY_TYPE_STORAGE => $updates->getUpdatesBetweenDates(Product::INVENTORY_TYPE_STORAGE, $activeConventId, ProductResourceType::LIMITED->value, $from, $until),
            Product::INVENTORY_TYPE_WAREHOUSE => $updates->getUpdatesBetweenDates(Product::INVENTORY_TYPE_WAREHOUSE, $activeConventId, ProductResourceType::LIMITED->value, $from, $until),
        ];

        $limitedResults = [];
        $cash = [];

        foreach (Product::$inventoryTypes as $target) {
            $cash[$target]['initial'] = round($initialAmounts[$target]['cash'], 2);
            $cash[$target]['in'] = round($periodUpdates[$target]['cash']['in'], 2);
            $cash[$target]['internal_in'] = round($periodUpdates[$target]['cash']['internal_in'], 2);
            $cash[$target]['out'] = round($periodUpdates[$target]['cash']['out'], 2);
            $cash[$target]['internal_out'] = round($periodUpdates[$target]['cash']['internal_out'], 2);
            $cash[$target]['final'] = round($finalAmounts[$target]['cash'], 2);

            foreach ($initialAmounts[$target]['products'] as $id => $count) {
                $limitedResults[$id][$target] = [
                    'initial' => $count,
                    'in' => 0,
                    'internal_in' => 0,
                    'out' => 0,
                    'internal_out' => 0,
                    'average_price_out' => 0,
                    'final' => 0,
                ];
            }

            foreach ($periodUpdates[$target]['products'] as $id => $counts) {
                $netOut = $counts['out'] - $counts['internal_out'];
                $averagePriceOut = $netOut == 0 ? 0 : round($counts['total_price_out'] / $netOut, 2);

                if (array_key_exists($id, $limitedResults) && array_key_exists($target, $limitedResults[$id])) {
                    $limitedResults[$id][$target]['in'] = $counts['in'];
                    $limitedResults[$id][$target]['internal_in'] = $counts['internal_in'];
                    $limitedResults[$id][$target]['out'] = $counts['out'];
                    $limitedResults[$id][$target]['internal_out'] = $counts['internal_out'];
                    $limitedResults[$id][$target]['average_price_out'] = $averagePriceOut;
                } else {
                    $limitedResults[$id][$target] = [
                        'initial' => 0,
                        'in' => $counts['in'],
                        'internal_in' => $counts['internal_in'],
                        'out' => $counts['out'],
                        'internal_out' => $counts['internal_out'],
                        'average_price_out' => $averagePriceOut,
                        'final' => 0,
                    ];
                }
            }

            foreach ($finalAmounts[$target]['products'] as $id => $count) {
                if (array_key_exists($id, $limitedResults) && array_key_exists($target, $limitedResults[$id])) {
                    $limitedResults[$id][$target]['final'] = $count;
                } else {
                    $limitedResults[$id][$target] = [
                        'initial' => 0,
                        'in' => 0,
                        'internal_in' => 0,
                        'out' => 0,
                        'internal_out' => 0,
                        'average_price_out' => 0,
                        'final' => $count,
                    ];
                }
            }
        }

        // Average incoming price for these items
        foreach ($limitedResults as $id => $product) {
            $storageCount = !array_key_exists(Product::INVENTORY_TYPE_STORAGE, $product) ? 0 :
                $product[Product::INVENTORY_TYPE_STORAGE]['initial'] - $product[Product::INVENTORY_TYPE_STORAGE]['final'] - $product[Product::INVENTORY_TYPE_STORAGE]['internal_out'];
            $warehouseCount = !array_key_exists(Product::INVENTORY_TYPE_WAREHOUSE, $product) ? 0 :
                $product[Product::INVENTORY_TYPE_WAREHOUSE]['initial'] - $product[Product::INVENTORY_TYPE_WAREHOUSE]['final'] - $product[Product::INVENTORY_TYPE_WAREHOUSE]['internal_out'];
            $count = $storageCount + $warehouseCount;

            if ($count == 0) {
                $limitedResults[$id]['average_price_in'] = 0;
                continue;
            }

            // This can get slow
            $incomingRows = $reportRowQuery->findIncomingRowsForProduct((int)$id, $activeConventId, $until);

            $runningCount = 0;
            $runningPrice = 0;
            foreach ($incomingRows as $reportRow) {
                $runningCount += $reportRow->getCount();
                $runningPrice += $reportRow->getCurrentPrice() * $reportRow->getCount();

                if ($runningCount >= $count) {
                    break;
                }
            }

            $limitedResults[$id]['average_price_in'] = $runningCount == 0 ? 0 : round($runningPrice / $runningCount, 2);
        }

        // Unlimited
        $unlimitedUpdates = $updates->getUpdatesBetweenDates(Product::INVENTORY_TYPE_STORAGE, $activeConventId, ProductResourceType::UNLIMITED->value, $from, $until);

        $unlimitedResults = [];
        foreach ($unlimitedUpdates['products'] as $id => $counts) {
            $unlimitedResults[$id] = [
                'out' => $counts['out'],
                'average_price_out' => $counts['out'] == 0 ? 0 : round($counts['total_price_out'] / $counts['out'], 2),
            ];
        }

        return $this->json([
            ProductResourceType::LIMITED->value => $limitedResults,
            ProductResourceType::UNLIMITED->value => $unlimitedResults,
            'cash' => $cash,
        ]);
    }
}
