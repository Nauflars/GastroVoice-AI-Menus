<?php

declare(strict_types=1);

namespace App\Tests\Functional\Menu;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MenuImportControllerTest extends WebTestCase
{
    public function testImportRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/menu/import');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testImportRejectsNoFile(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('POST', '/api/menu/import', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testConfirmWithInvalidPreviewIdReturns404(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('POST', '/api/menu/import/nonexistent-preview/confirm', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(404);
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
