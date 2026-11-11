<?php

declare(strict_types=1);

namespace App\Domain;

final class BookingPolicy
{
    public function assertDate(string $date, string $timezone, string $closing, \DateTimeImmutable $now): void
    {
        $local = $now->setTimezone(new \DateTimeZone($timezone));
        if (!in_array($date, [$local->format('Y-m-d'), $local->modify('+1 day')->format('Y-m-d')], true)) {
            throw new RuleViolation('Choose today or tomorrow.');
        }
        if ($date === $local->format('Y-m-d') && $local->format('H:i') >= $closing) {
            throw new RuleViolation('The office is already closed for today.');
        }
    }

    public function assertAssignment(?int $assignedDesk, int $desk, ?int $owner, int $user): void
    {
        if (null !== $assignedDesk && $assignedDesk !== $desk) {
            throw new RuleViolation('You can only book your assigned desk.');
        }
        if (null !== $owner && $owner !== $user) {
            throw new RuleViolation('This desk is permanently assigned to another person.');
        }
    }

    public function assertTransition(string $status, string $action, string $date, string $timezone, string $opening, string $closing, \DateTimeImmutable $now): string
    {
        $local = $now->setTimezone(new \DateTimeZone($timezone));
        if ('check-in' === $action && 'BOOKED' === $status) {
            if ($local->format('Y-m-d') !== $date || $local->format('H:i') < $opening || $local->format('H:i') >= $closing) {
                throw new RuleViolation('Check in on your reserved day during office hours.');
            }

            return 'CHECKED_IN';
        }
        if ('check-out' === $action && 'CHECKED_IN' === $status) {
            return 'CHECKED_OUT';
        }
        if ('cancel' === $action && 'BOOKED' === $status) {
            if ($date < $local->format('Y-m-d') || ($date === $local->format('Y-m-d') && $local->format('H:i') >= $closing)) {
                throw new RuleViolation('This reservation has already ended.');
            }

            return 'CANCELLED';
        }
        throw new RuleViolation('This action is not available for the reservation’s current status.');
    }
}
