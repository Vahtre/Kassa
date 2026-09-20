<?php

namespace App\Repository;

use App\Entity\CreditNettingRow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CreditNettingRow>
 */
class CreditNettingRowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreditNettingRow::class);
    }
}
