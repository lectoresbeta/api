<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Infrastructure\Security;

use LectoresBeta\Work\PublicLink\Domain\Exception\PublicLinkOpenedTooOften;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * El límite de aperturas de enlaces públicos (`FEAT-WRK-010` `RN-16`).
 *
 * **Por origen y no por token**, que es lo contrario de lo que parece
 * natural. Limitar por token protegería a un enlace concreto de ser abierto
 * mucho —que es justo lo que el autor quiere que pase, porque para eso lo
 * repartió—; lo que hay que frenar es a quien prueba tokens distintos, y eso
 * solo se ve desde la dirección de origen.
 */
final readonly class PublicLinkThrottle
{
    public function __construct(private RateLimiterFactory $byAddress)
    {
    }

    public function check(string $clientIp): void
    {
        $attempt = $this->byAddress->create($clientIp)->consume();

        if (!$attempt->isAccepted()) {
            throw PublicLinkOpenedTooOften::inSeconds(max(0, $attempt->getRetryAfter()->getTimestamp() - time()));
        }
    }
}
