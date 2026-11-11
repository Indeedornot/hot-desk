<?php

declare(strict_types=1);

namespace App\Message;

final readonly class BookingNotification
{
    public function __construct(public string $eventId, public string $email, public string $name, public string $action, public string $desk, public string $office, public string $date)
    {
    }
}
