<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

use App\Menu\Domain\Repository\MenuItemRepositoryInterface;
use App\Menu\Domain\ValueObject\Price;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class UpdateMenuItemHandler
{
    public function __construct(private MenuItemRepositoryInterface $items) {}

    public function __invoke(UpdateMenuItemCommand $command): void
    {
        $item = $this->items->findById(Uuid::fromString($command->itemId));
        if ($item === null) {
            throw new \DomainException('Menu item not found.');
        }
        $item->updateDetails($command->name, $command->description, Price::of($command->price, $command->currency));
        $this->items->save($item);
    }
}
