<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Ese aviso no existe, o no es tuyo (`FEAT-NOT-009` `RN-6`).
 *
 * **Las dos cosas responden igual, y nunca `403`.** Un «no puedes tocar ese»
 * confirmaría que el aviso está ahí, y con un identificador al azar se podría
 * ir descubriendo qué le ha pasado a otra persona: que le han revocado un
 * acceso, que alguien le ha corregido un capítulo.
 */
final class NotificationNotFound extends \DomainException implements BusinessFailure
{
    public static function notification(): self
    {
        return new self('That notification does not exist.');
    }

    public function errorCode(): string
    {
        return 'NOTIFICATION_NOT_FOUND';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
