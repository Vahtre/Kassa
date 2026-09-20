<?php

namespace App\Repository;

use App\Entity\Report;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Report>
 * @method Report|null findOneBy(array $criteria, array|null $orderBy = null)
 */
class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    /**
     * Find the most recent verification report for the same convent and target, created before
     * the given report.
     */
    public function findPreviousVerificationReport(Report $report): ?Report
    {
        if ($report->getCreatedAt() === null) {
            return null;
        }

        return $this->createQueryBuilder('r')
            ->andWhere('r.type = :type')
            ->andWhere('r.conventId = :conventId')
            ->andWhere('r.target = :target')
            ->andWhere('r.createdAt < :createdAt')
            ->setParameter('type', Report::TYPE_VERIFICATION)
            ->setParameter('conventId', $report->getConventId())
            ->setParameter('target', $report->getTarget())
            ->setParameter('createdAt', $report->getCreatedAt())
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find the most recent verification report for the given convent and target.
     */
    public function findLatestVerificationReport(int $conventId, string $target): ?Report
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.type = :type')
            ->andWhere('r.conventId = :conventId')
            ->andWhere('r.target = :target')
            ->setParameter('type', Report::TYPE_VERIFICATION)
            ->setParameter('conventId', $conventId)
            ->setParameter('target', $target)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find the most recent verification report for the given convent/target, created before the
     * given date (as opposed to findPreviousVerificationReport(), which takes a reference Report).
     */
    public function findLatestVerificationReportBefore(int $conventId, string $target, \DateTimeInterface $date): ?Report
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.type = :type')
            ->andWhere('r.conventId = :conventId')
            ->andWhere('r.target = :target')
            ->andWhere('r.createdAt < :date')
            ->setParameter('type', Report::TYPE_VERIFICATION)
            ->setParameter('conventId', $conventId)
            ->setParameter('target', $target)
            ->setParameter('date', $date)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find UPDATE-type reports (deliveries/transfers between inventories) for the given convent,
     * created strictly after dateFrom and up to and including dateUntil.
     *
     * @return Report[]
     */
    public function findUpdateReportsBetween(int $conventId, ?\DateTimeInterface $dateFrom, ?\DateTimeInterface $dateUntil): array
    {
        $query = $this->createQueryBuilder('r')
            ->andWhere('r.type = :type')
            ->andWhere('r.conventId = :conventId')
            ->setParameter('type', Report::TYPE_UPDATE)
            ->setParameter('conventId', $conventId)
        ;

        if ($dateFrom !== null) {
            $query->andWhere('r.createdAt > :dateFrom')->setParameter('dateFrom', $dateFrom);
        }

        if ($dateUntil !== null) {
            $query->andWhere('r.createdAt <= :dateUntil')->setParameter('dateUntil', $dateUntil);
        }

        return $query->getQuery()->getResult();
    }
}
