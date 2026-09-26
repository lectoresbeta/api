<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Service;

/**
 * Las cifras del fondo de la página de autor (`FEAT-USR-016`).
 *
 * La hermana de `AvatarPolicy`, con dos diferencias que salen de lo que cada
 * imagen es.
 */
final class CoverPolicy
{
    /**
     * El doble que un avatar. Un fondo ocupa el ancho de la pantalla, y con
     * dos megas quien suba una foto de paisaje se encuentra un rechazo
     * razonable en el avatar y absurdo aquí.
     */
    public const MAX_BYTES = 4 * 1024 * 1024;

    /**
     * **No se recorta** (`RN-5`), al revés que el avatar: un fondo es un
     * banner, no un retrato, y su proporción la decide quien diseña la
     * pantalla. Lo que sí se hace es acotar el lado mayor, porque una foto de
     * doce megapíxeles no mejora un fondo y multiplica lo que se almacena.
     */
    public const MAX_SIDE = 1600;
}
