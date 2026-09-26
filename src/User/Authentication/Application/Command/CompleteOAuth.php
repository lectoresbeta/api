<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Command;

/**
 * La vuelta del proveedor (`FEAT-USR-002`).
 *
 * Las versiones legales viajan **opcionales**, y su ausencia solo es un error
 * cuando la operación implicaría **crear** una cuenta: a quien solo inicia
 * sesión no se le vuelve a pedir nada (`RN-4`).
 */
final readonly class CompleteOAuth
{
    public function __construct(
        public string $provider,
        public string $code,
        public ?string $redirectUri = null,
        public ?string $acceptedTermsVersion = null,
        public ?string $acceptedPrivacyVersion = null,
        public ?string $userAgent = null,
        public ?string $ipAddress = null,
    ) {
    }
}
