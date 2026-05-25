<?php

declare(strict_types=1);

namespace App\Reservations\Domain\Entity;

use App\Reservations\Domain\ValueObject\ReservationStatus;
use App\Reservations\Domain\ValueObject\TimeSlot;
use Symfony\Component\Uid\Uuid;

class Reservation
{
    private \DateTimeImmutable $createdAt;
    private string $statusValue;

    private function __construct(
        private Uuid $id,
        private Uuid $restaurantId,
        private \DateTimeImmutable $date,
        private string $timeSlotValue,
        private int $numPeople,
        private string $customerName,
        private ?string $customerPhone,
        private ?string $customerEmail,
        private ?string $notes,
        ReservationStatus $status,
    ) {
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
