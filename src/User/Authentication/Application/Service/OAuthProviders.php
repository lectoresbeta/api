<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Service;

use LectoresBeta\User\Authentication\Application\Port\OAuthProvider;
use LectoresBeta\User\Authentication\Domain\Exception\ExternalSignInFailed;

/**
 * Los proveedores externos que hay, por su nombre en la URL.
 *
 * La ruta lleva el proveedor como parámetro para que Facebook y LinkedIn
 * encajen sin cambiar el contrato cuando se retomen. Lo que decide cuáles
 * existen es **la configuración**, no un `match` en el código: añadir uno es
 * escribir su adaptador y una línea en `config/services.yaml`, y ni
 * `Application` ni `Domain` se enteran.
 *
 * Un nombre que no está responde `404` y no `422`: el cliente ha pedido una
 * puerta que no existe, no ha escrito mal un campo.
 */
final readonly class OAuthProviders
{
    /**
     * @param iterable<OAuthProvider> $providers
     */
    public function __construct(private iterable $providers)
    {
    }

    public function named(string $name): OAuthProvider
    {
        foreach ($this->providers as $provider) {
            if (0 === strcasecmp($provider->handles()->value, $name)) {
                return $provider;
            }
        }

        throw ExternalSignInFailed::unknownProvider();
    }
}
