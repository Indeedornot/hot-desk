<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

final class Mutation
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function run(callable $operation): mixed
    {
        return $this->em->wrapInTransaction(function () use ($operation) {
            // All booking and administration writes share a transaction lock to prevent assignment races.
            $this->em->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(783962)');

            return $operation();
        });
    }
}
