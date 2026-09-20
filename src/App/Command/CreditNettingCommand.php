<?php

namespace App\Command;

use App\Entity\CreditNetting;
use App\Entity\CreditNettingRow;
use App\Entity\Member;
use App\Entity\MemberCredit;
use App\Repository\ConventRepository;
use App\Repository\MemberCreditRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Rebalances member credit across convents and records a CreditNetting snapshot. Ported from the
 * old Propel Rotalia\APIBundle\Command\CreditNettingCommand.
 *
 * For every member whose home convent has kassa active, consolidates their scattered
 * MemberCredit rows (credit they hold at convents other than their home one, e.g. from buying
 * at another convent's kassa) into a single row at their home convent, and records how much each
 * convent owes/is owed as a result.
 */
#[AsCommand(name: 'app:credit-netting', description: 'Runs credit netting')]
class CreditNettingCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConventRepository $conventQuery,
        private readonly MemberRepository $memberQuery,
        private readonly MemberCreditRepository $memberCreditQuery,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Rebalances credits between convents and calculates nettings.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            $activeConventIds = array_map(
                static fn ($convent) => $convent->getId(),
                $this->conventQuery->getActiveConvents(),
            );

            $output->writeln(sprintf('Number of convents with active kassa: %d', count($activeConventIds)));

            $memberIdsAtActiveConvents = $this->memberQuery->findIdsByConventIds($activeConventIds);

            // Sum the credit for all members, across all their MemberCredit rows
            $memberCreditsByMemberId = $this->memberCreditQuery->sumCreditByMember();
            $output->writeln(sprintf('Number of members with non-zero credit: %d', count($memberCreditsByMemberId)));

            // Incoming/outgoing credit for each active convent
            $memberCreditsByConventIdIn = $this->memberCreditQuery->sumIncomingByConvent($activeConventIds);
            $output->writeln('Incoming credits:');
            foreach ($memberCreditsByConventIdIn as $conventId => $sum) {
                $convent = $this->conventQuery->find($conventId);
                $output->writeln(sprintf('%s: %.2f', $convent?->getName() ?? $conventId, $sum));
            }

            $memberCreditsByConventIdOut = $this->memberCreditQuery->sumOutgoingByConvent($activeConventIds);
            $output->writeln('Outgoing credits:');
            foreach ($memberCreditsByConventIdOut as $conventId => $sum) {
                $convent = $this->conventQuery->find($conventId);
                $output->writeln(sprintf('%s: %.2f', $convent?->getName() ?? $conventId, $sum));
            }

            // Redistribute the credits
            $this->memberCreditQuery->deleteByMemberIds($memberIdsAtActiveConvents);

            $output->writeln('Members whose credit is not moved:');
            foreach ($memberCreditsByMemberId as $memberId => $credit) {
                /** @var Member|null $member */
                $member = $this->memberQuery->find($memberId);

                if ($member === null) {
                    continue;
                }

                if (in_array($member->getConventId(), $activeConventIds, true)) {
                    $memberCredit = (new MemberCredit())
                        ->setMember($member)
                        ->setConvent($member->getConvent())
                        ->setCredit((string)$credit)
                    ;
                    $this->em->persist($memberCredit);
                } else {
                    $output->writeln($member->getFullName());
                }
            }
            $output->writeln('Credit redistributed!');

            // Insert Credit netting
            $creditNetting = new CreditNetting();
            $this->em->persist($creditNetting);

            $conventIds = array_unique(array_merge(
                array_keys($memberCreditsByConventIdIn),
                array_keys($memberCreditsByConventIdOut),
            ));

            foreach ($conventIds as $conventId) {
                $convent = $this->conventQuery->find($conventId);
                if ($convent === null) {
                    continue;
                }

                $creditIn = $memberCreditsByConventIdIn[$conventId] ?? 0.0;
                $creditOut = $memberCreditsByConventIdOut[$conventId] ?? 0.0;
                $sum = $creditIn - $creditOut;

                $creditNettingRow = new CreditNettingRow();
                $creditNettingRow
                    ->setCreditNetting($creditNetting)
                    ->setConvent($convent)
                    ->setSum((string)$sum)
                ;
                if ($sum === 0.0) {
                    $creditNettingRow->setNettingDone(true);
                }
                $this->em->persist($creditNettingRow);
            }

            $output->writeln('Credit netting inserted!');

            $this->em->flush();
            $connection->commit();
            $output->writeln('Success!');
        } catch (\Throwable $e) {
            $output->writeln('Caught an exception:');
            $output->writeln($e->getMessage());
            $output->writeln('Rolling back!');
            $connection->rollBack();
            throw $e;
        }

        $output->writeln('All done!');

        return Command::SUCCESS;
    }
}
