<?php

namespace App\Service;

use App\Entity\Enum\ProductResourceType;
use App\Entity\Product;
use App\Entity\Report;
use App\Repository\ReportRepository;
use App\Repository\TransactionRepository;

/**
 * Computes cash and product-count deltas between reports/dates. Ported from the old Propel
 * Rotalia\APIBundle\Classes\Updates.
 */
class Updates
{
    public function __construct(
        private readonly TransactionRepository $transactionQuery,
        private readonly ReportRepository $reportQuery,
    ) {
    }

    /**
     * @param string $target storage|warehouse
     * @param string $resourceType LIMITED|UNLIMITED
     * @return array The values are positive if moving from store -> warehouse -> storage -> buyer
     */
    public function getUpdatesBetweenReports(
        string $target,
        int $conventId,
        string $resourceType,
        ?Report $report1,
        ?Report $report2,
    ): array {
        return $this->getUpdatesBetweenDates(
            $target,
            $conventId,
            $resourceType,
            $report1?->getCreatedAt(),
            $report2?->getCreatedAt(),
        );
    }

    /**
     * internal_in and internal_out mean transfers between storage and warehouse.
     */
    public function getUpdatesBetweenDates(
        string $target,
        int $conventId,
        string $resourceType,
        ?\DateTimeInterface $dateFrom,
        ?\DateTimeInterface $dateUntil,
    ): array {
        $updates = [
            'cash' => [
                'in' => 0,
                'out' => 0,
                'internal_in' => 0,
                'internal_out' => 0,
            ],
            'products' => [],
        ];

        if ($target === Product::INVENTORY_TYPE_STORAGE) {
            $transactions = $this->transactionQuery->findTransactionsBetween($conventId, $dateFrom, $dateUntil);

            foreach ($transactions as $transaction) {
                $product = $transaction->getProduct();

                if ($product !== null && $product->getResourceType() === $resourceType) {
                    $productId = $product->getId();

                    if (!array_key_exists($productId, $updates['products'])) {
                        $updates['products'][$productId] = [
                            'in' => 0,
                            'internal_in' => 0,
                            'out' => 0,
                            'internal_out' => 0,
                            'total_price_out' => 0,
                        ];
                    }

                    $updates['products'][$productId]['out'] += (int)$transaction->getCount();
                    $updates['products'][$productId]['total_price_out'] += $transaction->getSum();
                }
            }
        }

        if ($resourceType === ProductResourceType::LIMITED->value) {
            $updateReports = $this->reportQuery->findUpdateReportsBetween($conventId, $dateFrom, $dateUntil);

            return $this->collectUpdates($updateReports, $updates, $target);
        }

        return $updates;
    }

    /**
     * @param Report[] $updateReports
     */
    private function collectUpdates(array $updateReports, array $updates, string $target): array
    {
        foreach ($updateReports as $updateReport) {
            if ($updateReport->getTarget() === $target) {
                $direction = 'in';
            } elseif ($updateReport->getSource() === $target) {
                $direction = 'out';
            } else {
                continue;
            }

            $isInternalUpdate = (
                $updateReport->getTarget() === Product::INVENTORY_TYPE_WAREHOUSE && $updateReport->getSource() === Product::INVENTORY_TYPE_STORAGE
            ) || (
                $updateReport->getTarget() === Product::INVENTORY_TYPE_STORAGE && $updateReport->getSource() === Product::INVENTORY_TYPE_WAREHOUSE
            );

            $updates['cash'][$direction] += $updateReport->getCash();
            if ($isInternalUpdate) {
                $updates['cash']['internal_' . $direction] += $updateReport->getCash();
            }

            foreach ($updateReport->getReportRows() as $reportRow) {
                $product = $reportRow->getProduct();
                if ($product === null) {
                    continue;
                }
                $productId = $product->getId();

                if (!array_key_exists($productId, $updates['products'])) {
                    $updates['products'][$productId] = [
                        'in' => 0,
                        'internal_in' => 0,
                        'out' => 0,
                        'internal_out' => 0,
                        'total_price_out' => 0,
                    ];
                }

                $updates['products'][$productId][$direction] += $reportRow->getCount();
                if ($isInternalUpdate) {
                    $updates['products'][$productId]['internal_' . $direction] += $reportRow->getCount();
                } elseif ($direction === 'out') {
                    $updates['products'][$productId]['total_price_out'] += $reportRow->getCurrentPrice() * $reportRow->getCount();
                }
            }
        }

        return $updates;
    }

