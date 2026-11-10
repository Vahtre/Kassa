<?php

namespace Tests\App\Entity;

use App\Entity\Convent;
use App\Entity\PointOfSale;
use App\Entity\Report;
use Hautelook\AliceBundle\PhpUnit\FixtureStore;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Helpers\EntityManagerAwareTestCase;

/**
 * Entities loaded from the database must report the convent from their relation, not a property default.
 */
#[CoversClass(Report::class)]
#[CoversClass(PointOfSale::class)]
class ConventIdTest extends KernelTestCase
{
    use EntityManagerAwareTestCase;
    use RefreshDatabaseTrait;

    public function testLoadedReportUsesConventRelation(): void
    {
        /** @var Convent $tartu */
        $tartu = $this->entityManager->find(Convent::class, FixtureStore::getFixtures()['Convent_7']->getId());
        $report = (new Report())
            ->setConvent($tartu)
            ->setName('Test')
            ->setCash('1.00')
            ->setType(Report::TYPE_VERIFICATION)
            ->setTarget('storage');
        $this->entityManager->persist($report);
        $this->entityManager->flush();
        $id = $report->getId();
        $this->entityManager->clear();

        $loaded = $this->entityManager->find(Report::class, $id);

        $this->assertSame($tartu->getId(), $loaded->getConventId());
    }

    public function testLoadedPointOfSaleUsesConventRelation(): void
    {
        $fixture = FixtureStore::getFixtures()['PointOfSale_2'];
        $this->entityManager->clear();

        $loaded = $this->entityManager->find(PointOfSale::class, $fixture->getId());

        $this->assertSame(FixtureStore::getFixtures()['Convent_7']->getId(), $loaded->getConventId());
    }
}
