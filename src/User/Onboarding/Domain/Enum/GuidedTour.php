<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Domain\Enum;

/**
 * Los tours que existen (`FEAT-USR-026`).
 *
 * Un enum y no una cadena suelta: el identificador acaba en una tabla y en
 * una URL, y una errata crearía un tour fantasma que nadie ha visto nunca y
 * que se enseñaría para siempre.
 *
 * Que sea un enum **no** obliga a una columna por tour ni a una migración por
 * tour: la tabla sigue teniendo una fila por `(persona, tour)`. Añadir uno es
 * un caso más aquí.
 */
enum GuidedTour: string
{
    /**
     * Los cuatro globos de la Home. El único que existe hoy.
     */
    case HOME = 'home';

    public static function orHome(?string $tourId): self
    {
        if (null === $tourId || '' === $tourId) {
            return self::HOME;
        }

        return self::tryFrom(strtolower($tourId)) ?? throw new \InvalidArgumentException('There is no such tour.');
    }

    /**
     * Cuántos pasos tiene, para no dar por válido un abandono en el paso 9 de
     * un tour de cuatro: la métrica de `RN-4` es lo único que dice si el tour
     * funciona, y un dato inventado la estropea en silencio.
     */
    public function steps(): int
    {
        return match ($this) {
            self::HOME => 4,
        };
    }
}
