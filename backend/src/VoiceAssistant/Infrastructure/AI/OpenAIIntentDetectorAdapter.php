<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Infrastructure\AI;

use App\VoiceAssistant\Application\Port\IntentDetectionResult;
use App\VoiceAssistant\Application\Port\IntentDetectorPort;
use App\VoiceAssistant\Domain\ValueObject\Intent;
use OpenAI\Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OpenAIIntentDetectorAdapter implements IntentDetectorPort
{
    private const DEFAULT_MODEL = 'gpt-4.1-mini';
    private const SYSTEM_PROMPT = <<<PROMPT
You are a telephone assistant for a restaurant. Your job is to detect the customer's intent and extract relevant data.

Respond ONLY with valid JSON in this exact structure:
{
  "intent": "create_reservation|check_availability|create_order|query_menu|unknown",
  "data": {
    "date": "YYYY-MM-DD or null",
    "timeSlot": "HH:MM or null",
    "numPeople": number or null,
    "customerName": "string or null",
    "customerPhone": "string or null",
    "tableNumber": "string or null",
    "lines": [] 
  },
  "missing_fields": ["list of required fields not yet provided"],
  "reply": "Natural language reply to read to the customer in Spanish"
}

Always respond in Spanish. Be friendly and helpful. If data is missing, ask for it in the reply field.
IMPORTANT: When answering questions about the menu, use ONLY the items listed in the restaurant context provided. Do not invent or assume menu items. List the actual item names and prices from the menu.
PROMPT;

    public function __construct(
        private Client $openai,
        #[Autowire(env: 'OPENAI_INTENT_MODEL')]
        private string $model = self::DEFAULT_MODEL,
    ) {}

    public function detect(array $messages, array $restaurantContext): IntentDetectionResult
    {
        $systemMessage = self::SYSTEM_PROMPT;
        $systemMessage .= "\n\nToday's date is: " . date('Y-m-d') . " (use this year when the customer mentions a date without a year).";
        if (!empty($restaurantContext)) {
            $systemMessage .= "\n\nRestaurant context: " . json_encode($restaurantContext);
        }

        $response = $this->openai->chat()->create([
            'model'           => $this->model,
            'response_format' => ['type' => 'json_object'],
            'messages'        => array_merge(
                [['role' => 'system', 'content' => $systemMessage]],
                $messages,
            ),
            'max_tokens' => 500,
        ]);

        $rawContent = $response->choices[0]->message->content ?? '{}';

        try {
            $parsed = json_decode($rawContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new IntentDetectionResult(
                Intent::Unknown,
                [],
                [],
                'Lo siento, no le he entendido bien. ¿Podría repetirlo?',
            );
        }

        $intent = Intent::tryFrom($parsed['intent'] ?? '') ?? Intent::Unknown;

        return new IntentDetectionResult(
            $intent,
            $parsed['data'] ?? [],
            $parsed['missing_fields'] ?? [],
            $parsed['reply'] ?? '',
        );
    }
}
