<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AuditEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $actor;

    #[ORM\Column(length: 80)]
    public string $eventName = '';

    #[ORM\Column(type: 'datetimetz_immutable')]
    public \DateTimeImmutable $eventDate;

    #[ORM\Column(length: 30)]
    public string $eventType = '';

    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    public array $eventData = [];
}
