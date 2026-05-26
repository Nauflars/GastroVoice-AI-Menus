<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ordering;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderControllerTest extends WebTestCase
{
    public function testListOrdersRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/orders');

        self::assertResponseStatusCodeSame(401);
    }

    public function testListOrdersReturnsArray(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('GET', '/api/orders', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
    }

    public function testPlaceOrderReturnsCreated(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'source' => 'web',
            'tableNumber' => '5',
            'lines' => [
                [
                    'menuItemId' => '00000000-0000-7000-8000-000000000099',
                    'menuItemName' => 'Test Dish',
                    'quantity' => 2,
                    'unitPrice' => 12.50,
                    'currency' => 'EUR',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('status', $data);
        self::assertSame('pending', $data['status']);
        self::assertArrayHasKey('lines', $data);
        self::assertCount(1, $data['lines']);
        self::assertSame(25.0, $data['totalAmount']);
    }

    public function testPlaceOrderWithEmptyLinesReturns422(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'source' => 'web',
            'lines' => [],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testGetOrderByIdReturnsOrder(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        // Create order first
        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'source' => 'manual',
            'lines' => [[
                'menuItemId' => '00000000-0000-7000-8000-000000000099',
                'menuItemName' => 'Paella',
                'quantity' => 1,
                'unitPrice' => 18.00,
                'currency' => 'EUR',
            ]],
        ], JSON_THROW_ON_ERROR));

        $orderId = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['id'];

        // Fetch by ID
        $client->request('GET', '/api/orders/' . $orderId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($orderId, $data['id']);
        self::assertSame('pending', $data['status']);
    }

    public function testGetNonExistentOrderReturns404(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('GET', '/api/orders/00000000-0000-7000-8000-ffffffffffff', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUpdateOrderStatusReturnsUpdatedOrder(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        // Create order
        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'source' => 'web',
            'lines' => [[
                'menuItemId' => '00000000-0000-7000-8000-000000000099',
                'menuItemName' => 'Gazpacho',
                'quantity' => 1,
                'unitPrice' => 6.00,
                'currency' => 'EUR',
            ]],
        ], JSON_THROW_ON_ERROR));

        $orderId = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['id'];

        // Transition pending → confirmed
        $client->request('PATCH', '/api/orders/' . $orderId, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['status' => 'confirmed'], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('confirmed', $data['status']);
    }

    public function testInvalidStatusTransitionReturns422(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        // Create order
        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'source' => 'web',
            'lines' => [[
                'menuItemId' => '00000000-0000-7000-8000-000000000099',
                'menuItemName' => 'Tortilla',
                'quantity' => 1,
                'unitPrice' => 8.00,
                'currency' => 'EUR',
            ]],
        ], JSON_THROW_ON_ERROR));

        $orderId = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['id'];

        // Try invalid transition: pending → delivered (skips confirmed, preparing, ready)
        $client->request('PATCH', '/api/orders/' . $orderId, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['status' => 'delivered'], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testFilterOrdersByStatus(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('GET', '/api/orders?status=pending', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
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
