<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Security;

use LectoresBeta\User\Account\Domain\Exception\ActivationEmailResendLimitReached;
use LectoresBeta\User\Account\Domain\Exception\ActivationEmailResentTooSoon;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Los tres límites del reenvío (`FEAT-USR-021` `RN-2`, `RN-3`, `RN-R3`).
 *
 * **Un endpoint público que dispara correo con el dominio de la plataforma en
 * el remitente es un amplificador de spam**, así que hay tres contadores y
 * cada uno para un abuso distinto:
 *
 * - por correo y minuto, contra el botón pulsado cinco veces seguidas;
 * - por correo y día, contra quien espacia las peticiones para saltarse el
 *   anterior — con solo el intervalo mínimo se pueden mandar mil cuatrocientos
 *   correos diarios a una dirección ajena;
 * - por origen, contra quien reparte esas peticiones entre muchas direcciones.
 *
 * **Se consumen siempre, exista o no la cuenta**, y de eso depende que el
 * endpoint no filtre nada: si solo contaran los envíos de verdad, la
 * diferencia entre agotar el límite y no agotarlo diría qué direcciones están
 * registradas.
 *
 * Vive en Infrastructure porque un limitador es almacenamiento con reloj, no
 * una regla de negocio. Lo que sí es de negocio —qué significa pasarse— son
 * las dos excepciones que lanza.
 */
final readonly class ActivationResendThrottle
{
    public function __construct(
        private RateLimiterFactory $byInterval,
        private RateLimiterFactory $byDay,
        private RateLimiterFactory $byAddress,
    ) {
    }

    public function check(string $email, string $clientIp): void
    {
        $key = hash('sha256', strtolower(trim($email)));

        // El orden importa: el tope diario se comprueba antes que el
        // intervalo porque es el que peor noticia da. Quien lo ha agotado
        // tiene que saberlo aunque además acabe de pulsar el botón, y no
        // enterarse un minuto después.
        $daily = $this->byDay->create($key)->consume();
        $interval = $this->byInterval->create($key)->consume();
        $address = $this->byAddress->create($clientIp)->consume();

        if (!$daily->isAccepted()) {
            throw ActivationEmailResendLimitReached::inSeconds(self::secondsUntil($daily->getRetryAfter()));
        }

        if (!$interval->isAccepted()) {
            throw ActivationEmailResentTooSoon::inSeconds(self::secondsUntil($interval->getRetryAfter()));
        }

        if (!$address->isAccepted()) {
            throw ActivationEmailResendLimitReached::inSeconds(self::secondsUntil($address->getRetryAfter()));
        }
    }

    private static function secondsUntil(\DateTimeInterface $moment): int
    {
        return max(0, $moment->getTimestamp() - time());
    }
}
