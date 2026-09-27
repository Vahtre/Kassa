<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 * @method Product|null findOneBy(array $criteria, array|null $orderBy = null)
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Order the given query by the sequence number set for the given convent, falling back to
     * the product's base sequence number when no convent-specific ProductInfo row exists yet.
     *
     * @param QueryBuilder $query
     * @param int $conventId
     * @return QueryBuilder
     */
    public function orderBySeqForConvent(QueryBuilder $query, int $conventId): QueryBuilder
    {
        return $query
            ->addSelect('COALESCE(seqInfo.seq, p.seq) AS HIDDEN sortSeq')
            ->leftJoin('p.productInfos', 'seqInfo', Join::WITH, 'seqInfo.conventId = :seqConventId')
            ->setParameter('seqConventId', $conventId)
            ->addOrderBy('sortSeq', 'ASC')
        ;
    }
}
