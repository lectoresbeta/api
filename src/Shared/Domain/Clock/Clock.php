<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Clock;

/**
 * El tiempo, como puerto.
 *
 * Llamar a `new \DateTimeImmutable()` dentro de una regla de negocio la hace
 * imposible de probar: no se puede escribir un test de «el alias caduca a los
 * 30 días» si el dominio pregunta la hora al sistema operativo.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
