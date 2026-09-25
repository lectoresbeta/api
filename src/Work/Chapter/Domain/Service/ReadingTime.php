<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Service;

/**
 * Cuánto se tarda en leer un texto (`FEAT-WRK-013` `RN-5`).
 *
 * La Home lleva enseñando «8 min lectura» desde el primer diseño y no lo
 * calculaba nadie.
 *
 * **Doscientas palabras por minuto**, que es el extremo prudente del rango de
 * lectura silenciosa en prosa. Quedarse corto y quedarse largo no cuestan lo
 * mismo: quien reserva ocho minutos y necesita doce se siente engañado, y al
 * revés no pasa nada.
 *
 * **No se guarda en ningún sitio** (`RN-6`): se deriva al servir. Una cifra
 * derivada que se almacena es una cifra que se queda vieja, y esta cambia
 * cada vez que el texto cambia.
 *
 * Vive en `Domain` y no en el cliente porque repetida en cada pantalla habría
 * que buscarlas todas el día que la velocidad cambie (`W-18`).
 */
final class ReadingTime
{
    public const WORDS_PER_MINUTE = 200;

    /**
     * Redondea hacia arriba y nunca devuelve cero para un texto que existe:
     * «0 min lectura» no informa de nada. Un texto vacío sí son cero, porque
     * ahí no hay nada que leer.
     */
    public static function minutesFor(int $words): int
    {
        if ($words < 1) {
            return 0;
        }

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }
}
