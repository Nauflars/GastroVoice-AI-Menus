<?php

declare(strict_types=1);

namespace App\Tests\Unit\VoiceAssistant\Infrastructure\AI;

use App\VoiceAssistant\Infrastructure\AI\OpenAITTSAdapter;
use OpenAI\Client;
use OpenAI\Resources\Audio;
use PHPUnit\Framework\TestCase;

final class OpenAITTSAdapterTest extends TestCase
{
    public function testSynthesizeCreatesAudioFile(): void
    {
        $fakeAudioContent = 'fake mp3 binary';

        $audio = $this->createMock(Audio::class);
        $audio->method('speech')->willReturn($fakeAudioContent);

        $client = $this->createMock(Client::class);
        $client->method('audio')->willReturn($audio);

        $adapter = new OpenAITTSAdapter($client);
        $path = $adapter->synthesize('Hola, bienvenido al restaurante');

        try {
            $this->assertFileExists($path);
            $this->assertStringEndsWith('.mp3', $path);
            $this->assertSame($fakeAudioContent, file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }
}
