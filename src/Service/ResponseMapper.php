<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditEvent;
use App\Entity\Booking;
use App\Entity\Desk;
use App\Entity\Office;
use App\Entity\SeatAssignment;
use App\Entity\User;

final class ResponseMapper
{
    public static function user(User $u): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'active' => $u->active];
    }

    public static function office(Office $o): array
    {
        return ['id' => $o->id, 'name' => $o->name, 'address' => $o->address, 'timezone' => $o->timezone, 'openingTime' => $o->openingTime, 'closingTime' => $o->closingTime, 'active' => $o->active];
    }

    public static function desk(Desk $d): array
    {
        return ['id' => $d->id, 'officeId' => $d->office->id, 'officeName' => $d->office->name, 'label' => $d->label, 'equipment' => $d->equipment, 'x' => $d->x, 'y' => $d->y, 'active' => $d->active];
    }

    public static function booking(Booking $b): array
    {
        $now = new \DateTimeImmutable();

        return ['id' => $b->id, 'userId' => $b->user->id, 'userName' => $b->user->name, 'deskId' => $b->desk->id, 'deskLabel' => $b->desk->label, 'officeName' => $b->desk->office->name, 'officeId' => $b->desk->office->id, 'timezone' => $b->desk->office->timezone, 'date' => $b->date->format('Y-m-d'), 'status' => $b->status, 'elapsed' => $b->endsAt <= $now, 'createdAt' => $b->createdAt->format(DATE_ATOM), 'checkedInAt' => $b->checkedInAt?->format(DATE_ATOM), 'checkedOutAt' => $b->checkedOutAt?->format(DATE_ATOM), 'cancelledAt' => $b->cancelledAt?->format(DATE_ATOM)];
    }

    public static function assignment(SeatAssignment $a): array
    {
        return ['id' => $a->id, 'userId' => $a->user->id, 'userName' => $a->user->name, 'deskId' => $a->desk->id, 'deskLabel' => $a->desk->label, 'officeName' => $a->desk->office->name, 'createdAt' => $a->createdAt->format(DATE_ATOM)];
    }

    public static function event(AuditEvent $e): array
    {
        return ['id' => $e->id, 'actorId' => $e->actor->id, 'actorName' => $e->actor->name, 'event_name' => $e->eventName, 'event_date' => $e->eventDate->format(DATE_ATOM), 'event_type' => $e->eventType, 'event_data' => $e->eventData];
    }
}
