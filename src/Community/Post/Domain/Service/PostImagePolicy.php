<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\Service;

/**
 * Las cifras de la imagen adjunta a una publicación (`FEAT-COM-002` `RN-5`).
 *
 * Juntas y con nombre porque son decisiones, no constantes técnicas.
 */
final class PostImagePolicy
{
    /**
     * **El límite lo aplica el servidor.** Que el modal lo anuncie es una
     * cortesía, no un control.
     *
     * Más que un avatar —una foto de un muro se mira entera, no en un
     * círculo de 360 píxeles— y muy lejos de lo que sube un móvil sin pensar.
     */
    public const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * El lado mayor de lo que se guarda.
     *
     * La tarjeta del muro no pasa de unos 700 píxeles de ancho; al doble se
     * ve nítida en una pantalla de densidad doble, y guardar los 4.000 que
     * trae una foto de móvil solo multiplica el almacén.
     */
    public const MAX_SIDE = 1440;
}
