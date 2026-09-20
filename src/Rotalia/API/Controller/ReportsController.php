<?php

namespace Rotalia\API\Controller;

use App\Entity\Convent;
use App\Entity\Product;
use App\Entity\Report;
use App\Entity\ReportRow;
use App\Entity\User;
use App\Repository\ConventRepository;
use App\Repository\ProductRepository;
use App\Repository\ReportRepository;
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
 * TODO: this is a partial port. Report creation here does not yet reproduce the old Propel
 * behaviour's side effects: it does not write ReportRow counts back into Product warehouse/
 * storage counts (Report::saveProductCounts), does not auto-create a cash-out UPDATE report,
 * and does not compute profit/deficit (which needs the old Propel Updates class ported - see
 * Rotalia\APIBundle\Classes\Updates on the master branch / pre-Doctrine git history).
 * See App\Entity\Report's docblocks for the exact gaps. Economy reports are not ported at all yet.
 */
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

            $report = $reportQuery->findLatestVerificationReport($conventId, $target);

            // TODO: 'updates' (inventory delta since this report) requires the Updates class port
            return $this->json([
                'report' => $report?->getFullAjaxData(),
                'updates' => null,
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
            $report->setPreviousVerification($reportQuery->findPreviousVerificationReport($report));

            // TODO: 'updates' requires the Updates class port
            return $this->json([
                'report' => $report->getPartialAjaxData(),
                'updates' => null,
            ]);
        }

        return $this->json(['report' => $report->getFullAjaxData()]);
    }

    /**
     * Creates a new Report. Does not yet reproduce inventory/cash side effects - see class docblock.
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
        $em->flush();

        return $this->json(['report' => $report], 201);
    }
}
