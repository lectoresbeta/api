<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Security;

use LectoresBeta\User\Account\Domain\Exception\PasswordResetLimitReached;
use LectoresBeta\User\Account\Domain\Exception\PasswordResetRequestedTooSoon;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Los tres límites de «he olvidado mi contraseña» (`FEAT-USR-007` `RN-3`).
 *
 * La misma forma que el reenvío de activación y por las mismas razones, más
 * una que es propia de este: **una avalancha de correos «restablece tu
 * contraseña» es como se prepara un engaño**. Quien recibe veinte seguidos
 * acaba pulsando el enlace del veintiuno, y ese puede no ser el nuestro.
 *
 * Se consumen siempre, exista o no la cuenta (`RN-4`): si solo contaran los
 * envíos de verdad, agotar el límite diría que esa dirección está registrada
 * y la respuesta indistinguible se caería por la puerta de atrás.
 */
final readonly class PasswordResetThrottle
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

        $daily = $this->byDay->create($key)->consume();
        $interval = $this->byInterval->create($key)->consume();
        $address = $this->byAddress->create($clientIp)->consume();

        // El tope del periodo primero: es el que peor noticia da, y quien lo
        // ha agotado tiene que saberlo aunque además acabe de pulsar.
        if (!$daily->isAccepted()) {
            throw PasswordResetLimitReached::inSeconds(self::secondsUntil($daily->getRetryAfter()));
        }

        if (!$interval->isAccepted()) {
            throw PasswordResetRequestedTooSoon::inSeconds(self::secondsUntil($interval->getRetryAfter()));
        }

        if (!$address->isAccepted()) {
            throw PasswordResetLimitReached::inSeconds(self::secondsUntil($address->getRetryAfter()));
        }
    }

    private static function secondsUntil(\DateTimeInterface $moment): int
    {
        return max(0, $moment->getTimestamp() - time());
    }
}
