<?php

declare(strict_types=1);

namespace App\Tests\Functional\VoiceAssistant;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VoiceControllerTest extends WebTestCase
{
    public function testSimulateEndpointAcceptsText(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('POST', '/api/voice/simulate', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'restaurantId' => '00000000-0000-7000-8000-000000000001',
            'text' => 'Hola, quiero hacer una reserva',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('sessionId', $data);
        self::assertArrayHasKey('reply', $data);
        self::assertArrayHasKey('intent', $data);
        self::assertNotEmpty($data['reply']);
    }

    public function testSimulateMultiTurnConversation(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        // Turn 1: greeting
        $client->request('POST', '/api/voice/simulate', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'restaurantId' => '00000000-0000-7000-8000-000000000001',
            'text' => 'Quiero reservar mesa para 4 personas',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $data1 = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $sessionId = $data1['sessionId'];
        self::assertNotEmpty($sessionId);

        // Turn 2: provide more data (same session)
        $client->request('POST', '/api/voice/simulate', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'restaurantId' => '00000000-0000-7000-8000-000000000001',
            'text' => 'Para mañana a las 14:00, a nombre de Carlos',
            'sessionId' => $sessionId,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $data2 = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($sessionId, $data2['sessionId']);
    }

    public function testSimulateMenuQuery(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('POST', '/api/voice/simulate', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'restaurantId' => '00000000-0000-7000-8000-000000000001',
            'text' => '¿Qué platos tenéis hoy?',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('query_menu', $data['intent']);
    }

    public function testCallEndpointRequiresAudioFile(): void
    {
        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('POST', '/api/voice/call', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(400);
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('audioFile is required', $data['error']);
    }

    public function testRealtimeSessionEndpoint(): void
    {
        // This test will only work if OPENAI_API_KEY is set — skip otherwise
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? '';
        if (empty($apiKey) || str_starts_with($apiKey, 'sk-proj-your')) {
            self::markTestSkipped('OPENAI_API_KEY not configured — skipping realtime session test.');
        }

        $client = static::createClient();
        $token = $this->getJwtToken($client);

        $client->request('POST', '/api/voice/realtime-session', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'restaurantId' => '00000000-0000-7000-8000-000000000001',
            'restaurantName' => 'Test Restaurant',
            'voice' => 'nova',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('clientSecret', $data);
        self::assertArrayHasKey('tools', $data);
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
