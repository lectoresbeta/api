<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Port;

use LectoresBeta\User\Account\Domain\Enum\AuthProvider;
use LectoresBeta\User\Authentication\Application\DTO\ExternalIdentity;

/**
 * Un proveedor de identidad externo (`FEAT-USR-002`).
 *
 * El puerto existe para que añadir Facebook o LinkedIn cuando se retomen
 * (`FEAT-USR-003`, `FEAT-USR-019`) sea escribir un adaptador y una línea de
 * configuración, sin tocar `Application` ni `Domain`. Es un criterio de
 * aceptación de la ficha, no una aspiración.
 *
 * Dos métodos, y la frontera entre ellos es la del navegador: el primero dice
 * adónde mandar a la persona, el segundo canjea lo que trae de vuelta.
 */
interface OAuthProvider
{
    public function handles(): AuthProvider;

    public function authorizationUrl(string $state, ?string $redirectUri): string;

    /**
     * Canjea el código por la identidad.
     *
     * `$redirectUri` viaja porque el proveedor exige que sea **la misma** que
     * se usó al empezar, y quien la eligió fue el cliente: una aplicación
     * web y una móvil vuelven a sitios distintos.
     *
     * @throws \LectoresBeta\User\Authentication\Domain\Exception\ExternalSignInFailed
     */
    public function identify(string $code, ?string $redirectUri): ExternalIdentity;
}
