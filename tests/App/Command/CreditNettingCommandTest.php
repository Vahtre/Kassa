<?php

namespace Tests\App\Command;

use App\Command\CreditNettingCommand;
use App\Entity\CreditNetting;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Helpers\EntityManagerAwareTestCase;

#[CoversClass(CreditNettingCommand::class)]
class CreditNettingCommandTest extends KernelTestCase
{
    use EntityManagerAwareTestCase;
    use RefreshDatabaseTrait;

    public function testExecute(): void
    {
        /** @var CreditNettingCommand $command */
        $command = self::getContainer()->get(CreditNettingCommand::class);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());

        $this->entityManager->clear();
        $creditNettings = $this->entityManager->getRepository(CreditNetting::class)->findAll();

        // One from fixtures, one freshly generated
        $this->assertCount(2, $creditNettings);

        foreach ($creditNettings as $creditNetting) {
            $this->assertCount(2, $creditNetting->getCreditNettingRows());

            $sum = 0;
            foreach ($creditNetting->getCreditNettingRows() as $row) {
                $sum += $row->getSum();
            }

            // Incoming/outgoing credit always nets to zero across all convents
            $this->assertEqualsWithDelta(0, $sum, 0.001);
        }
    }
}
