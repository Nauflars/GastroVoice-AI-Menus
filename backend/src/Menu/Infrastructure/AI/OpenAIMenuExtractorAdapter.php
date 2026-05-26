<?php

declare(strict_types=1);

namespace App\Menu\Infrastructure\AI;

use App\Menu\Application\DTO\MenuImportCategoryDTO;
use App\Menu\Application\DTO\MenuImportItemDTO;
use App\Menu\Application\DTO\MenuImportPreview;
use App\Menu\Application\Port\AIMenuExtractorPort;
use OpenAI\Client as OpenAIClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

final class OpenAIMenuExtractorAdapter implements AIMenuExtractorPort
{
    private const DEFAULT_MODEL = 'gpt-4.1';
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a menu extraction assistant. Extract all menu categories and items from the provided menu image.
Return a JSON object with this exact structure:
{
  "categories": [
    {
      "name": "Category Name",
      "items": [
        {
          "name": "Item name",
          "description": "Short description or null",
          "price": 12.50,
          "currency": "EUR",
          "allergens": ["gluten", "dairy"]
        }
      ]
    }
  ]
}
Rules:
- Extract ALL visible categories and items.
- Price must be a number (float). If not visible, use 0.
- Currency: use EUR unless another currency symbol is visible.
- Allergens: extract only if explicitly listed; otherwise empty array.
- Description: extract if present, otherwise null.
- Return ONLY the JSON object, no explanation.
PROMPT;

    public function __construct(
        private OpenAIClient $openai,
        #[Autowire(env: 'OPENAI_MENU_MODEL')]
        private string $model = self::DEFAULT_MODEL,
    ) {}

    public function extract(string $imageBase64, string $mimeType): MenuImportPreview
    {
        $response = $this->openai->chat()->create([
            'model' => $this->model,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'data:' . $mimeType . ';base64,' . $imageBase64,
                                'detail' => 'high',
                            ],
                        ],
                        ['type' => 'text', 'text' => 'Extract all menu items from this image.'],
                    ],
                ],
            ],
        ]);

        $content = $response->choices[0]->message->content ?? '{}';
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $categories = array_map(function (array $c): MenuImportCategoryDTO {
            $items = array_map(fn(array $i) => new MenuImportItemDTO(
                (string) ($i['name'] ?? 'Unknown'),
                isset($i['description']) ? (string) $i['description'] : null,
                (float) ($i['price'] ?? 0),
                (string) ($i['currency'] ?? 'EUR'),
                array_map('strval', $i['allergens'] ?? []),
            ), $c['items'] ?? []);

            return new MenuImportCategoryDTO((string) ($c['name'] ?? 'Unnamed'), $items);
        }, $data['categories'] ?? []);

        return new MenuImportPreview((string) Uuid::v7(), $categories);
    }
}
