<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Service;

/**
 * Las cifras de la foto de perfil (`FEAT-USR-037`).
 *
 * Están juntas y con nombre porque son decisiones, no constantes técnicas:
 * cuánto se admite subir, cuánto se guarda y con qué tamaño.
 */
final class AvatarPolicy
{
    /**
     * `RN-1`. **El límite lo aplica el servidor**: que el modal lo anuncie es
     * una cortesía, no un control.
     */
    public const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * `RN-4`. El diseño referencia 180 px y aquí se guarda el doble: una
     * pantalla de densidad doble enseña el avatar a 360 px reales, y
     * guardarlo a 180 lo vería borroso justo quien tiene mejor pantalla.
     *
     * El recorte circular es **una máscara de presentación**: lo guardado es
     * cuadrado.
     */
    public const SIDE = 360;

    /**
     * `RN-4c`. El original es material de trabajo del editor, así que se
     * guarda entero pero **no tal como viene del móvil**: una foto de 12
     * megapíxeles no aporta nada a un recorte de 360 y multiplica lo que se
     * almacena, que ya es el doble por conservar las dos.
     */
    public const ORIGINAL_MAX_SIDE = 1600;
}
