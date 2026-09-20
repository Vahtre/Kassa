<?php

namespace App\Repository;

use App\Entity\GuardDuty;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GuardDuty>
 */
class GuardDutyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GuardDuty::class);
    }

    /**
     * The members who should be on guard duty for the given convent on the given date, across
     * all cycles active that day.
     *
     * @return \App\Entity\Member[]
     */
    public function findMembersOnGuardDutyForDate(int $conventId, \DateTimeInterface $date): array
    {
        /** @var GuardDuty[] $guardDuties */
        $guardDuties = $this->createQueryBuilder('gd')
            ->join('gd.guardDutyCycle', 'c')
            ->andWhere('gd.date = :date')
            ->andWhere('c.conventId = :conventId')
            ->setParameter('date', $date)
            ->setParameter('conventId', $conventId)
            ->getQuery()
            ->getResult()
        ;

        $membersById = [];

        foreach ($guardDuties as $guardDuty) {
            $member = $guardDuty->getMember();
            // Sometimes member ID is 0 in legacy data, avoid an error
            if ($member === null || !$member->getId()) {
                continue;
            }

            // Avoid duplicate members when in 2 duty cycles at the same time
            $membersById[$member->getId()] = $member;
        }

        return array_values($membersById);
    }
}
