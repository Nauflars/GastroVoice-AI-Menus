<?php

declare(strict_types=1);

namespace App\Reservations\Domain\Entity;

use App\Reservations\Domain\ValueObject\ReservationStatus;
use App\Reservations\Domain\ValueObject\TimeSlot;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'reservations')]
class Reservation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'uuid')]
    private Uuid $restaurantId;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'string', length: 5, name: 'time_slot')]
    private string $timeSlotValue;

    #[ORM\Column(type: 'integer')]
    private int $numPeople;

    #[ORM\Column(type: 'string', length: 255)]
    private string $customerName;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $customerPhone;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $customerEmail;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes;

    #[ORM\Column(type: 'string', length: 20, name: 'status')]
    private string $statusValue;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        Uuid $id,
        Uuid $restaurantId,
        \DateTimeImmutable $date,
        string $timeSlotValue,
        int $numPeople,
        string $customerName,
        ?string $customerPhone,
        ?string $customerEmail,
        ?string $notes,
        ReservationStatus $status,
    ) {
        $this->id = $id;
        $this->restaurantId = $restaurantId;
        $this->date = $date;
        $this->timeSlotValue = $timeSlotValue;
        $this->numPeople = $numPeople;
        $this->customerName = $customerName;
        $this->customerPhone = $customerPhone;
        $this->customerEmail = $customerEmail;
        $this->notes = $notes;
        if ($numPeople < 1) {
            throw new \DomainException('Number of people must be at least 1.');
        }
        $this->statusValue = $status->value;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function create(
        Uuid $restaurantId,
        \DateTimeImmutable $date,
        TimeSlot $timeSlot,
        int $numPeople,
        string $customerName,
        ?string $customerPhone = null,
        ?string $customerEmail = null,
        ?string $notes = null,
    ): self {
        return new self(
            Uuid::v7(),
            $restaurantId,
            $date,
            $timeSlot->toString(),
            $numPeople,
            $customerName,
            $customerPhone,
            $customerEmail,
            $notes,
            ReservationStatus::Pending,
        );
    }

    public function confirm(): void
    {
        $current = ReservationStatus::from($this->statusValue);
        $this->statusValue = $current->transitionTo(ReservationStatus::Confirmed)->value;
    }

    public function cancel(): void
    {
        $current = ReservationStatus::from($this->statusValue);
        $this->statusValue = $current->transitionTo(ReservationStatus::Cancelled)->value;
    }

    public function markNoShow(): void
    {
        $current = ReservationStatus::from($this->statusValue);
        $this->statusValue = $current->transitionTo(ReservationStatus::NoShow)->value;
    }

    public function getId(): Uuid { return $this->id; }
    public function getRestaurantId(): Uuid { return $this->restaurantId; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getTimeSlot(): TimeSlot { return TimeSlot::fromString($this->timeSlotValue); }
    public function getNumPeople(): int { return $this->numPeople; }
    public function getCustomerName(): string { return $this->customerName; }
    public function getCustomerPhone(): ?string { return $this->customerPhone; }
    public function getCustomerEmail(): ?string { return $this->customerEmail; }
    public function getNotes(): ?string { return $this->notes; }
    public function getStatus(): ReservationStatus { return ReservationStatus::from($this->statusValue); }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
