<?php

declare(strict_types=1);

namespace App\Tests\Functional\Menu;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MenuControllerTest extends WebTestCase
{
    public function testGetPublicMenuReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/menu/' . '00000000-0000-7000-8000-000000000001');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testCreateCategoryRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/menu/categories', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Starters', 'displayOrder' => 1]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateCategoryWithAuth(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('POST', '/api/menu/categories', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['name' => 'Starters', 'displayOrder' => 1]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
    }

    public function testUpdateCategory(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        // Create
        $client->request('POST', '/api/menu/categories', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['name' => 'Old Name', 'displayOrder' => 0]));

        $id = json_decode($client->getResponse()->getContent(), true)['id'];

        // Update
        $client->request('PUT', '/api/menu/categories/' . $id, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['name' => 'New Name', 'displayOrder' => 1]));

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeactivateCategory(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('POST', '/api/menu/categories', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['name' => 'To Delete', 'displayOrder' => 0]));

        $id = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('DELETE', '/api/menu/categories/' . $id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testCreateMenuItem(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        // Create category first
        $client->request('POST', '/api/menu/categories', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['name' => 'Desserts', 'displayOrder' => 0]));

        $categoryId = json_decode($client->getResponse()->getContent(), true)['id'];

        // Create item
        $client->request('POST', '/api/menu/items', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'categoryId' => $categoryId,
            'name' => 'Tiramisu',
            'description' => 'Classic Italian dessert',
            'price' => 7.50,
            'currency' => 'EUR',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
    }

    public function testToggleItemAvailability(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        // Create category
        $client->request('POST', '/api/menu/categories', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['name' => 'Mains', 'displayOrder' => 0]));
        $categoryId = json_decode($client->getResponse()->getContent(), true)['id'];

        // Create item
        $client->request('POST', '/api/menu/items', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'categoryId' => $categoryId,
            'name' => 'Pasta',
            'description' => null,
            'price' => 12.00,
        ]));
        $itemId = json_decode($client->getResponse()->getContent(), true)['id'];

        // Toggle availability
        $client->request('PATCH', '/api/menu/items/' . $itemId . '/toggle', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    private function getJwtToken($client): string
    {
        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'admin@gastrovoice.test',
            'password' => 'password123',
        ]));

        $data = json_decode($client->getResponse()->getContent(), true);

        return $data['token'] ?? '';
    }
}
