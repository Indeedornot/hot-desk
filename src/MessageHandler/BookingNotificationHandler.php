<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BookingNotification;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class BookingNotificationHandler
{
    public function __construct(private Connection $db, private MailerInterface $mailer, #[Autowire('%env(MAILER_FROM)%')] private string $from)
    {
    }

    public function __invoke(BookingNotification $message): void
    {
        $this->db->transactional(function () use ($message) {
            $this->db->executeQuery('SELECT pg_advisory_xact_lock(hashtext(:id))', ['id' => $message->eventId]);
            if ($this->db->fetchOne('SELECT event_id FROM notification_delivery WHERE event_id = ?', [$message->eventId])) {
                return;
            }
            $action = str_replace('_', ' ', $message->action);
            $email = (new Email())->from($this->from)->to($message->email)->subject('Hot Desk: reservation '.$action)
                ->text("Hello {$message->name},\n\nYour reservation was {$action}.\nOffice: {$message->office}\nDesk: {$message->desk}\nDate: {$message->date}\n\nOpen Hot Desk to see your current reservations.\n");
            $this->mailer->send($email);
            $this->db->insert('notification_delivery', ['event_id' => $message->eventId, 'sent_at' => gmdate('Y-m-d H:i:s')]);
        });
    }
}
