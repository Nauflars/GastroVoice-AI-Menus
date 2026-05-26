<?php

declare(strict_types=1);

namespace App\RestaurantManagement\Infrastructure\Http;

use App\Identity\Domain\Entity\AdminUser;
use App\RestaurantManagement\Application\Command\UpdateRestaurantCommand;
use App\RestaurantManagement\Application\Query\GetRestaurantQuery;
use App\Shared\Domain\Bus\CommandBusInterface;
use App\Shared\Domain\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/restaurant')]
final class RestaurantController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('', name: 'restaurant_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $this->getUser();
        $restaurantId = $user->getRestaurantId();

        if (null === $restaurantId) {
            return new JsonResponse(['error' => 'No restaurant assigned to this account.'], Response::HTTP_NOT_FOUND);
        }

        $restaurant = $this->queryBus->ask(new GetRestaurantQuery($restaurantId));

        return $this->json([
            'id' => (string) $restaurant->getId(),
            'name' => $restaurant->getName(),
            'address' => $restaurant->getAddress(),
            'phone' => $restaurant->getPhone(),
            'seatCapacity' => $restaurant->getSeatCapacity()->value(),
            'slotDurationMinutes' => $restaurant->getSlotDuration()->minutes(),
            'timezone' => $restaurant->getTimezone(),
            'openingHours' => array_map(
                static fn ($oh) => [
                    'id' => (string) $oh->getId(),
                    'dayOfWeek' => $oh->getDayOfWeek(),
                    'openTime' => $oh->getOpenTime(),
                    'closeTime' => $oh->getCloseTime(),
                    'isClosed' => $oh->isClosed(),
                ],
                $restaurant->getOpeningHours(),
            ),
            'createdAt' => $restaurant->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $restaurant->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('', name: 'restaurant_update', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $this->getUser();
        $restaurantId = $user->getRestaurantId();

        if (null === $restaurantId) {
            return new JsonResponse(['error' => 'No restaurant assigned to this account.'], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->commandBus->dispatch(new UpdateRestaurantCommand(
            restaurantId: $restaurantId,
            name: (string) ($data['name'] ?? ''),
            address: (string) ($data['address'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            seatCapacity: (int) ($data['seatCapacity'] ?? 0),
            slotDurationMinutes: (int) ($data['slotDurationMinutes'] ?? 0),
            timezone: (string) ($data['timezone'] ?? 'UTC'),
            openingHours: (array) ($data['openingHours'] ?? []),
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
