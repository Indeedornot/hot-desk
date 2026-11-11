<?php

declare(strict_types=1);

namespace App\Domain;

final class RuleViolation extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }
}
