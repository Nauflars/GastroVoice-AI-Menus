<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * Login is handled by LexikJWTAuthenticationBundle via security.yaml.
     * This action is unreachable but serves as documentation / route definition.
     */
    #[Route('/login', name: 'auth_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Handled by LexikJWTAuthenticationBundle
        throw new \LogicException('This should never be reached.');
    }

    /**
     * Refresh token endpoint — client sends the current JWT to get a new one.
     */
    #[Route('/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $token->getUser();
        if (null === $user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $jwt = $this->jwtManager->create($user);

        return new JsonResponse(['token' => $jwt]);
    }
}
