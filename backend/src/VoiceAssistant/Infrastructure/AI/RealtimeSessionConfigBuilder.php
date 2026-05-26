<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Infrastructure\AI;

/**
 * Builds the OpenAI Realtime session configuration (instructions + tools).
 * Mirrors the pattern used in OpenAIIntentDetectorAdapter for the text pipeline.
 */
final class RealtimeSessionConfigBuilder
{
    private const SYSTEM_PROMPT = <<<PROMPT
Eres el asistente de voz de %s. Hay un único restaurante; nunca preguntes cuál es.

REGLAS — cúmplelas sin excepción:
- Respuestas MUY cortas (1-2 frases). Sin introducciones, sin despedidas largas.
- NUNCA inventes platos, precios ni horarios. Si necesitas esa información, llama al tool correspondiente ANTES de responder.
- Para mencionar platos o precios → llama "query_menu" primero.
- Para confirmar disponibilidad → llama "check_availability" primero.
- Para pedir un plato → llama "query_menu", verifica que existe, luego llama "create_order". Rechaza platos que no estén en el resultado.
- Antes de "create_reservation" o "create_order" → confirma los datos con el cliente en una sola frase corta y espera su "sí".
- Si el cliente pregunta algo ajeno al restaurante → di solo: "Solo puedo ayudarte con el menú, reservas y pedidos de %s."
- Habla siempre en español.
PROMPT;

    private const TOOLS = [
        [
            'type'        => 'function',
            'name'        => 'query_menu',
            'description' => 'Obtiene el menú activo del restaurante con todas las categorías y platos.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [],
                'required'   => [],
            ],
        ],
        [
            'type'        => 'function',
            'name'        => 'check_availability',
            'description' => 'Verifica si hay disponibilidad de mesas para una fecha, hora y número de personas.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'date'      => ['type' => 'string',  'description' => 'Fecha en formato YYYY-MM-DD'],
                    'timeSlot'  => ['type' => 'string',  'description' => 'Hora en formato HH:MM, p.ej. 13:00'],
                    'numPeople' => ['type' => 'integer', 'description' => 'Número de comensales'],
                ],
                'required' => ['date', 'timeSlot', 'numPeople'],
            ],
        ],
        [
            'type'        => 'function',
            'name'        => 'create_reservation',
            'description' => 'Crea una reserva de mesa en el restaurante.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'date'          => ['type' => 'string',  'description' => 'Fecha YYYY-MM-DD'],
                    'timeSlot'      => ['type' => 'string',  'description' => 'Hora HH:MM'],
                    'numPeople'     => ['type' => 'integer', 'description' => 'Número de personas'],
                    'customerName'  => ['type' => 'string',  'description' => 'Nombre del cliente'],
                    'customerPhone' => ['type' => 'string',  'description' => 'Teléfono del cliente (opcional)'],
                    'customerEmail' => ['type' => 'string',  'description' => 'Email del cliente (opcional)'],
                    'notes'         => ['type' => 'string',  'description' => 'Notas adicionales (opcional)'],
                ],
                'required' => ['date', 'timeSlot', 'numPeople', 'customerName'],
            ],
        ],
        [
            'type'        => 'function',
            'name'        => 'create_order',
            'description' => 'Crea un pedido de platos del menú.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'items' => [
                        'type'        => 'array',
                        'description' => 'Lista de platos a pedir',
                        'items'       => [
                            'type'       => 'object',
                            'properties' => [
                                'name'     => ['type' => 'string',  'description' => 'Nombre exacto del plato según el menú'],
                                'quantity' => ['type' => 'integer', 'description' => 'Cantidad'],
                            ],
                            'required' => ['name', 'quantity'],
                        ],
                    ],
                    'customerName' => ['type' => 'string', 'description' => 'Nombre del cliente'],
                ],
                'required' => ['items'],
            ],
        ],
    ];

    public function buildInstructions(string $restaurantName): string
    {
        return sprintf(self::SYSTEM_PROMPT, $restaurantName, $restaurantName);
    }

    /** @return array<int, array<string, mixed>> */
    public function buildTools(): array
    {
        return self::TOOLS;
    }

    /** @return array<string, mixed> Full session config ready for the OpenAI client_secrets body */
    public function buildSessionConfig(string $restaurantName): array
    {
        return [
            'type'           => 'realtime',
            'model'          => 'gpt-realtime-2',
            'instructions'   => $this->buildInstructions($restaurantName),
            'tools'          => $this->buildTools(),
            'tool_choice'    => 'auto',
            'audio'          => [
                'output' => ['voice' => 'marin'],
                'input'  => ['transcription' => ['model' => 'whisper-1']],
            ],
            'turn_detection' => ['type' => 'server_vad'],
        ];
    }
}
