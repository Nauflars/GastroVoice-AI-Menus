<?php

declare(strict_types=1);

namespace App\RestaurantManagement\Domain\Entity;

use App\RestaurantManagement\Domain\ValueObject\SeatCapacity;
use App\RestaurantManagement\Domain\ValueObject\SlotDuration;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'restaurants')]
class Restaurant
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 500)]
    private string $address;

    #[ORM\Column(type: 'string', length: 50)]
    private string $phone;

    #[ORM\Embedded(class: SeatCapacity::class, columnPrefix: 'seat_')]
    private SeatCapacity $seatCapacity;

    #[ORM\Embedded(class: SlotDuration::class, columnPrefix: 'slot_')]
    private SlotDuration $slotDuration;

    #[ORM\Column(type: 'string', length: 100)]
    private string $timezone;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
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
