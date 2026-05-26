<?php

declare(strict_types=1);

namespace App\Menu\Infrastructure\Http;

use App\Menu\Application\Command\ConfirmMenuImportCommand;
use App\Menu\Application\Command\ImportMenuCommand;
use App\Shared\Domain\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/menu/import')]
final class MenuImportController extends AbstractController
{
    public function __construct(private CommandBusInterface $commandBus) {}

    #[Route('', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $image = $request->files->get('image');
        if ($image === null) {
            return $this->json(['error' => 'No image uploaded.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $clientMime = $image->getClientMimeType() ?? '';
        if (!in_array($clientMime, $allowedMimes, true)) {
            return $this->json(['error' => 'Unsupported image format. Please upload a JPG, PNG, WEBP or GIF file.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $restaurantId = $this->getRestaurantId();

        try {
            $preview = $this->commandBus->dispatch(new ImportMenuCommand($restaurantId, $image));
            return $this->json($preview->toArray(), Response::HTTP_OK);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Failed to analyze image: ' . $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/{previewId}/confirm', methods: ['POST'])]
    public function confirm(string $previewId): JsonResponse
    {
        $restaurantId = $this->getRestaurantId();

        try {
            $this->commandBus->dispatch(new ConfirmMenuImportCommand($previewId, $restaurantId));
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_GONE);
        }
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
