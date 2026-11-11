<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\BookingPolicy;
use App\Domain\RuleViolation;
use PHPUnit\Framework\TestCase;

final class BookingPolicyTest extends TestCase
{
    public function testTodayCanBeBookedBeforeClosing(): void
    {
        $policy = new BookingPolicy();
        $policy->assertDate('2026-11-11', 'Europe/Warsaw', '18:00', new \DateTimeImmutable('2026-11-11T10:00:00Z'));
        self::addToAssertionCount(1);
    }

    public function testTomorrowCanBeBooked(): void
    {
        (new BookingPolicy())->assertDate('2026-11-12', 'Europe/Warsaw', '18:00', new \DateTimeImmutable('2026-11-11T22:00:00Z'));
        self::addToAssertionCount(1);
    }

    public function testDatesBeyondTomorrowAreRejected(): void
    {
        $this->expectException(RuleViolation::class);
        (new BookingPolicy())->assertDate('2026-11-13', 'Europe/Warsaw', '18:00', new \DateTimeImmutable('2026-11-11T10:00:00Z'));
    }

    public function testTodayCannotBeBookedAfterClosing(): void
    {
        $this->expectException(RuleViolation::class);
        (new BookingPolicy())->assertDate('2026-11-11', 'Europe/Warsaw', '18:00', new \DateTimeImmutable('2026-11-11T17:00:00Z'));
    }

    public function testAssignedUserCannotBookAnotherDesk(): void
    {
        $this->expectException(RuleViolation::class);
        (new BookingPolicy())->assertAssignment(7, 8, null, 2);
    }

    public function testDeskAssignedToAnotherUserCannotBeBooked(): void
    {
        $this->expectException(RuleViolation::class);
        (new BookingPolicy())->assertAssignment(null, 8, 3, 2);
    }

    public function testOwnerCanBookAssignedDesk(): void
    {
        (new BookingPolicy())->assertAssignment(8, 8, 2, 2);
        self::addToAssertionCount(1);
    }

    public function testCheckinRequiresBookingDateAndOfficeHours(): void
    {
        $this->expectException(RuleViolation::class);
        (new BookingPolicy())->assertTransition('BOOKED', 'check-in', '2026-11-12', 'Europe/Warsaw', '08:00', '18:00', new \DateTimeImmutable('2026-11-11T10:00:00Z'));
    }

    public function testCheckedInBookingMustBeCheckedOutRatherThanCancelled(): void
    {
        $this->expectException(RuleViolation::class);
        (new BookingPolicy())->assertTransition('CHECKED_IN', 'cancel', '2026-11-11', 'Europe/Warsaw', '08:00', '18:00', new \DateTimeImmutable('2026-11-11T10:00:00Z'));
    }

    public function testValidAttendanceTransitions(): void
    {
        $policy = new BookingPolicy();
        $now = new \DateTimeImmutable('2026-11-11T10:00:00Z');
        self::assertSame('CHECKED_IN', $policy->assertTransition('BOOKED', 'check-in', '2026-11-11', 'Europe/Warsaw', '08:00', '18:00', $now));
        self::assertSame('CHECKED_OUT', $policy->assertTransition('CHECKED_IN', 'check-out', '2026-11-11', 'Europe/Warsaw', '08:00', '18:00', $now));
    }

    public function testCheckoutBeforeCheckinIsRejected(): void
    {
        $this->expectException(RuleViolation::class);
        (new BookingPolicy())->assertTransition('BOOKED', 'check-out', '2026-11-11', 'Europe/Warsaw', '08:00', '18:00', new \DateTimeImmutable('2026-11-11T10:00:00Z'));
    }

    public function testTimezoneDeterminesToday(): void
    {
        (new BookingPolicy())->assertDate('2026-11-12', 'Europe/Warsaw', '18:00', new \DateTimeImmutable('2026-11-11T23:30:00Z'));
        self::addToAssertionCount(1);
    }
}
