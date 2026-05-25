<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

use App\Menu\Application\DTO\MenuImportPreview;
use App\Menu\Application\Port\AIMenuExtractorPort;
use Predis\ClientInterface as RedisClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ImportMenuHandler
{
    private const PREVIEW_TTL = 600; // 10 minutes

    public function __construct(
        private AIMenuExtractorPort $extractor,
        private RedisClientInterface $redis,
    ) {}

    public function __invoke(ImportMenuCommand $command): MenuImportPreview
    {
        $imageContent = file_get_contents($command->image->getPathname());
        if ($imageContent === false) {
            throw new \RuntimeException('Cannot read uploaded image.');
        }

        $base64 = base64_encode($imageContent);
        $mimeType = $command->image->getMimeType() ?? 'image/jpeg';

        $preview = $this->extractor->extract($base64, $mimeType);

        // Store preview in Redis with TTL
        $this->redis->setex(
            'menu_import:' . $preview->previewId,
            self::PREVIEW_TTL,
            json_encode($preview->toArray(), JSON_THROW_ON_ERROR),
        );

        return $preview;
    }
}
