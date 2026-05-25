<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

use App\Menu\Domain\Repository\MenuItemRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ToggleMenuItemAvailabilityHandler
{
    public function __construct(private MenuItemRepositoryInterface $items) {}

    public function __invoke(ToggleMenuItemAvailabilityCommand $command): void
    {
        $item = $this->items->findById(Uuid::fromString($command->itemId));
        if ($item === null) {
            throw new \DomainException('Menu item not found.');
        }
        $item->toggleAvailability();
        $this->items->save($item);
    }
}
