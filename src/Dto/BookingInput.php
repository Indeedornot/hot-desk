<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class BookingInput
{
    public function __construct(
        #[Assert\Positive]
        public int $deskId = 0,
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $date = '',
    ) {
    }
}
