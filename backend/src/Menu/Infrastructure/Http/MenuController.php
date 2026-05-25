<?php

declare(strict_types=1);

namespace App\Menu\Infrastructure\Http;

use App\Menu\Application\Command\CreateCategoryCommand;
use App\Menu\Application\Command\CreateMenuItemCommand;
use App\Menu\Application\Command\DeactivateCategoryCommand;
use App\Menu\Application\Command\ToggleMenuItemAvailabilityCommand;
use App\Menu\Application\Command\UpdateCategoryCommand;
use App\Menu\Application\Query\GetActiveMenuQuery;
use App\Shared\Domain\Bus\CommandBusInterface;
use App\Shared\Domain\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class MenuController extends AbstractController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
    ) {}

    /** Public endpoint — no JWT required */
    #[Route('/menu/{restaurantId}', methods: ['GET'])]
    public function getMenu(string $restaurantId): JsonResponse
    {
        $menu = $this->queryBus->ask(new GetActiveMenuQuery($restaurantId));
        return $this->json($menu);
    }

    #[Route('/menu/categories', methods: ['POST'])]
    public function createCategory(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $restaurantId = $this->getRestaurantId();

        $category = $this->commandBus->dispatch(new CreateCategoryCommand(
            $restaurantId,
            $data['name'] ?? '',
            (int) ($data['displayOrder'] ?? 0),
        ));

        return $this->json(['id' => (string) $category->getId()], Response::HTTP_CREATED);
    }

    #[Route('/menu/categories/{id}', methods: ['PUT'])]
    public function updateCategory(string $id, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $this->commandBus->dispatch(new UpdateCategoryCommand(
            $id,
            $data['name'] ?? '',
            (int) ($data['displayOrder'] ?? 0),
        ));
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/menu/categories/{id}', methods: ['DELETE'])]
    public function deactivateCategory(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new DeactivateCategoryCommand($id));
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/menu/items', methods: ['POST'])]
    public function createItem(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $item = $this->commandBus->dispatch(new CreateMenuItemCommand(
            $data['categoryId'] ?? '',
            $data['name'] ?? '',
            $data['description'] ?? null,
            (float) ($data['price'] ?? 0),
            $data['currency'] ?? 'EUR',
        ));

        return $this->json(['id' => (string) $item->getId()], Response::HTTP_CREATED);
    }

    #[Route('/menu/items/{id}/toggle', methods: ['PATCH'])]
    public function toggleItemAvailability(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new ToggleMenuItemAvailabilityCommand($id));
        return $this->json(null, Response::HTTP_NO_CONTENT);
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
