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
}
