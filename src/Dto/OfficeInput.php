<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class OfficeInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $address = '',
        #[Assert\NotBlank]
        #[Assert\Timezone]
        public string $timezone = 'Europe/Warsaw',
        #[Assert\NotBlank]
        #[Assert\Regex('/^([01][0-9]|2[0-3]):[0-5][0-9]$/')]
        public string $openingTime = '08:00',
        #[Assert\NotBlank]
        #[Assert\Regex('/^([01][0-9]|2[0-3]):[0-5][0-9]$/')]
        public string $closingTime = '18:00',
        public bool $active = true,
    ) {
    }
}
