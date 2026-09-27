<?php

namespace Rotalia\API\Controller;

use App\Component\HttpFoundation\JSendResponse;
use App\Entity\Convent;
use App\Entity\Enum\ProductResourceType;
use App\Entity\Product;
use App\Entity\Report;
use App\Entity\ReportRow;
use App\Entity\User;
use App\Repository\ConventRepository;
use App\Repository\ProductRepository;
use App\Repository\ReportRepository;
use App\Service\Updates;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class ReportsController extends DefaultController
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    #[Route('/reports', methods: ['GET'])]
    public function list(
        ReportRepository $reportQuery,
        Updates $updates,
        #[MapQueryParameter] ?string $memberName = null,
        #[MapQueryParameter] ?string $dateFrom = null,
        #[MapQueryParameter] ?string $dateUntil = null,
        #[MapQueryParameter] ?int $conventId = null,
        #[MapQueryParameter] ?string $reportType = null,
        #[MapQueryParameter] int $limit = 5,
        #[MapQueryParameter] int $offset = 0,
    ): JsonResponse
    {
        $this->requireUser();

        /** @var User $user */
        $user = $this->getUser();
        $memberConventId = $user->getMember()->getConventId();
        $activeConventId = $conventId ?? $memberConventId;

        if ($activeConventId !== $memberConventId && !$this->isGranted(User::ROLE_SUPER_ADMIN)) {
            return JSendResponse::createFail('Teise konvendi aruandeid saab näha ainult super admin', 403);
        }

        $query = $reportQuery->createQueryBuilder('r');
        $query
            ->andWhere('r.conventId = :conventId')
            ->setParameter('conventId', $activeConventId)
            ->orderBy('r.createdAt', 'DESC')
        ;

        if (!empty($memberName)) {
            $query
                ->leftJoin('r.member', 'm')
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
            $query->andWhere('r.createdAt >= :dateFrom')->setParameter('dateFrom', $from);
        }

        if (!empty($dateUntil)) {
            try {
                $until = new \DateTime($dateUntil);
                $until->modify('+1 day');
            } catch (\Exception $e) {
                return JSendResponse::createFail('Vigane lõppkuupäev', 400, ['dateUntil' => $e->getMessage()]);
            }
            $query->andWhere('r.createdAt < :dateUntil')->setParameter('dateUntil', $until);
        }

        if (!empty($reportType)) {
            if (!in_array($reportType, Report::$types, true)) {
                return JSendResponse::createFail('Vigane raporti tüüp', 400, ['reportType' => 'Vigane raporti tüüp: ' . $reportType]);
            }
            $query->andWhere('r.type = :type')->setParameter('type', $reportType);
        }

        $contentRange = $this->limitQuery($query, $limit, $offset);

        /** @var Report[] $reports */
        $reports = $query->getQuery()->getResult();

        foreach ($reports as $report) {
            $report->setDeficit($updates->calculateDeficit($report));
        }

        return $this->json(['reports' => $reports], 200, ['Content-Range' => 'reports ' . $contentRange]);
    }

    /**
     * When id == -1, returns the latest verification report for the given convent/target.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    #[Route('/reports/{id}', methods: ['GET'], requirements: ['id' => '-?\d+'])]
    public function get(
        ReportRepository $reportQuery,
        Updates $updates,
        Request $request,
        int $id,
    ): JsonResponse
    {
        $this->requireUser();

        /** @var User $user */
        $user = $this->getUser();
        $memberConventId = $user->getMember()->getConventId();

        if ($id === -1) {
            $conventId = (int)$request->query->get('conventId', $memberConventId);
            $target = $request->query->get('target', Product::INVENTORY_TYPE_STORAGE);

            if ($conventId !== $memberConventId && !$this->isGranted(User::ROLE_SUPER_ADMIN)) {
                return JSendResponse::createFail('Teise konvendi raporteid saab näha ainult super admin', 403);
            }

            $report = $reportQuery->findLatestVerificationReport($conventId, $target);

            $reportUpdates = $report === null ? null : $updates->getUpdatesBetweenReports(
                $target,
                $conventId,
                ProductResourceType::LIMITED->value,
                $report,
                null,
            );

            return $this->json([
                'report' => $report?->getFullAjaxData(),
                'updates' => $reportUpdates,
            ]);
        }

        $report = $reportQuery->findOneBy(['id' => $id]);

        if ($report === null) {
            return JSendResponse::createFail('Aruannet ei leitud', 404);
        }

        if ($report->getConventId() !== $memberConventId && !$this->isGranted(User::ROLE_SUPER_ADMIN)) {
            return JSendResponse::createFail('Teise konvendi raporteid saab näha ainult super admin', 403);
        }

        if ($report->getType() === Report::TYPE_VERIFICATION) {
            $previousVerification = $reportQuery->findPreviousVerificationReport($report);
            $report->setPreviousVerification($previousVerification);

            $reportUpdates = $updates->getUpdatesBetweenReports(
                $report->getTarget(),
                $report->getConventId(),
                ProductResourceType::LIMITED->value,
                $previousVerification,
                $report,
            );

            return $this->json([
                'report' => $report->getPartialAjaxData(),
                'updates' => $reportUpdates,
            ]);
        }

        return $this->json(['report' => $report->getFullAjaxData()]);
    }

    /**
     * Creates a new Report. Verification reports save their row counts into the target
     * inventory (and, if this is the most recent report and a cash difference is given, spin off
     * a cash-out UPDATE report). Update reports move counts between the given source/target
     * inventories.
     *
     * @throws Throwable
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    #[Route('/reports', methods: ['POST'])]
    public function create(
        EntityManagerInterface $em,
        ProductRepository $productQuery,
        ConventRepository $conventQuery,
        Request $request,
    ): JsonResponse
    {
        $this->requireUser();

        /** @var User $user */
        $user = $this->getUser();
        $memberConventId = $user->getMember()->getConventId();
        $conventId = (int)$request->request->get('conventId', $memberConventId);

        if ($conventId !== $memberConventId && !$this->isGranted(User::ROLE_SUPER_ADMIN)) {
            return JSendResponse::createFail('Tegevuseks pead olema super admin', 403);
        }

        Product::$activeConventId = $conventId;

        /** @var Convent|null $convent */
        $convent = $conventQuery->find($conventId);

        if (!$convent) {
            return JSendResponse::createFail('Koondist ei leitud', 404);
        }

        $type = $request->request->get('type', Report::TYPE_VERIFICATION);

        if (!in_array($type, Report::$types, true)) {
            return JSendResponse::createFail('Vigased parameetrid', 400, ['type' => 'Vigane raporti tüüp']);
        }

        if ($type === Report::TYPE_UPDATE && !$this->isGranted(User::ROLE_ADMIN)) {
            return JSendResponse::createFail('Vigased parameetrid', 403, ['type' => 'Sellist tüüpi raportit saab tekitada admin']);
        }

        $reportData = $request->request->all('Report');

        if (empty($reportData)) {
            return JSendResponse::createFail('Aruande salvestamine ebaõnnestus', 400);
        }

        $report = new Report();
        $report
            ->setConvent($convent)
            ->setMember($user->getMember())
            ->setType($type)
            ->setCash($reportData['cash'] ?? '0')
            ->setSource($reportData['source'] ?? null)
            ->setTarget($reportData['target'] ?? null)
        ;

        if ($report->isUpdate()) {
            if ($report->getSource() === null && $report->getTarget() === null) {
                return JSendResponse::createFail('Kust ja kuhu ei tohi olla mõlemad tühjad', 400);
            }

            if ($report->getSource() === $report->getTarget()) {
                return JSendResponse::createFail('Kust ja kuhu ei tohi olla samad', 400);
            }
        }

        foreach ($reportData['reportRows'] ?? [] as $rowData) {
            $product = isset($rowData['productId']) ? $productQuery->find($rowData['productId']) : null;

            if (!$product) {
                continue;
            }

            $reportRow = new ReportRow();
            $reportRow
                ->setProduct($product)
                ->setCount($rowData['count'] ?? '0')
            ;
            $report->addReportRow($reportRow);
            $reportRow->updateCurrentPrice();
        }

        $em->persist($report);

        if ($report->isUpdate()) {
            // Remove from source inventory (warehouse and, in some cases, storage)
            if ($report->getSource() !== null) {
                $this->saveProductCounts($report, $report->getSource(), 'reduce', $em);
            }

            // Add to target inventory (warehouse or storage)
            if ($report->getTarget() !== null) {
                $this->saveProductCounts($report, $report->getTarget(), 'add', $em);
            }
        } else {
            // A freshly created report is always "the latest" one
            $cashOut = (float)$request->request->get('cashOut', 0);

            if (abs($cashOut) > 0.001) {
                $updateReport = new Report();
                $updateReport
                    ->setConvent($convent)
                    ->setMember($user->getMember())
                    ->setType(Report::TYPE_UPDATE)
                    ->setTarget($report->getTarget() === Product::INVENTORY_TYPE_STORAGE ? Product::INVENTORY_TYPE_WAREHOUSE : null)
                    ->setSource($report->getTarget())
                    ->setCash((string)$cashOut)
                ;
                $em->persist($updateReport);
            }

            if ($report->getTarget() !== null) {
                $this->saveProductCounts($report, $report->getTarget(), 'set', $em);
            }
        }

        $em->flush();

        return $this->json(['report' => $report], 201);
    }

    /**
     * Applies the given Report's row counts to the target inventory (Product warehouse/storage
     * count), either replacing ('set'), adding to, or subtracting from the current count.
     *
     * @throws BadRequestHttpException
     */
    private function saveProductCounts(Report $report, string $inventoryType, string $action, EntityManagerInterface $em): void
    {
        if (!in_array($action, ['set', 'add', 'reduce'], true)) {
            throw new BadRequestHttpException('Invalid action for saveProductCounts: ' . $action);
        }

        foreach ($report->getReportRows() as $row) {
            $product = $row->getProduct();
            if ($product === null) {
                continue;
            }

            $productInfo = $product->getActiveProductInfo();
            $count = $row->getCount();

            switch (strtolower($inventoryType)) {
                case Product::INVENTORY_TYPE_WAREHOUSE:
                    $current = $productInfo->getWarehouseCount() ?? 0;
                    $productInfo->setWarehouseCount(match ($action) {
                        'add' => $current + $count,
                        'reduce' => $current - $count,
                        default => $count,
                    });
                    break;
                case Product::INVENTORY_TYPE_STORAGE:
                    $current = $productInfo->getStorageCount() ?? 0;
                    $productInfo->setStorageCount(match ($action) {
                        'add' => $current + $count,
                        'reduce' => $current - $count,
                        default => $count,
                    });
                    break;
                default:
                    throw new BadRequestHttpException('Invalid inventoryType: ' . $inventoryType);
            }

            $em->persist($productInfo);
        }
    }
}
