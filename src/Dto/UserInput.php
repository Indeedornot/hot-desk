<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UserInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name = '',
        #[Assert\Length(min: 10, max: 128)]
        public ?string $password = null,
        #[Assert\Choice(['ROLE_USER', 'ROLE_ADMIN'])]
        public string $role = 'ROLE_USER',
        public bool $active = true,
    ) {
    }
}
