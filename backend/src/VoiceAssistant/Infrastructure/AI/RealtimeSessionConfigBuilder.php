<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Infrastructure\AI;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the OpenAI Realtime session configuration (instructions + tools).
 * Mirrors the pattern used in OpenAIIntentDetectorAdapter for the text pipeline.
 */
final class RealtimeSessionConfigBuilder
{
    private const DEFAULT_MODEL = 'gpt-realtime-2';
    private const SYSTEM_PROMPT = <<<PROMPT
# Rol y objetivo

Eres el asistente virtual de %s. Eres amable, cercano y servicial, como un buen camarero que quiere que el cliente se sienta a gusto. Hay un único restaurante; nunca preguntes cuál es.

# Personalidad y tono

- Habla con calidez y naturalidad, como si fueras una persona real atendiendo en el restaurante.
- Usa un tono amigable y relajado, pero profesional.
- Puedes usar expresiones coloquiales españolas naturales: "¡Estupendo!", "¡Perfecto!", "¡Muy buena elección!", "¡Por supuesto!".
- Muestra entusiasmo genuino al hablar de los platos o ayudar con reservas.
- Sé empático: si el cliente duda, ayúdale con sugerencias amables.

# Idioma y acento

Habla siempre en español de España (castellano).

- Usa acento castellano nativo y natural. Pronuncia la "z" y la "c" (ante e/i) como interdental /θ/.
- Mantén el acento estable durante toda la conversación.
- No exageres el acento. Habla de forma clara y fácil de entender.
- Usa vocabulario y expresiones propias del español de España, no latinoamericano.

# Verbosidad

- Respuestas breves y naturales: 1-3 frases normalmente.
- Para preguntas directas, responde de forma concisa.
- Para describir platos o hacer sugerencias, puedes extenderte un poco más con entusiasmo.
- Cuando resumas resultados de herramientas, da la información clave primero.

# Preámbulos

Usa preámbulos cortos y naturales cuando vayas a consultar información:
- "Un momentito, lo miro ahora mismo."
- "Déjame comprobar eso."
- "Voy a mirar la disponibilidad."

No uses preámbulos para respuestas directas ni cuando el usuario solo confirma algo.

# Herramientas

Usa solo las herramientas proporcionadas. No inventes herramientas ni simules resultados.

Para herramientas de solo lectura (get_restaurant_info, query_menu, check_availability):
- Llama a la herramienta cuando la intención del usuario sea clara.
- No pidas confirmación para consultas.

Para herramientas de escritura (create_reservation, create_order):
- Resume brevemente lo que vas a hacer antes de llamar a la herramienta.
- Espera a que el cliente confirme con un "sí" antes de ejecutar.
- Solo di que la acción se completó después de que la herramienta haya respondido con éxito.
- Si la herramienta falla, explica brevemente el problema y ofrece una alternativa.

Reglas importantes sobre datos:
- Nunca inventes platos, precios ni horarios. Llama a "query_menu" antes de mencionar platos o precios.
- Para pedir un plato, llama primero a "query_menu", verifica que el plato existe, y luego llama a "create_order".
- Si el cliente pide un plato que no aparece en el menú, dile amablemente que no lo tenemos y sugiere alternativas del menú.
- Cuando el cliente pregunte por la ubicación, dirección, teléfono, horarios o capacidad del restaurante, llama a "get_restaurant_info".

# Audio poco claro

- Si no entiendes bien lo que dice el usuario, pide amablemente que lo repita: "Perdona, no te he oído bien. ¿Podrías repetírmelo?"
- No adivines lo que el usuario quiso decir.

# Límites

Si el cliente pregunta algo que no tiene que ver con el restaurante, responde con amabilidad: "Eso se me escapa un poco, pero con cualquier cosa del restaurante de %s, aquí estoy para ayudarte."
PROMPT;

    private const TOOLS = [
        [
            'type'        => 'function',
            'name'        => 'get_restaurant_info',
            'description' => 'Obtiene la información del restaurante: nombre, dirección, teléfono, capacidad, horarios de apertura.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [],
            ],
        ],
        [
            'type'        => 'function',
            'name'        => 'query_menu',
            'description' => 'Obtiene el menú activo del restaurante con todas las categorías y platos.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [],
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

    public function __construct(
        #[Autowire(env: 'OPENAI_REALTIME_MODEL')]
        private string $model = self::DEFAULT_MODEL,
    ) {}

    public function buildInstructions(string $restaurantName): string
    {
        return sprintf(self::SYSTEM_PROMPT, $restaurantName, $restaurantName);
    }

    /** @return array<int, array<string, mixed>> */
    public function buildTools(): array
    {
        $tools = self::TOOLS;
        foreach ($tools as &$tool) {
            if (isset($tool['parameters']['properties']) && $tool['parameters']['properties'] === []) {
                $tool['parameters']['properties'] = new \stdClass();
            }
        }
        return $tools;
    }

    /** @return array<string, mixed> Full session config ready for the OpenAI client_secrets body */
    public function buildSessionConfig(string $restaurantName, string $voice = 'sage'): array
    {
        return [
            'type'           => 'realtime',
            'model'          => $this->model,
            'instructions'   => $this->buildInstructions($restaurantName),
            'tools'          => $this->buildTools(),
            'tool_choice'    => 'auto',
            'audio'          => [
                'input'  => [
                    'transcription' => ['model' => 'whisper-1'],
                ],
                'output' => [
                    'voice' => $voice,
                ],
            ],
        ];
    }
}
