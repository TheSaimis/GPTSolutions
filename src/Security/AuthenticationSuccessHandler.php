<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();
        $jwt = $this->jwtManager->create($user);

        $response = new JsonResponse([
            'token' => $jwt,
        ]);

        $response->headers->setCookie(
            Cookie::create('auth')
                ->withValue($jwt)
                ->withHttpOnly(true)
                ->withSecure(false) // true in production HTTPS
                ->withSameSite('lax')
                ->withPath('/')
                ->withExpires(new \DateTimeImmutable('+30 days'))
        );

        return $response;
    }
}