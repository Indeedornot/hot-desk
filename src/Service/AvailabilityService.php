<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\BookingPolicy;
use App\Domain\RuleViolation;
use App\Entity\Desk;
use App\Entity\Office;
use App\Entity\User;
use App\Repository\WorkspaceRepository;
use Psr\Clock\ClockInterface;

final class AvailabilityService
{
    public function __construct(private WorkspaceRepository $repo, private BookingPolicy $policy, private ClockInterface $clock)
    {
    }

    public function desks(User $user, int $officeId, string $date, string $search): array
    {
        $office = $this->repo->get(Office::class, $officeId);
        if (!$office->active) {
            throw new RuleViolation('This office is disabled.', 404);
        }
        $local = $this->clock->now()->setTimezone(new \DateTimeZone($office->timezone));
        if (!in_array($date, [$local->format('Y-m-d'), $local->modify('+1 day')->format('Y-m-d')], true)) {
            throw new RuleViolation('Choose today or tomorrow.', 422);
        }
        $assigned = $this->repo->assignmentForUser($user);
        $items = [];
        $userBooking = null;
        foreach ($this->repo->currentBookings() as $booking) {
            $start = new \DateTimeImmutable($date.' '.$office->openingTime, new \DateTimeZone($office->timezone));
            $end = new \DateTimeImmutable($date.' '.$office->closingTime, new \DateTimeZone($office->timezone));
            if ($booking->user->id === $user->id && $booking->startsAt < $end && $booking->endsAt > $start) {
                $userBooking = $booking;
            }
        }
        foreach ($this->repo->all(Desk::class) as $desk) {
            if ($desk->office->id !== $officeId || !$desk->active || ('' !== $search && !str_contains(mb_strtolower($desk->label.' '.$desk->equipment), mb_strtolower($search)))) {
                continue;
            }
            $booking = $this->repo->activeForDay($desk, $date);
            $owner = $this->repo->assignmentForDesk($desk);
            $reason = null;
            try {
                $this->policy->assertDate($date, $office->timezone, $office->closingTime, $this->clock->now());
                $this->policy->assertAssignment($assigned?->desk->id, $desk->id, $owner?->user->id, $user->id);
                if ($booking || $userBooking) {
                    throw new RuleViolation($booking ? 'Reserved for this day.' : 'You already have a reservation for this day.');
                }
            } catch (RuleViolation $error) {
                $reason = $error->getMessage();
            }
            $items[] = ResponseMapper::desk($desk) + ['available' => null === $reason, 'reason' => $reason, 'state' => $booking ? ('CHECKED_IN' === $booking->status ? 'occupied' : 'reserved') : ($owner ? 'assigned' : 'available'), 'isAssignedToMe' => $owner?->user->id === $user->id, 'myBookingId' => $booking?->user->id === $user->id ? $booking->id : null];
        }

        return ['items' => $items, 'assignedSeat' => $assigned ? ResponseMapper::assignment($assigned) : null, 'office' => ResponseMapper::office($office), 'date' => $date];
    }
}
