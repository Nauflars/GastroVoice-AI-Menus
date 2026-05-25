<?php

declare(strict_types=1);

namespace App\RestaurantManagement\Domain\Entity;

use App\RestaurantManagement\Domain\ValueObject\SeatCapacity;
use App\RestaurantManagement\Domain\ValueObject\SlotDuration;
use Symfony\Component\Uid\Uuid;

class Restaurant
{
    private Uuid $id;
    private string $name;
    private string $address;
    private string $phone;
    private SeatCapacity $seatCapacity;
    private SlotDuration $slotDuration;
    private string $timezone;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
    /** @var list<OpeningHour> */
    private array $openingHours = [];

    public function __construct(
        Uuid $id,
        string $name,
        string $address,
        string $phone,
        SeatCapacity $seatCapacity,
        SlotDuration $slotDuration,
        string $timezone = 'UTC',
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->address = $address;
        $this->phone = $phone;
        $this->seatCapacity = $seatCapacity;
        $this->slotDuration = $slotDuration;
        $this->timezone = $timezone;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function update(
        string $name,
        string $address,
        string $phone,
        SeatCapacity $seatCapacity,
        SlotDuration $slotDuration,
        string $timezone,
    ): void {
        $this->name = $name;
        $this->address = $address;
        $this->phone = $phone;
        $this->seatCapacity = $seatCapacity;
        $this->slotDuration = $slotDuration;
        $this->timezone = $timezone;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setOpeningHours(array $openingHours): void
    {
        $this->openingHours = $openingHours;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getSeatCapacity(): SeatCapacity
    {
        return $this->seatCapacity;
    }

    public function getSlotDuration(): SlotDuration
    {
        return $this->slotDuration;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return list<OpeningHour> */
    public function getOpeningHours(): array
    {
        return $this->openingHours;
    }
}
