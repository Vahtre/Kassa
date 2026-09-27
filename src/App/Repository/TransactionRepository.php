<?php

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Find CREDIT_PURCHASE transactions for the given convent, created strictly after dateFrom
     * and strictly before dateUntil.
     *
     * @return Transaction[]
     */
    public function findTransactionsBetween(int $conventId, ?\DateTimeInterface $dateFrom, ?\DateTimeInterface $dateUntil): array
    {
        $query = $this->createQueryBuilder('t')
            ->andWhere('t.type = :type')
            ->andWhere('IDENTITY(t.convent) = :conventId')
            ->setParameter('type', Transaction::TYPE_CREDIT_PURCHASE)
            ->setParameter('conventId', $conventId)
        ;

        if ($dateFrom !== null) {
            $query->andWhere('t.createdAt > :dateFrom')->setParameter('dateFrom', $dateFrom);
        }

        if ($dateUntil !== null) {
            $query->andWhere('t.createdAt < :dateUntil')->setParameter('dateUntil', $dateUntil);
        }

        return $query->getQuery()->getResult();
    }
}
