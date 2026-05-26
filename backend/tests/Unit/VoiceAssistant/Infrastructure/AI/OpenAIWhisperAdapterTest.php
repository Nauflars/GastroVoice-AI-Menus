<?php

declare(strict_types=1);

namespace App\Tests\Unit\VoiceAssistant\Infrastructure\AI;

use App\VoiceAssistant\Infrastructure\AI\OpenAIWhisperAdapter;
use OpenAI\Client;
use OpenAI\Resources\Audio;
use OpenAI\Responses\Audio\TranscriptionResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class OpenAIWhisperAdapterTest extends TestCase
{
    public function testTranscribeReturnsText(): void
    {
        $response = TranscriptionResponse::from([
            'task'     => 'transcribe',
            'language' => 'es',
            'duration' => 3.5,
            'text'     => 'Quiero reservar una mesa para cuatro personas',
        ]);

        $audio = $this->createMock(Audio::class);
        $audio->method('transcribe')->willReturn($response);

        $client = $this->createMock(Client::class);
        $client->method('audio')->willReturn($audio);

        // Create a temporary file to simulate an audio file
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_audio_');
        file_put_contents($tmpFile, 'fake audio content');

        try {
            $adapter = new OpenAIWhisperAdapter($client);
            $text = $adapter->transcribe($tmpFile);
            $this->assertSame('Quiero reservar una mesa para cuatro personas', $text);
        } finally {
            @unlink($tmpFile);
        }
    }
}
