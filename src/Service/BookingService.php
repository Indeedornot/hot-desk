<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\BookingPolicy;
use App\Domain\RuleViolation;
use App\Dto\BookingInput;
use App\Entity\Booking;
use App\Entity\Desk;
use App\Entity\User;
use App\Message\BookingNotification;
use App\Repository\WorkspaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class BookingService
{
    public function __construct(private EntityManagerInterface $em, private WorkspaceRepository $repo, private BookingPolicy $policy, private Mutation $mutation, private AuditLog $audit, private MessageBusInterface $bus, private ClockInterface $clock)
    {
    }

    public function save(User $actor, BookingInput $input, ?int $id = null): Booking
    {
        return $this->mutation->run(function () use ($actor, $input, $id) {
            $this->em->refresh($actor);
            $booking = $id ? $this->repo->get(Booking::class, $id) : new Booking();
            if ($id) {
                $this->authorize($actor, $booking);
                if ('BOOKED' !== $booking->status || $booking->endsAt <= $this->clock->now()) {
                    throw new RuleViolation('Only upcoming reservations can be edited.');
                }
            }
            $user = $id ? $booking->user : $actor;
            $desk = $this->repo->get(Desk::class, $input->deskId);
            $office = $desk->office;
            if (!$user->active || !$desk->active || !$office->active) {
                throw new RuleViolation('This person, desk or office is disabled.');
            }
            $this->policy->assertDate($input->date, $office->timezone, $office->closingTime, $this->clock->now());
            $assignment = $this->repo->assignmentForUser($user);
            $owner = $this->repo->assignmentForDesk($desk);
            $this->policy->assertAssignment($assignment?->desk->id, $desk->id, $owner?->user->id, $user->id);
            $zone = new \DateTimeZone($office->timezone);
            $start = new \DateTimeImmutable($input->date.' '.$office->openingTime, $zone);
            $end = new \DateTimeImmutable($input->date.' '.$office->closingTime, $zone);
            foreach ($this->repo->currentBookings() as $existing) {
                if ($existing->id === $id) {
                    continue;
                }
                if (($existing->desk->id === $desk->id && $existing->date->format('Y-m-d') === $input->date) || ($existing->user->id === $user->id && $existing->startsAt < $end && $existing->endsAt > $start)) {
                    throw new RuleViolation('This desk or person already has a reservation for that day.');
                }
            }
            $previous = $id ? ['deskId' => $booking->desk->id, 'date' => $booking->date->format('Y-m-d')] : null;
            $booking->user = $user;
            $booking->desk = $desk;
            $booking->date = new \DateTimeImmutable($input->date);
            $booking->startsAt = $start;
            $booking->endsAt = $end;
            if (!$id) {
                $booking->createdAt = $this->clock->now();
                $this->em->persist($booking);
            }
            $this->em->flush();
            $this->record($actor, $booking, $id ? 'updated' : 'created', ['previous' => $previous]);

            return $booking;
        });
    }

    public function transition(User $actor, int $id, string $action): Booking
    {
        return $this->mutation->run(function () use ($actor, $id, $action) {
            $booking = $this->repo->get(Booking::class, $id);
            $this->authorize($actor, $booking);
            $office = $booking->desk->office;
            $previous = $booking->status;
            $booking->status = $this->policy->assertTransition($booking->status, $action, $booking->date->format('Y-m-d'), $office->timezone, $office->openingTime, $office->closingTime, $this->clock->now());
            $field = match ($action) {
                'check-in' => 'checkedInAt', 'check-out' => 'checkedOutAt', 'cancel' => 'cancelledAt'
            };
            $booking->$field = $this->clock->now();
            $name = match ($action) {
                'check-in' => 'checked_in', 'check-out' => 'checked_out', 'cancel' => 'cancelled'
            };
            $this->record($actor, $booking, $name, ['previousStatus' => $previous]);

            return $booking;
        });
    }

    private function authorize(User $actor, Booking $booking): void
    {
        if ($actor->id !== $booking->user->id && 'ROLE_ADMIN' !== $actor->role) {
            throw new RuleViolation('You cannot manage another person’s reservation.', 403);
        }
    }

    private function record(User $actor, Booking $booking, string $action, array $extra = []): void
    {
        $eventId = $this->audit->record($actor, 'booking.'.$action, in_array($action, ['checked_in', 'checked_out'], true) ? 'attendance' : 'booking', ['bookingId' => $booking->id, 'userId' => $booking->user->id, 'deskId' => $booking->desk->id, 'deskLabel' => $booking->desk->label, 'officeId' => $booking->desk->office->id, 'officeName' => $booking->desk->office->name, 'date' => $booking->date->format('Y-m-d'), 'status' => $booking->status] + $extra);
        $this->bus->dispatch(new BookingNotification($eventId, $booking->user->email, $booking->user->name, $action, $booking->desk->label, $booking->desk->office->name, $booking->date->format('Y-m-d')));
    }
}
