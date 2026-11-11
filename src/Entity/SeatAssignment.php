<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class SeatAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    public User $user;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    public Desk $desk;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $assignedBy;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public \DateTimeImmutable $createdAt;
}
