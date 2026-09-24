<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\DTO;

/**
 * Cómo ha quedado el nombre (`FEAT-USR-034`).
 *
 * Lleva `changeableOn` porque el formulario tiene que poder desactivarse
 * **sin fallar primero**: quien acaba de cambiar su nombre necesita saber que
 * no podrá volver a hacerlo hasta esa fecha, y descubrirlo con un error
 * treinta días después es descubrirlo tarde.
 */
final readonly class UsernameChange
{
    public function __construct(
        public string $username,
        public ?string $previousUsername,
        public ?\DateTimeImmutable $aliasExpiresAt,
        public \DateTimeImmutable $changeableOn,
    ) {
    }
}
