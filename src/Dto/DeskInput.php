<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DeskInput
{
    public function __construct(
        #[Assert\Positive]
        public int $officeId = 0,
        #[Assert\NotBlank]
        #[Assert\Length(max: 40)]
        public string $label = '',
        #[Assert\Length(max: 255)]
        public string $equipment = '',
        #[Assert\Range(min: 0, max: 9)]
        public int $x = 0,
        #[Assert\Range(min: 0, max: 9)]
        public int $y = 0,
        public bool $active = true,
    ) {
    }
}
