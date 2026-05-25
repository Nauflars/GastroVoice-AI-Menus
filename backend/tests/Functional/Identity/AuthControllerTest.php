<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthControllerTest extends WebTestCase
{
    public function testLoginWithValidCredentialsReturnsToken(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'admin@gastrovoice.com', 'password' => 'admin123'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $data);
    }

    public function testLoginWithInvalidCredentialsReturns401(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'admin@gastrovoice.com', 'password' => 'wrongpassword'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testProtectedRouteRequiresJwt(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/restaurant');

        self::assertResponseStatusCodeSame(401);
    }
}
