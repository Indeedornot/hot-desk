<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\RuleViolation;
use App\Dto\AssignmentInput;
use App\Dto\DeskInput;
use App\Dto\OfficeInput;
use App\Dto\UserInput;
use App\Entity\Booking;
use App\Entity\Desk;
use App\Entity\Office;
use App\Entity\SeatAssignment;
use App\Entity\User;
use App\Repository\WorkspaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminService
{
    public function __construct(private EntityManagerInterface $em, private WorkspaceRepository $repo, private Mutation $mutation, private AuditLog $audit, private ClockInterface $clock, private UserPasswordHasherInterface $hasher)
    {
    }

    public function office(User $actor, OfficeInput $input, ?int $id): Office
    {
        return $this->mutation->run(function () use ($actor, $input, $id) {
            $this->checkAdmin($actor);
            if ($input->openingTime >= $input->closingTime) {
                throw new RuleViolation('Closing time must be after opening time.', 422);
            }
            $office = $id ? $this->repo->get(Office::class, $id) : new Office();
            if ($id && ($office->timezone !== $input->timezone || $office->openingTime !== $input->openingTime || $office->closingTime !== $input->closingTime || !$input->active)) {
                foreach ($this->repo->currentBookings() as $booking) {
                    if ($booking->desk->office->id === $id) {
                        throw new RuleViolation('Resolve current and future reservations before changing office hours or deactivating it.');
                    }
                }
                if (!$input->active) {
                    foreach ($this->repo->all(SeatAssignment::class) as $assignment) {
                        if ($assignment->desk->office->id === $id) {
                            throw new RuleViolation('Remove permanent assignments before deactivating the office.');
                        }
                    }
                }
            }
            foreach (['name', 'address', 'timezone', 'openingTime', 'closingTime', 'active'] as $field) {
                $office->$field = $input->$field;
            }
            $this->save($actor, $office, 'office', $id);

            return $office;
        });
    }

    public function desk(User $actor, DeskInput $input, ?int $id): Desk
    {
        return $this->mutation->run(function () use ($actor, $input, $id) {
            $this->checkAdmin($actor);
            $desk = $id ? $this->repo->get(Desk::class, $id) : new Desk();
            $office = $this->repo->get(Office::class, $input->officeId);
            if (!$office->active && $input->active) {
                throw new RuleViolation('Activate the office before enabling its desks.');
            }
            if ($id && ($desk->office->id !== $office->id || !$input->active)) {
                if ($this->repo->assignmentForDesk($desk)) {
                    throw new RuleViolation('Unassign this desk before moving or deactivating it.');
                }
                foreach ($this->repo->currentBookings() as $booking) {
                    if ($booking->desk->id === $id) {
                        throw new RuleViolation('Resolve reservations before moving or deactivating this desk.');
                    }
                }
                if ($desk->office->id !== $office->id && $this->em->getRepository(Booking::class)->count(['desk' => $desk]) > 0) {
                    throw new RuleViolation('A desk with reservation history cannot move to another office.');
                }
            }
            $position = $this->em->getRepository(Desk::class)->findOneBy(['office' => $office, 'x' => $input->x, 'y' => $input->y]);
            if ($position && $position->id !== $id) {
                throw new RuleViolation('Another desk already occupies this map position.');
            }
            $desk->office = $office;
            foreach (['label', 'equipment', 'x', 'y', 'active'] as $field) {
                $desk->$field = $input->$field;
            }
            $this->save($actor, $desk, 'desk', $id);

            return $desk;
        });
    }

    public function user(User $actor, UserInput $input, ?int $id): User
    {
        return $this->mutation->run(function () use ($actor, $input, $id) {
            $this->checkAdmin($actor);
            $user = $id ? $this->repo->get(User::class, $id) : new User();
            if (!$id && null === $input->password) {
                throw new RuleViolation('A password of at least 10 characters is required.', 422);
            }
            $this->protectAdmin($user, $input->role, $input->active);
            if ($id && !$input->active) {
                $this->assertUserFree($user);
            }
            $user->name = $input->name;
            $user->email = mb_strtolower(trim($input->email));
            $user->role = $input->role;
            $user->active = $input->active;
            if (null !== $input->password) {
                $user->password = $this->hasher->hashPassword($user, $input->password);
            }
            $this->save($actor, $user, 'user', $id);

            return $user;
        });
    }

    public function assign(User $actor, AssignmentInput $input): SeatAssignment
    {
        return $this->mutation->run(function () use ($actor, $input) {
            $this->checkAdmin($actor);
            $user = $this->repo->get(User::class, $input->userId);
            $desk = $this->repo->get(Desk::class, $input->deskId);
            if (!$user->active || !$desk->active || !$desk->office->active) {
                throw new RuleViolation('Choose an active person, desk and office.');
            }
            if ($this->repo->assignmentForUser($user) || $this->repo->assignmentForDesk($desk)) {
                throw new RuleViolation('Remove the existing assignment before creating another one.');
            }
            foreach ($this->repo->currentBookings() as $booking) {
                if (($booking->desk->id === $desk->id && $booking->user->id !== $user->id) || ($booking->user->id === $user->id && $booking->desk->id !== $desk->id)) {
                    throw new RuleViolation('Existing reservations conflict with this assignment. Resolve them first.');
                }
            }
            $assignment = new SeatAssignment();
            $assignment->user = $user;
            $assignment->desk = $desk;
            $assignment->assignedBy = $actor;
            $assignment->createdAt = $this->clock->now();
            $this->em->persist($assignment);
            $this->em->flush();
            $this->audit->record($actor, 'seat.assigned', 'administration', ['assignmentId' => $assignment->id, 'userId' => $user->id, 'deskId' => $desk->id, 'deskLabel' => $desk->label]);

            return $assignment;
        });
    }

    public function unassign(User $actor, int $id): void
    {
        $this->mutation->run(function () use ($actor, $id) {
            $this->checkAdmin($actor);
            $assignment = $this->repo->get(SeatAssignment::class, $id);
            $this->audit->record($actor, 'seat.unassigned', 'administration', ['assignmentId' => $id, 'userId' => $assignment->user->id, 'deskId' => $assignment->desk->id, 'deskLabel' => $assignment->desk->label]);
            $this->em->remove($assignment);
        });
    }

    public function delete(User $actor, string $resource, int $id): void
    {
        $this->mutation->run(function () use ($actor, $resource, $id) {
            $this->checkAdmin($actor);
            $class = match ($resource) {
                'offices' => Office::class, 'desks' => Desk::class, 'users' => User::class
            };
            $entity = $this->repo->get($class, $id);
            if ($entity instanceof User) {
                $this->protectAdmin($entity, 'ROLE_USER', false);
                $this->assertUserFree($entity);
            }
            $this->em->remove($entity);
            $this->em->flush();
            $this->audit->record($actor, rtrim($resource, 's').'.deleted', 'administration', ['resourceId' => $id]);
        });
    }

    private function save(User $actor, object $entity, string $resource, ?int $id): void
    {
        $this->em->persist($entity);
        $this->em->flush();
        $this->audit->record($actor, $resource.($id ? '.updated' : '.created'), 'administration', ['resourceId' => $entity->id]);
    }

    private function assertUserFree(User $user): void
    {
        if ($this->repo->assignmentForUser($user)) {
            throw new RuleViolation('Remove this person’s permanent seat assignment first.');
        }
        foreach ($this->repo->currentBookings() as $booking) {
            if ($booking->user->id === $user->id) {
                throw new RuleViolation('Resolve this person’s current and future reservations first.');
            }
        }
    }

    private function protectAdmin(User $user, string $role, bool $active): void
    {
        if ($user->id && $user->active && 'ROLE_ADMIN' === $user->role && (!$active || 'ROLE_ADMIN' !== $role) && $this->em->getRepository(User::class)->count(['active' => true, 'role' => 'ROLE_ADMIN']) <= 1) {
            throw new RuleViolation('At least one active administrator must remain.');
        }
    }

    private function checkAdmin(User $actor): void
    {
        $this->em->refresh($actor);
        if (!$actor->active || 'ROLE_ADMIN' !== $actor->role) {
            throw new RuleViolation('Administrator access is required.', 403);
        }
    }
}
