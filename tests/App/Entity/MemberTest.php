<?php

namespace Tests\App\Entity;

use App\Entity\Member;
use App\Entity\Report;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Helpers\EntityManagerAwareTestCase;

#[CoversClass(Member::class)]
class MemberTest extends KernelTestCase
{
    use EntityManagerAwareTestCase;

    public function testDisplayNameOfMissingMemberFallsBackToId(): void
    {
        $missing = $this->entityManager->getReference(Member::class, 999999);

        $this->assertNull(Member::fullNameOf($missing));
        $this->assertSame('Tundmatu liige #999999', Member::displayNameOf($missing));
    }

    public function testDisplayNameOfExistingAndNullMember(): void
    {
        $member = (new Member())->setFirstName('Mari')->setLastName('Maasikas');

        $this->assertSame('Mari Maasikas', Member::displayNameOf($member));
        $this->assertNull(Member::displayNameOf(null));
    }

    public function testReportMemberNameFallsBackToStoredName(): void
    {
        $report = (new Report())->setName('Stored Name');
        $reflection = new \ReflectionProperty(Report::class, 'member');
        $reflection->setValue($report, $this->entityManager->getReference(Member::class, 999999));

        $this->assertSame('Stored Name', $report->getMemberName());
    }
}
