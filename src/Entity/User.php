<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    public string $email = '';

    #[ORM\Column(length: 100)]
    public string $name = '';

    #[ORM\Column]
    public string $password = '';

    #[ORM\Column(length: 20)]
    public string $role = 'ROLE_USER';

    #[ORM\Column]
    public bool $active = true;

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getRoles(): array
    {
        return array_values(array_unique(['ROLE_USER', $this->role]));
    }

    public function eraseCredentials(): void
    {
    }
}
