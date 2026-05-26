<?php

declare(strict_types=1);

namespace App\Tests\Functional\Reservations;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReservationControllerTest extends WebTestCase
{
    public function testCheckAvailabilityRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/reservations/availability?date=2026-06-01&timeSlot=13:00&numPeople=4');

        self::assertResponseStatusCodeSame(401);
    }

    public function testCheckAvailabilityReturnsResult(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('GET', '/api/reservations/availability?date=2026-06-01&timeSlot=13:00&numPeople=4', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('available', $data);
    }

    public function testListReservationsReturnsArray(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('GET', '/api/reservations', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
    }

    public function testCreateReservationReturnsCreated(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'date' => '2026-07-15',
            'timeSlot' => '20:00',
            'numPeople' => 4,
            'customerName' => 'Juan García',
            'customerPhone' => '+34600111222',
            'customerEmail' => 'juan@example.com',
            'notes' => 'Mesa junto a la ventana',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $data);
        self::assertSame('pending', $data['status']);
        self::assertSame('2026-07-15', $data['reservationDate']);
        self::assertSame(4, $data['numPeople']);
        self::assertSame('Juan García', $data['customerName']);
    }

    public function testGetReservationByIdReturnsReservation(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        // Create first
        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'date' => '2026-08-01',
            'timeSlot' => '14:00',
            'numPeople' => 2,
            'customerName' => 'María López',
        ], JSON_THROW_ON_ERROR));

        $reservationId = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['id'];

        // Fetch by ID
        $client->request('GET', '/api/reservations/' . $reservationId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($reservationId, $data['id']);
        self::assertSame('María López', $data['customerName']);
    }

    public function testGetNonExistentReservationReturns404(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('GET', '/api/reservations/00000000-0000-7000-8000-ffffffffffff', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCancelReservationReturnsCancelled(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        // Create
        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'date' => '2026-09-01',
            'timeSlot' => '21:00',
            'numPeople' => 3,
            'customerName' => 'Pedro Ruiz',
        ], JSON_THROW_ON_ERROR));

        $reservationId = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['id'];

        // Cancel
        $client->request('POST', '/api/reservations/' . $reservationId . '/cancel', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('cancelled', $data['status']);
    }

    public function testCancelAlreadyCancelledReturns422(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        // Create
        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'date' => '2026-09-02',
            'timeSlot' => '19:00',
            'numPeople' => 2,
            'customerName' => 'Ana Fernández',
        ], JSON_THROW_ON_ERROR));

        $reservationId = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['id'];

        // Cancel first time
        $client->request('POST', '/api/reservations/' . $reservationId . '/cancel', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        self::assertResponseIsSuccessful();

        // Cancel again
        $client->request('POST', '/api/reservations/' . $reservationId . '/cancel', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testFilterReservationsByDate(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('GET', '/api/reservations?date=2026-07-15', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
    }

    private function getJwtToken($client): string
    {
        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'admin@gastrovoice.test',
            'password' => 'password123',
        ], JSON_THROW_ON_ERROR));

        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $data['token'] ?? '';
    }
}
