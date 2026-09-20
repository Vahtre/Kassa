<?php

namespace App\Repository;

use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Member>
 */
class MemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Member::class);
    }

    /**
     * @param int[] $conventIds
     * @return int[]
     */
    public function findIdsByConventIds(array $conventIds): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('m.id')
            ->andWhere('m.conventId IN (:conventIds)')
            ->setParameter('conventIds', $conventIds)
            ->getQuery()
            ->getScalarResult()
        ;

        return array_map(static fn (array $row) => (int)$row['id'], $rows);
    }
}
