<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    /**
     * Finds the convent's current cash register total setting, creating it (at 0) if missing.
     * If newly created, it is persisted immediately since Setting::value has no natural default.
     */
    public function getCurrentCashSetting(int $conventId): Setting
    {
        $setting = $this->findOneBy([
            'object' => Setting::OBJECT_CONVENT,
            'objectId' => $conventId,
            'reference' => Setting::REFERENCE_CURRENT_CASH,
        ]);

        if ($setting !== null) {
            return $setting;
        }

        $setting = (new Setting())
            ->setObject(Setting::OBJECT_CONVENT)
            ->setObjectId($conventId)
            ->setReference(Setting::REFERENCE_CURRENT_CASH)
            ->setValue('0')
        ;

        $em = $this->getEntityManager();
        $em->persist($setting);
        $em->flush();

        return $setting;
    }

    /**
     * @return Setting[] Returns an array of Setting objects
     */
    public function findByObject(string $object): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.object = :object')
            ->setParameter('object', $object)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
