<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\DTO;

/**
 * Cómo acabó la vuelta del proveedor: una sesión, y qué pasó para llegar a
 * ella.
 *
 * `isNewAccount` lo necesita la interfaz para decidir adónde manda a la
 * persona —al onboarding o a su muro— y **no cambia lo que la API hace**.
 * Distinguirlo aquí es lo que evita que el cliente lo deduzca mirando el
 * estado del onboarding, que es un detalle que puede cambiar.
 */
final readonly class OAuthOutcome
{
    public function __construct(
        public Session $session,
        public bool $isNewAccount,
        public bool $linkedToExistingAccount,
    ) {
    }
}
