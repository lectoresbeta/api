<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Domain\Service;

/**
 * Las cifras del gancho de reactivación (`FEAT-CRD-019`).
 *
 * **La decisión que hace controlable este mecanismo es que se reparte por
 * cupo y no por plazo individual** (`RN-2`). Con un disparador por
 * antigüedad, la deuda que se puede generar depende de cuánta gente cruce el
 * umbral, que es una cifra que nadie controla. Con un cupo, el techo se sabe
 * de antemano: cupo × precio máximo, y apagarlo es poner un número a cero.
 *
 * Con tres por semana y un precio máximo de 20, la emisión no puede pasar de
 * 60 créditos semanales.
 */
final class OverdraftPolicy
{
    /**
     * `RN-2b` (`C-42`). Tres por semana es la cifra de partida, y está en
     * configuración porque es exactamente la palanca que habrá que mover.
     */
    public const DEFAULT_QUOTA = 3;

    /**
     * `RN-2c` (`C-43`). Por debajo de 30 días no está dormido: está de
     * vacaciones.
     */
    public const MIN_IDLE_DAYS = 30;

    /**
     * Y por encima de 180 la probabilidad de volver cae tanto que el cupo
     * rinde más en gente más reciente. Quien lleva dos años fuera no vuelve
     * por un correo.
     */
    public const MAX_IDLE_DAYS = 180;

    /**
     * La semana ISO, `2026-W39`.
     *
     * Se guarda escrita y no se calcula al contar: así el cupo de un periodo
     * es una igualdad sobre una columna, y no un rango de fechas que hay que
     * recorrer.
     */
    public static function periodOf(\DateTimeImmutable $moment): string
    {
        return $moment->format('o-\WW');
    }

    /**
     * Hasta cuándo sirve la elegibilidad concedida en ese momento.
     *
     * **Caduca con el periodo y no se acumula** (`RN-2`): un cupo que se
     * arrastra deja de ser un techo. Si nadie corrigió a ese autor esa
     * semana, la semana siguiente empieza de cero.
     */
    public static function expiryOf(\DateTimeImmutable $grantedAt): \DateTimeImmutable
    {
        // El lunes siguiente a las 00:00, que es donde acaba la semana ISO
        // del momento en que se concede.
        return $grantedAt->modify('monday next week')->setTime(0, 0);
    }

    public static function idleSince(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->modify(\sprintf('-%d days', self::MIN_IDLE_DAYS));
    }

    public static function idleUntil(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->modify(\sprintf('-%d days', self::MAX_IDLE_DAYS));
    }
}
