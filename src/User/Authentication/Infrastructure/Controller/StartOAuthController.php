<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Infrastructure\Controller;

use LectoresBeta\User\Authentication\Application\Handler\StartOAuthHandler;
use LectoresBeta\User\Authentication\Application\Query\StartOAuth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/auth/oauth/{provider}` (`FEAT-USR-002`).
 *
 * Devuelve la dirección del proveedor **en JSON**, en vez de redirigir.
 * Redirigir obligaría al cliente a sacar a la persona de la aplicación por
 * una respuesta de la API, y una aplicación móvil no puede seguir una
 * redirección así. Quien la abre es el cliente, que además es quien sabe
 * dónde.
 *
 * El `state` viaja con ella: el cliente lo guarda y lo compara con el que le
 * devuelva el proveedor antes de mandar el código.
 */
#[AsController]
final readonly class StartOAuthController
{
    public function __construct(private StartOAuthHandler $start)
    {
    }

    public function __invoke(Request $request, string $provider): Response
    {
        $start = ($this->start)(new StartOAuth(
            $provider,
            $request->query->getString('redirectUri') ?: null,
        ));

        return new JsonResponse([
            'authorizationUrl' => $start->authorizationUrl,
            'state' => $start->state,
        ]);
    }
}
