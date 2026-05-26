<?php

declare(strict_types=1);

namespace App\Tests\Unit\VoiceAssistant\Infrastructure\AI;

use App\VoiceAssistant\Domain\ValueObject\Intent;
use App\VoiceAssistant\Infrastructure\AI\OpenAIIntentDetectorAdapter;
use OpenAI\Client;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class OpenAIIntentDetectorAdapterTest extends TestCase
{
    private Client&MockObject $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
    }

    public function testDetectReturnsCreateReservation(): void
    {
        $json = json_encode([
            'intent'         => 'create_reservation',
            'data'           => ['date' => '2026-06-15', 'timeSlot' => '13:00', 'numPeople' => 4],
            'missing_fields' => [],
            'reply'          => 'Reservando mesa.',
        ]);

        $response = $this->createChatResponse($json);
        $chat = $this->createMock(Chat::class);
        $chat->method('create')->willReturn($response);
        $this->client->method('chat')->willReturn($chat);

        $adapter = new OpenAIIntentDetectorAdapter($this->client);
        $result = $adapter->detect(
            [['role' => 'user', 'content' => 'Mesa para 4 a las 13']],
            [],
        );

        $this->assertSame(Intent::CreateReservation, $result->intent);
        $this->assertSame('2026-06-15', $result->data['date']);
        $this->assertEmpty($result->missingFields);
    }

    public function testDetectFallsBackToUnknownOnInvalidJson(): void
    {
        $response = $this->createChatResponse('this is not json {{{');
        $chat = $this->createMock(Chat::class);
        $chat->method('create')->willReturn($response);
        $this->client->method('chat')->willReturn($chat);

        $adapter = new OpenAIIntentDetectorAdapter($this->client);
        $result = $adapter->detect([['role' => 'user', 'content' => '...']], []);

        $this->assertSame(Intent::Unknown, $result->intent);
        $this->assertStringContainsString('no le he entendido', $result->reply);
    }

    public function testDetectFallsBackToUnknownOnUnrecognizedIntent(): void
    {
        $json = json_encode([
            'intent'         => 'some_undefined_intent',
            'data'           => [],
            'missing_fields' => [],
            'reply'          => 'No entiendo.',
        ]);

        $response = $this->createChatResponse($json);
        $chat = $this->createMock(Chat::class);
        $chat->method('create')->willReturn($response);
        $this->client->method('chat')->willReturn($chat);

        $adapter = new OpenAIIntentDetectorAdapter($this->client);
        $result = $adapter->detect([['role' => 'user', 'content' => 'blabla']], []);

        $this->assertSame(Intent::Unknown, $result->intent);
    }

    private function createChatResponse(string $content): CreateResponse
    {
        return CreateResponse::from([
            'id'      => 'chatcmpl-test',
            'object'  => 'chat.completion',
            'created' => time(),
            'model'   => 'gpt-4.1-mini',
            'choices' => [
                [
                    'index'         => 0,
                    'message'       => ['role' => 'assistant', 'content' => $content],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
        ]);
    }
}
