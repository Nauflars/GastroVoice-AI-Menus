<?php

declare(strict_types=1);

namespace App\Tests\Unit\RestaurantManagement\Application\Handler;

use App\RestaurantManagement\Application\Command\UpdateRestaurantCommand;
use App\RestaurantManagement\Application\Command\UpdateRestaurantHandler;
use App\RestaurantManagement\Domain\Entity\Restaurant;
use App\RestaurantManagement\Domain\Repository\RestaurantRepositoryInterface;
use App\RestaurantManagement\Domain\ValueObject\SeatCapacity;
use App\RestaurantManagement\Domain\ValueObject\SlotDuration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateRestaurantHandlerTest extends TestCase
{
    private RestaurantRepositoryInterface&MockObject $repository;
    private UpdateRestaurantHandler $handler;
    private Uuid $restaurantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RestaurantRepositoryInterface::class);
        $this->handler = new UpdateRestaurantHandler($this->repository);
        $this->restaurantId = Uuid::v7();
    }

    private function makeRestaurant(): Restaurant
    {
        return new Restaurant(
            id: $this->restaurantId,
            name: 'Old Name',
            address: 'Old Address',
            phone: '+33000000000',
            seatCapacity: new SeatCapacity(20),
            slotDuration: new SlotDuration(15),
        );
    }

    public function testHandlerUpdatesAndSavesRestaurant(): void
    {
        $restaurant = $this->makeRestaurant();

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with($this->restaurantId)
            ->willReturn($restaurant);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with($restaurant);

        $command = new UpdateRestaurantCommand(
            restaurantId: $this->restaurantId,
            name: 'New Name',
            address: 'New Address',
            phone: '+33111111111',
            seatCapacity: 60,
            slotDurationMinutes: 30,
            timezone: 'Europe/Madrid',
        );

        ($this->handler)($command);

        self::assertSame('New Name', $restaurant->getName());
        self::assertSame(60, $restaurant->getSeatCapacity()->value());
    }

    public function testHandlerThrowsWhenRestaurantNotFound(): void
    {
        $this->repository
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\DomainException::class);

        ($this->handler)(new UpdateRestaurantCommand(
            restaurantId: $this->restaurantId,
            name: 'X', address: 'X', phone: 'X',
            seatCapacity: 10, slotDurationMinutes: 15, timezone: 'UTC',
        ));
    }
}
