<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\RuleViolation;
use App\Entity\AuditEvent;
use App\Entity\Booking;
use App\Entity\Desk;
use App\Entity\SeatAssignment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final class WorkspaceRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function get(string $class, int $id): object
    {
        return $this->em->find($class, $id) ?? throw new RuleViolation('The requested resource was not found.', 404);
    }

    public function all(string $class): array
    {
        return $this->em->getRepository($class)->findBy([], ['id' => 'ASC']);
    }

    public function assignmentForUser(User $user): ?SeatAssignment
    {
        return $this->em->getRepository(SeatAssignment::class)->findOneBy(['user' => $user]);
    }

    public function assignmentForDesk(Desk $desk): ?SeatAssignment
    {
        return $this->em->getRepository(SeatAssignment::class)->findOneBy(['desk' => $desk]);
    }

    public function currentBookings(): array
    {
        return $this->em->createQueryBuilder()->select('b', 'd', 'o', 'u')->from(Booking::class, 'b')
            ->join('b.desk', 'd')->join('d.office', 'o')->join('b.user', 'u')
            ->where('b.status IN (:statuses)')->andWhere('b.endsAt > :now')
            ->setParameter('statuses', ['BOOKED', 'CHECKED_IN'])->setParameter('now', new \DateTimeImmutable())->getQuery()->getResult();
    }

    public function activeForDay(Desk $desk, string $date): ?Booking
    {
        return $this->em->getRepository(Booking::class)->findOneBy(['desk' => $desk, 'date' => new \DateTimeImmutable($date), 'status' => ['BOOKED', 'CHECKED_IN']]);
    }

    public function bookings(User $user, bool $admin, array $filters): array
    {
        if (!empty($filters['date'])) {
            $date = is_string($filters['date']) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $filters['date']) : false;
            if (!$date || $date->format('Y-m-d') !== $filters['date']) {
                throw new RuleViolation('Use a valid YYYY-MM-DD date filter.', 422);
            }
        }
        if (!empty($filters['status']) && !in_array($filters['status'], ['BOOKED', 'CHECKED_IN', 'CHECKED_OUT', 'CANCELLED'], true)) {
            throw new RuleViolation('Unknown reservation status.', 422);
        }
        if (!empty($filters['officeId']) && (!is_scalar($filters['officeId']) || !ctype_digit((string) $filters['officeId']))) {
            throw new RuleViolation('Use a numeric office identifier.', 422);
        }
        $qb = $this->em->createQueryBuilder()->select('b')->from(Booking::class, 'b')->join('b.desk', 'd');
        if (!$admin) {
            $qb->andWhere('b.user = :user')->setParameter('user', $user);
        }
        foreach (['date' => 'b.date', 'status' => 'b.status', 'officeId' => 'd.office'] as $key => $field) {
            if (!empty($filters[$key]) && is_scalar($filters[$key])) {
                $qb->andWhere($field.' = :'.$key)->setParameter($key, $filters[$key]);
            }
        }
        $qb->orderBy('b.createdAt', 'DESC')->addOrderBy('b.id', 'DESC');

        return $this->paginate($qb, $filters);
    }

    public function users(array $filters): array
    {
        $qb = $this->em->createQueryBuilder()->select('u')->from(User::class, 'u')->orderBy('u.name', 'ASC');
        if (!empty($filters['q']) && is_string($filters['q'])) {
            $qb->where('LOWER(u.name) LIKE :q OR LOWER(u.email) LIKE :q')->setParameter('q', '%'.mb_strtolower($filters['q']).'%');
        }

        return $this->paginate($qb, $filters);
    }

    public function events(User $user, bool $admin, array $filters): array
    {
        $where = [];
        $params = [];
        if (!$admin) {
            $where[] = "event_data->>'userId' = :user";
            $params['user'] = (string) $user->id;
        }
        foreach (['event_type' => 'event_type', 'event_name' => 'event_name'] as $key => $column) {
            if (!empty($filters[$key]) && is_string($filters[$key])) {
                $where[] = $column.' = :'.$key;
                $params[$key] = $filters[$key];
            }
        }
        if (!empty($filters['bookingId']) && is_scalar($filters['bookingId'])) {
            $where[] = "event_data->>'bookingId' = :booking";
            $params['booking'] = (string) $filters['bookingId'];
        }
        $condition = $where ? ' WHERE '.implode(' AND ', $where) : '';
        [$page, $size] = $this->page($filters);
        $db = $this->em->getConnection();
        $total = (int) $db->fetchOne('SELECT count(*) FROM audit_event'.$condition, $params);
        $ids = $db->fetchFirstColumn('SELECT id FROM audit_event'.$condition.' ORDER BY event_date DESC, id DESC LIMIT '.$size.' OFFSET '.(($page - 1) * $size), $params);

        return ['items' => array_map(fn ($id) => $this->get(AuditEvent::class, (int) $id), $ids), 'total' => $total, 'page' => $page, 'pageSize' => $size];
    }

    private function paginate(QueryBuilder $qb, array $filters): array
    {
        [$page, $size] = $this->page($filters);
        $count = clone $qb;
        $total = (int) $count->select('COUNT('.$qb->getRootAliases()[0].'.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        return ['items' => $qb->setFirstResult(($page - 1) * $size)->setMaxResults($size)->getQuery()->getResult(), 'total' => $total, 'page' => $page, 'pageSize' => $size];
    }

    private function page(array $filters): array
    {
        foreach (['page', 'pageSize'] as $key) {
            if (isset($filters[$key]) && (!is_scalar($filters[$key]) || !ctype_digit((string) $filters[$key]) || (int) $filters[$key] < 1)) {
                throw new RuleViolation('Pagination values must be positive integers.', 422);
            }
        }

        return [max(1, min(100000, (int) ($filters['page'] ?? 1))), max(1, min(100, (int) ($filters['pageSize'] ?? 20)))];
    }
}
