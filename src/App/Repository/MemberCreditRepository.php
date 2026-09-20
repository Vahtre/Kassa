<?php

namespace App\Repository;

use App\Entity\Convent;
use App\Entity\Member;
use App\Entity\MemberCredit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberCredit>
 */
class MemberCreditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MemberCredit::class);
    }

    /**
     * Finds the Member's MemberCredit row for the given Convent, or returns a new, unpersisted
     * one (starting at 0 credit) if none exists yet. Callers must persist() the result if new.
     */
    public function findOrCreateForMemberAndConvent(Member $member, Convent $convent): MemberCredit
    {
        $credit = $this->findOneBy(['member' => $member, 'convent' => $convent]);

        if ($credit !== null) {
            return $credit;
        }

        return (new MemberCredit())
            ->setMember($member)
            ->setConvent($convent)
            ->setCredit('0')
        ;
    }

    /**
     * Sum of credit per member, across ALL of that member's MemberCredit rows regardless of
     * convent (not just active convents).
     *
     * @return array<int, float> memberId => total credit
     */
    public function sumCreditByMember(): array
    {
        $rows = $this->createQueryBuilder('mc')
            ->select('IDENTITY(mc.member) as memberId, SUM(mc.credit) as total')
            ->groupBy('mc.member')
            ->getQuery()
            ->getArrayResult()
        ;

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['memberId']] = (float)$row['total'];
        }

        return $result;
    }

    /**
     * For members whose home convent is active: the sum of credit they hold at OTHER convents
     * than their home one, grouped by their home convent ("incoming" from that convent's view).
     *
     * @param int[] $activeConventIds
     * @return array<int, float> conventId => total
     */
    public function sumIncomingByConvent(array $activeConventIds): array
    {
        $rows = $this->createQueryBuilder('mc')
            ->select('m.conventId as conventId, SUM(mc.credit) as total')
            ->join('mc.member', 'm')
            ->andWhere('m.conventId IN (:conventIds)')
            ->andWhere('mc.convent <> m.convent')
            ->setParameter('conventIds', $activeConventIds)
            ->groupBy('m.conventId')
            ->getQuery()
            ->getArrayResult()
        ;

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['conventId']] = (float)$row['total'];
        }

        return $result;
    }

    /**
     * For members whose home convent is active: the sum of credit they hold at OTHER convents
     * than their home one, grouped by the convent where that credit actually sits ("outgoing"
     * from that convent's view).
     *
     * @param int[] $activeConventIds
     * @return array<int, float> conventId => total
     */
    public function sumOutgoingByConvent(array $activeConventIds): array
    {
        $rows = $this->createQueryBuilder('mc')
            ->select('IDENTITY(mc.convent) as conventId, SUM(mc.credit) as total')
            ->join('mc.member', 'm')
            ->andWhere('m.conventId IN (:conventIds)')
            ->andWhere('mc.convent <> m.convent')
            ->setParameter('conventIds', $activeConventIds)
            ->groupBy('mc.convent')
            ->getQuery()
            ->getArrayResult()
        ;

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['conventId']] = (float)$row['total'];
        }

        return $result;
    }

    /**
     * Deletes all MemberCredit rows belonging to the given members.
     */
    public function deleteByMemberIds(array $memberIds): int
    {
        if (empty($memberIds)) {
            return 0;
        }

        return $this->createQueryBuilder('mc')
            ->delete()
            ->andWhere('mc.member IN (:memberIds)')
            ->setParameter('memberIds', $memberIds)
            ->getQuery()
            ->execute()
        ;
    }
}