    /**
     * Expected cash/product counts for a given inventory as of a specific date, based on the
     * last verification report before that date plus updates since.
     */
    public function findAmountsAtDate(string $target, int $conventId, \DateTimeInterface $date): array
    {
        $previousVerification = $this->reportQuery->findLatestVerificationReportBefore($conventId, $target, $date);

        $updates = $this->getUpdatesBetweenDates(
            $target,
            $conventId,
            ProductResourceType::LIMITED->value,
            $previousVerification?->getCreatedAt(),
            $date,
        );

        $expectedCash = 0;
        $expectedProductCounts = [];

        if ($previousVerification !== null) {
            $expectedCash = $previousVerification->getCash();

            foreach ($previousVerification->getReportRows() as $row) {
                $product = $row->getProduct();
                if ($product !== null && $product->getResourceType() === ProductResourceType::LIMITED->value) {
                    $expectedProductCounts[$product->getId()] = $row->getCount();
                }
            }
        }

        $expectedCash += $updates['cash']['in'] - $updates['cash']['out'];
        foreach ($updates['products'] as $productId => $productUpdates) {
            $expectedProductCounts[$productId] = ($expectedProductCounts[$productId] ?? 0)
                + $productUpdates['in'] - $productUpdates['out'];
        }

        return [
            'cash' => $expectedCash,
            'products' => $expectedProductCounts,
        ];
    }

    /**
     * The expected-vs-actual cash+inventory discrepancy for a VERIFICATION report, in currency.
     */
    public function calculateDeficit(Report $report): float
    {
        if ($report->isUpdate()) {
            // Has no meaning for update reports
            return 0.0;
        }

        $previousVerification = $report->getPreviousVerification()
            ?? $this->reportQuery->findPreviousVerificationReport($report);

        $updates = $this->getUpdatesBetweenReports(
            $report->getTarget(),
            $report->getConventId(),
            ProductResourceType::LIMITED->value,
            $previousVerification,
            $report,
        );

        $expectedCash = 0;
        $expectedProductCounts = [];
        $prices = [];

        if ($previousVerification !== null) {
            $expectedCash = $previousVerification->getCash();

            foreach ($previousVerification->getReportRows() as $row) {
                $product = $row->getProduct();
                if ($product !== null && $product->getResourceType() === ProductResourceType::LIMITED->value) {
                    $expectedProductCounts[$product->getId()] = $row->getCount();
                    $prices[$product->getId()] = $row->getCurrentPrice();
                }
            }
        }

        $expectedCash += $updates['cash']['in'] - $updates['cash']['out'];
        foreach ($updates['products'] as $productId => $productUpdates) {
            $expectedProductCounts[$productId] = ($expectedProductCounts[$productId] ?? 0)
                + $productUpdates['in'] - $productUpdates['out'];
        }

        $realCash = $report->getCash();
        $realProductCounts = [];

        foreach ($report->getReportRows() as $row) {
            $product = $row->getProduct();
            if ($product !== null && $product->getResourceType() === ProductResourceType::LIMITED->value) {
                $realProductCounts[$product->getId()] = $row->getCount();
                $prices[$product->getId()] = $row->getCurrentPrice();
            }
        }

        $cashDiff = $expectedCash - $realCash;
        $productDiff = 0;

        foreach (array_unique(array_merge(array_keys($expectedProductCounts), array_keys($realProductCounts))) as $productId) {
            $expected = $expectedProductCounts[$productId] ?? 0;
            $real = $realProductCounts[$productId] ?? 0;
            $price = $prices[$productId] ?? 0;
            $productDiff += ($expected - $real) * $price;
        }

        return $cashDiff + $productDiff;
    }
}
