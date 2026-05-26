<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

use App\Menu\Application\DTO\MenuImportCategoryDTO;
use App\Menu\Application\DTO\MenuImportItemDTO;
use App\Menu\Application\DTO\MenuImportPreview;
use App\Menu\Domain\Service\MenuImportService;
use Predis\ClientInterface as RedisClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ConfirmMenuImportHandler
{
    public function __construct(
        private RedisClientInterface $redis,
        private MenuImportService $importService,
    ) {}

    public function __invoke(ConfirmMenuImportCommand $command): void
    {
        $key = 'menu_import:' . $command->previewId;
        $raw = $this->redis->get($key);

        if ($raw === null) {
            throw new \DomainException('Import preview expired or not found. Please re-upload the menu image.');
        }

        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $categories = array_map(function (array $c): MenuImportCategoryDTO {
            $items = array_map(fn(array $i) => new MenuImportItemDTO(
                $i['name'],
                $i['description'] ?? null,
                (float) $i['price'],
                $i['currency'] ?? 'EUR',
                $i['allergens'] ?? [],
            ), $c['items'] ?? []);

            return new MenuImportCategoryDTO($c['name'], $items);
        }, $data['categories'] ?? []);

        $preview = new MenuImportPreview($command->previewId, $categories);
        $this->importService->persist(Uuid::fromString($command->restaurantId), $preview);

        $this->redis->del([$key]);
    }
}
