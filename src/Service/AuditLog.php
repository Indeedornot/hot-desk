<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditEvent;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class AuditLog
{
    public function __construct(private EntityManagerInterface $em, private ClockInterface $clock)
    {
    }

    public function record(User $actor, string $name, string $type, array $data): string
    {
        $eventId = bin2hex(random_bytes(16));
        $event = new AuditEvent();
        $event->actor = $actor;
        $event->eventName = $name;
        $event->eventType = $type;
        $event->eventDate = $this->clock->now();
        $event->eventData = $data + ['eventId' => $eventId, 'source' => 'manual'];
        $this->em->persist($event);

        return $eventId;
    }
}
