<?php

declare(strict_types=1);

namespace App\Reservations\Infrastructure\Http;

use App\Reservations\Application\Command\CancelReservationCommand;
use App\Reservations\Application\Command\CreateReservationCommand;
use App\Reservations\Application\Query\CheckAvailabilityQuery;
use App\Reservations\Application\Query\GetReservationsQuery;
use App\Reservations\Domain\Exception\SlotFullException;
use App\Reservations\Domain\Repository\ReservationRepositoryInterface;
use App\Shared\Domain\Bus\CommandBusInterface;
use App\Shared\Domain\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/reservations')]
final class ReservationController extends AbstractController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private ReservationRepositoryInterface $reservationRepo,
    ) {}

    #[Route('/availability', methods: ['GET'])]
    public function availability(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId();
        $result = $this->queryBus->ask(new CheckAvailabilityQuery(
            $restaurantId,
            $request->query->get('date', date('Y-m-d')),
            $request->query->get('timeSlot', '12:00'),
        ));
        return $this->json($result);
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId();
        $items = $this->queryBus->ask(new GetReservationsQuery(
            $restaurantId,
            $request->query->get('date'),
            $request->query->get('status'),
        ));
        return $this->json($items);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $restaurantId = $this->getRestaurantId();

        try {
            $reservation = $this->commandBus->dispatch(new CreateReservationCommand(
                $restaurantId,
                $data['date'] ?? '',
                $data['timeSlot'] ?? '',
                (int) ($data['numPeople'] ?? 1),
                $data['customerName'] ?? '',
                $data['customerPhone'] ?? null,
                $data['customerEmail'] ?? null,
                $data['notes'] ?? null,
            ));
            return $this->json($this->serializeReservation($reservation), Response::HTTP_CREATED);
        } catch (SlotFullException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $reservation = $this->reservationRepo->findById(Uuid::fromString($id));
        if ($reservation === null) {
            return $this->json(['code' => 'not_found', 'message' => 'Reservation not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeReservation($reservation));
    }

    #[Route('/{id}/cancel', methods: ['POST'])]
    public function cancel(string $id): JsonResponse
    {
        try {
            $this->commandBus->dispatch(new CancelReservationCommand($id));
            $reservation = $this->reservationRepo->findById(Uuid::fromString($id));
            return $this->json($this->serializeReservation($reservation));
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function serializeReservation(object $reservation): array
    {
        return [
            'id'              => (string) $reservation->getId(),
            'reservationDate' => $reservation->getDate()->format('Y-m-d'),
            'timeSlot'        => $reservation->getTimeSlot()->toString(),
            'numPeople'       => $reservation->getNumPeople(),
            'customerName'    => $reservation->getCustomerName(),
            'customerPhone'   => $reservation->getCustomerPhone(),
            'customerEmail'   => $reservation->getCustomerEmail(),
            'status'          => $reservation->getStatus()->value,
            'notes'           => $reservation->getNotes(),
            'createdAt'       => $reservation->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function getRestaurantId(): string
    {
        /** @var \App\Identity\Domain\Entity\AdminUser $user */
        $user = $this->getUser();
        $restaurantId = $user->getRestaurantId();
        if ($restaurantId === null) {
            throw new \DomainException('No restaurant assigned to this user.');
        }
        return (string) $restaurantId;
    }
}
