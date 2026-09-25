<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Handler;

use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\User\Authentication\Application\DTO\OAuthStart;
use LectoresBeta\User\Authentication\Application\Query\StartOAuth;
use LectoresBeta\User\Authentication\Application\Service\OAuthProviders;

/**
 * Empezar el viaje al proveedor (`FEAT-USR-002`).
 *
 * Devuelve la dirección **y un `state`**. El `state` es un valor
 * impredecible que el cliente guarda y compara con el que le devuelva el
 * proveedor antes de mandar el código aquí: es lo que impide que a alguien le
 * completen un flujo que no empezó.
 *
 * **Lo compara el cliente y no el servidor**, y conviene saberlo en vez de
 * suponerlo: esta API no tiene sesión de navegador donde guardarlo, y un
 * `state` que el servidor firmara pero no atara a un navegador concreto
 * parecería una comprobación sin serlo. Queda anotado como `U-3`.
 */
final readonly class StartOAuthHandler
{
    public function __construct(
        private OAuthProviders $providers,
        private SecureTokenFactory $tokens,
    ) {
    }

    public function __invoke(StartOAuth $query): OAuthStart
    {
        $provider = $this->providers->named($query->provider);
        $state = $this->tokens->create()->plain;

        return new OAuthStart(
            $provider->authorizationUrl($state, $query->redirectUri),
            $state,
        );
    }
}
