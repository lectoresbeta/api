<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Shared;

use LectoresBeta\Shared\Domain\Clock\Clock;

/**
 * Reloj detenido para los tests de dominio.
 *
 * Vive en tests y no en src porque no es una implementación del sistema: es
 * una herramienta de prueba.
 */
final class FrozenClock implements Clock
{
    public function __construct(private \DateTimeImmutable $now)
    {
    }

    public static function at(string $iso8601): self
    {
        return new self(new \DateTimeImmutable($iso8601, new \DateTimeZone('UTC')));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
