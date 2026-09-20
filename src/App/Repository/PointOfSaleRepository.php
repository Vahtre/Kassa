<?php

namespace App\Repository;

use App\Entity\PointOfSale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PointOfSale>
 * @method PointOfSale|null findOneBy(array $criteria, array|null $orderBy = null)
 */
class PointOfSaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PointOfSale::class);
    }
}
