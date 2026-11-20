<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Desk;
use App\Entity\Office;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiTest extends WebTestCase
{
    private KernelBrowser $browser;
    private string $csrf;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('TRUNCATE audit_event, seat_assignment, booking, desk, office, app_user, messenger_messages RESTART IDENTITY CASCADE');
        foreach ([['admin@example.test', 'ROLE_ADMIN'], ['user@example.test', 'ROLE_USER'], ['other@example.test', 'ROLE_USER']] as [$email, $role]) {
            $user = new User();
            $user->email = $email;
            $user->name = $email;
            $user->role = $role;
            $user->password = password_hash('Test-password-42', PASSWORD_BCRYPT, ['cost' => 4]);
            $em->persist($user);
        }
        $office = new Office();
        $office->name = 'Test office';
        $office->address = 'Kraków';
        $office->timezone = 'UTC';
        $office->openingTime = '00:00';
        $office->closingTime = '23:59';
        $em->persist($office);
        foreach (['A1', 'A2'] as $index => $label) {
            $desk = new Desk();
            $desk->office = $office;
            $desk->label = $label;
            $desk->x = $index;
            $em->persist($desk);
        }
        $em->flush();
        $this->browser->request('GET', '/api/auth/csrf');
        $this->csrf = $this->body()['csrfToken'];
    }

    private function body(): array
    {
        return json_decode($this->browser->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function send(string $method, string $url, array $data = []): array
    {
        $this->browser->jsonRequest($method, $url, $data, ['HTTP_X_CSRF_TOKEN' => $this->csrf]);

        return $this->body();
    }

    private function login(string $email = 'user@example.test'): void
    {
        $result = $this->send('POST', '/api/auth/login', ['email' => $email, 'password' => 'Test-password-42']);
        self::assertResponseIsSuccessful();
        $this->csrf = $result['csrfToken'];
    }

    public function testAuthenticationAndAdminAuthorization(): void
    {
        $this->browser->request('GET', '/api/offices');
        self::assertResponseStatusCodeSame(401);
        $failure = $this->send('POST', '/api/auth/login', ['email' => 'user@example.test', 'password' => 'wrong-password']);
        self::assertResponseStatusCodeSame(401);
        self::assertIsString($failure['error']['message']);
        $this->login();
        $this->browser->request('GET', '/api/admin/users');
        self::assertResponseStatusCodeSame(403);
    }

    public function testBookingCheckinCheckoutAndAuditTrail(): void
    {
        $this->login();
        $result = $this->send('POST', '/api/bookings', ['deskId' => 1, 'date' => gmdate('Y-m-d')]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(gmdate('Y-m-d').'T00:00:00+00:00', $result['startsAt'] ?? null);
        self::assertSame(gmdate('Y-m-d').'T23:59:00+00:00', $result['endsAt'] ?? null);
        $id = $result['id'];
        $this->send('POST', "/api/bookings/$id/check-in");
        self::assertResponseIsSuccessful();
        $this->send('POST', "/api/bookings/$id/cancel");
        self::assertResponseStatusCodeSame(409);
        $this->send('POST', "/api/bookings/$id/check-out");
        self::assertResponseIsSuccessful();
        $this->browser->request('GET', '/api/events');
        $events = $this->body()['items'];
        self::assertSame(['booking.checked_out', 'booking.checked_in', 'booking.created'], array_column($events, 'event_name'));
        self::assertSame(2, $events[0]['event_data']['userId']);
        self::assertSame(2, $events[0]['actorId']);
        $this->send('POST', '/api/bookings', ['deskId' => 1, 'date' => gmdate('Y-m-d')]);
        self::assertResponseStatusCodeSame(201);
    }

    public function testAssignmentsRestrictBothUserAndDesk(): void
    {
        $this->login('admin@example.test');
        $this->send('POST', '/api/admin/assignments', ['deskId' => 1, 'userId' => 2]);
        self::assertResponseStatusCodeSame(201);
        $this->send('POST', '/api/auth/logout');
        $this->browser->request('GET', '/api/auth/csrf');
        $this->csrf = $this->body()['csrfToken'];
        $this->login();
        $this->send('POST', '/api/bookings', ['deskId' => 2, 'date' => gmdate('Y-m-d')]);
        self::assertResponseStatusCodeSame(409);
        $this->send('POST', '/api/bookings', ['deskId' => 1, 'date' => gmdate('Y-m-d')]);
        self::assertResponseStatusCodeSame(201);
    }

    public function testDuplicateBookingRollsBackAuditAndQueue(): void
    {
        $this->login();
        $this->send('POST', '/api/bookings', ['deskId' => 1, 'date' => gmdate('Y-m-d')]);
        self::assertResponseStatusCodeSame(201);
        $this->send('POST', '/api/bookings', ['deskId' => 2, 'date' => gmdate('Y-m-d')]);
        self::assertResponseStatusCodeSame(409);
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame(1, (int) $connection->fetchOne('SELECT count(*) FROM audit_event'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT count(*) FROM messenger_messages'));
    }

    public function testInputValidationAndCsrfProtection(): void
    {
        $this->login();
        $this->send('POST', '/api/bookings', ['deskId' => 0, 'date' => 'invalid']);
        self::assertResponseStatusCodeSame(422);
        $this->browser->request('GET', '/api/bookings?date=not-a-date');
        self::assertResponseStatusCodeSame(422);
        $this->browser->request('GET', '/api/events?page[]=1');
        self::assertResponseStatusCodeSame(422);
        $this->browser->jsonRequest('POST', '/api/bookings', ['deskId' => 1, 'date' => gmdate('Y-m-d')]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminOfficeCrudAndLastAdminProtection(): void
    {
        $this->login('admin@example.test');
        $this->send('POST', '/api/admin/offices', ['name' => 'Invalid office', 'address' => 'Warsaw', 'timezone' => '', 'openingTime' => '']);
        self::assertResponseStatusCodeSame(422);
        $office = $this->send('POST', '/api/admin/offices', ['name' => 'Second office', 'address' => 'Warsaw']);
        self::assertResponseStatusCodeSame(201);
        $this->send('PUT', '/api/admin/offices/'.$office['id'], ['name' => 'Renamed office', 'address' => 'Warsaw']);
        self::assertResponseIsSuccessful();
        $this->send('DELETE', '/api/admin/offices/'.$office['id']);
        self::assertResponseIsSuccessful();
        $this->send('DELETE', '/api/admin/users/1');
        self::assertResponseStatusCodeSame(409);
    }

    public function testUserCannotReadOrCancelAnotherUsersBooking(): void
    {
        $this->login('other@example.test');
        $booking = $this->send('POST', '/api/bookings', ['deskId' => 1, 'date' => gmdate('Y-m-d')]);
        self::assertResponseStatusCodeSame(201);
        $this->send('POST', '/api/auth/logout');
        $this->browser->request('GET', '/api/auth/csrf');
        $this->csrf = $this->body()['csrfToken'];
        $this->login();
        $this->send('POST', '/api/bookings/'.$booking['id'].'/cancel');
        self::assertResponseStatusCodeSame(403);
        $this->browser->request('GET', '/api/bookings');
        self::assertSame([], $this->body()['items']);
        $this->browser->request('GET', '/api/events');
        self::assertSame([], $this->body()['items']);
    }
}
