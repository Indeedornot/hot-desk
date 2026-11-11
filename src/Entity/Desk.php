<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'desk_office_label', columns: ['office_id', 'label'])]
class Desk
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public Office $office;

    #[ORM\Column(length: 40)]
    public string $label = '';

    #[ORM\Column(length: 255)]
    public string $equipment = '';

    #[ORM\Column]
    public int $x = 0;

    #[ORM\Column]
    public int $y = 0;

    #[ORM\Column]
    public bool $active = true;
}
