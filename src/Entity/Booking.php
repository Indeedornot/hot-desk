<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public Desk $desk;

    #[ORM\Column(type: 'date_immutable')]
    public \DateTimeImmutable $date;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public \DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public \DateTimeImmutable $endsAt;

    #[ORM\Column(length: 20)]
    public string $status = 'BOOKED';

    #[ORM\Column(type: 'datetimetz_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    public ?\DateTimeImmutable $checkedInAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    public ?\DateTimeImmutable $checkedOutAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    public ?\DateTimeImmutable $cancelledAt = null;
}
