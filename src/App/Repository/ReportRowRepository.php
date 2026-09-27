<?php

namespace App\Repository;

use App\Entity\Report;
use App\Entity\ReportRow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReportRow>
 */
class ReportRowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportRow::class);
    }

    /**
     * Find rows of "incoming delivery" UPDATE reports (source is null: stock arriving from
     * outside the tracked inventories) for the given product/convent, up to and including
     * dateUntil, most recent first. Used to compute the average incoming price of a product.
     *
     * @return ReportRow[]
     */
    public function findIncomingRowsForProduct(int $productId, int $conventId, ?\DateTimeInterface $dateUntil): array
    {
        $query = $this->createQueryBuilder('rr')
            ->join('rr.report', 'r')
            ->andWhere('rr.product = :productId')
            ->andWhere('r.type = :type')
            ->andWhere('IDENTITY(r.convent) = :conventId')
            ->andWhere('r.source IS NULL')
            ->setParameter('productId', $productId)
            ->setParameter('type', Report::TYPE_UPDATE)
            ->setParameter('conventId', $conventId)
            ->orderBy('r.createdAt', 'DESC')
        ;

        if ($dateUntil !== null) {
            $query->andWhere('r.createdAt <= :dateUntil')->setParameter('dateUntil', $dateUntil);
        }

        return $query->getQuery()->getResult();
    }
}
