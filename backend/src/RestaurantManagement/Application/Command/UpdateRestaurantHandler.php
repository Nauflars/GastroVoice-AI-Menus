<?php

declare(strict_types=1);

namespace App\RestaurantManagement\Application\Command;

use App\RestaurantManagement\Domain\Entity\OpeningHour;
use App\RestaurantManagement\Domain\Repository\RestaurantRepositoryInterface;
use App\RestaurantManagement\Domain\ValueObject\SeatCapacity;
use App\RestaurantManagement\Domain\ValueObject\SlotDuration;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class UpdateRestaurantHandler
{
    public function __construct(
        private readonly RestaurantRepositoryInterface $repository,
    ) {
    }

    public function __invoke(UpdateRestaurantCommand $command): void
    {
        $restaurant = $this->repository->findById($command->restaurantId);
        if (null === $restaurant) {
            throw new \DomainException(\sprintf('Restaurant "%s" not found.', $command->restaurantId));
        }

        $restaurant->update(
            name: $command->name,
            address: $command->address,
            phone: $command->phone,
            seatCapacity: new SeatCapacity($command->seatCapacity),
            slotDuration: new SlotDuration($command->slotDurationMinutes),
            timezone: $command->timezone,
        );

        $openingHours = [];
        foreach ($command->openingHours as $hourData) {
            $openingHours[] = new OpeningHour(
                id: Uuid::v7(),
                restaurantId: $restaurant->getId(),
                dayOfWeek: $hourData['dayOfWeek'],
                isClosed: $hourData['isClosed'],
                openTime: $hourData['openTime'] ?? null,
                closeTime: $hourData['closeTime'] ?? null,
            );
        }
        $restaurant->setOpeningHours($openingHours);

        $this->repository->save($restaurant);
    }
}
