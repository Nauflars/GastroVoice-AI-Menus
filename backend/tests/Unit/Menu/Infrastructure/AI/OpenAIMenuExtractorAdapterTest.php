<?php

declare(strict_types=1);

namespace App\Tests\Unit\Menu\Infrastructure\AI;

use App\Menu\Application\DTO\MenuImportPreview;
use App\Menu\Infrastructure\AI\OpenAIMenuExtractorAdapter;
use OpenAI\Client as OpenAIClient;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class OpenAIMenuExtractorAdapterTest extends TestCase
{
    private OpenAIClient&MockObject $openai;

    protected function setUp(): void
    {
        $this->openai = $this->createMock(OpenAIClient::class);
    }

    public function testExtractReturnsPreviewFromValidResponse(): void
    {
        $jsonResponse = json_encode([
            'categories' => [
                [
                    'name' => 'Starters',
                    'items' => [
                        ['name' => 'Soup', 'description' => 'Hot soup', 'price' => 8.50, 'currency' => 'EUR', 'allergens' => ['gluten']],
                    ],
                ],
            ],
        ]);

        $chat = $this->createMock(Chat::class);
        $response = CreateResponse::from([
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => $jsonResponse],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150],
        ]);

        $chat->method('create')->willReturn($response);
        $this->openai->method('chat')->willReturn($chat);

        $adapter = new OpenAIMenuExtractorAdapter($this->openai);
        $preview = $adapter->extract(base64_encode('fake-image'), 'image/jpeg');

        $this->assertInstanceOf(MenuImportPreview::class, $preview);
        $this->assertCount(1, $preview->categories);
        $this->assertSame('Starters', $preview->categories[0]->name);
        $this->assertCount(1, $preview->categories[0]->items);
        $this->assertSame('Soup', $preview->categories[0]->items[0]->name);
    }

    public function testExtractHandlesEmptyCategories(): void
    {
        $chat = $this->createMock(Chat::class);
        $response = CreateResponse::from([
            'id' => 'chatcmpl-test2',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => '{"categories": []}'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 10, 'total_tokens' => 60],
        ]);

        $chat->method('create')->willReturn($response);
        $this->openai->method('chat')->willReturn($chat);

        $adapter = new OpenAIMenuExtractorAdapter($this->openai);
        $preview = $adapter->extract(base64_encode('fake'), 'image/png');

        $this->assertCount(0, $preview->categories);
    }

    public function testExtractHandlesMalformedJson(): void
    {
        $chat = $this->createMock(Chat::class);
        $response = CreateResponse::from([
            'id' => 'chatcmpl-test3',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'not valid json {{{'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 10, 'total_tokens' => 60],
        ]);

        $chat->method('create')->willReturn($response);
        $this->openai->method('chat')->willReturn($chat);

        $adapter = new OpenAIMenuExtractorAdapter($this->openai);

        $this->expectException(\JsonException::class);
        $adapter->extract(base64_encode('fake'), 'image/jpeg');
    }
}
