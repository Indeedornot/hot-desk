<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Office
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 100)]
    public string $name = '';

    #[ORM\Column(length: 255)]
    public string $address = '';

    #[ORM\Column(length: 60)]
    public string $timezone = 'Europe/Warsaw';

    #[ORM\Column(length: 5)]
    public string $openingTime = '08:00';

    #[ORM\Column(length: 5)]
    public string $closingTime = '18:00';

    #[ORM\Column]
    public bool $active = true;
}
