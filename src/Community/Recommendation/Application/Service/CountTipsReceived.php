<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\Service;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Recommendation\Application\Contract\TipsReceived;
use LectoresBeta\Community\Recommendation\Domain\Repository\AuthorStatsRepository;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * Las propinas recibidas, leídas de la proyección que este contexto ya
 * mantiene (`FEAT-CRD-017` `RN-3c`).
 *
 * `of()` devuelve una proyección recién construida cuando no hay fila, y eso
 * **no la guarda**: aquí se lee y no se escribe, que es lo que un contrato
 * tiene que hacer. Quien no ha recibido ninguna propina tiene cero, que es la
 * respuesta correcta y no un hueco.
 */
final readonly class CountTipsReceived implements TipsReceived
{
    public function __construct(
        private AuthorStatsRepository $stats,
        private Clock $clock,
    ) {
    }

    public function ofReader(string $readerId): int
    {
        try {
            return $this->stats->of(MemberId::fromString($readerId), $this->clock->now())->tipsReceived();
        } catch (InvalidValue) {
            return 0;
        }
    }
}
