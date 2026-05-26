<?php

declare(strict_types=1);

namespace App\Tests\Unit\Menu\Application\Handler;

use App\Menu\Application\Command\ImportMenuCommand;
use App\Menu\Application\Command\ImportMenuHandler;
use App\Menu\Application\DTO\MenuImportCategoryDTO;
use App\Menu\Application\DTO\MenuImportItemDTO;
use App\Menu\Application\DTO\MenuImportPreview;
use App\Menu\Application\Port\AIMenuExtractorPort;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Predis\ClientInterface as RedisClientInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImportMenuHandlerTest extends TestCase
{
    private AIMenuExtractorPort&MockObject $extractor;
    private RedisClientInterface&MockObject $redis;
    private ImportMenuHandler $handler;

    protected function setUp(): void
    {
        $this->extractor = $this->createMock(AIMenuExtractorPort::class);
        $this->redis = $this->createMock(RedisClientInterface::class);
        $this->handler = new ImportMenuHandler($this->extractor, $this->redis);
    }

    public function testHandlerCallsExtractorAndStoresPreview(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_img');
        file_put_contents($tmpFile, 'fake-image-content');

        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->method('getPathname')->willReturn($tmpFile);
        $uploadedFile->method('getClientMimeType')->willReturn('image/jpeg');

        $preview = new MenuImportPreview('preview-123', [
            new MenuImportCategoryDTO('Starters', [
                new MenuImportItemDTO('Soup', 'Hot', 8.50, 'EUR', []),
            ]),
        ]);

        $this->extractor->expects($this->once())
            ->method('extract')
            ->with($this->isType('string'), 'image/jpeg')
            ->willReturn($preview);

        $this->redis->expects($this->once())
            ->method('setex')
            ->with(
                'menu_import:preview-123',
                600,
                $this->isType('string'),
            );

        $command = new ImportMenuCommand($uploadedFile, 'restaurant-1');
        $result = ($this->handler)($command);

        $this->assertSame('preview-123', $result->previewId);
        $this->assertCount(1, $result->categories);

        @unlink($tmpFile);
    }
}
