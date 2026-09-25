<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Event\CreditBalanceWentNegative;
use LectoresBeta\Feedback\Correction\Application\Event\CreditDebtCleared;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Qué significa aquí quedarse en números rojos (`FEAT-CRD-018` `RN-9` a
 * `RN-11`).
 *
 * **El corrector cobra siempre**, incluso si el autor no llega. Lo que se
 * retiene no es el pago sino la lectura: la corrección que dejó el saldo en
 * negativo llega bloqueada —metadatos sí, contenido no— hasta que el autor
 * reponga.
 *
 * Es la única forma de que el descubierto signifique algo sin castigar a
 * quien hizo el trabajo. Cerrar la puerta de recibir (`RN-2`, que decide
 * `Credits` con su política de corregibilidad) y retener lo ya entregado son
 * las dos mitades: sin la segunda, dejar la cuenta en rojo sería gratis.
 *
 * Dos cosas que parecen detalles y son las reglas:
 *
 * - **solo se bloquea la que lo provocó** (`RN-11`). Lo que el autor ya había
 *   leído, leído está, y quitárselo después sería reescribir el pasado;
 * - **se desbloquean todas de golpe** (`RN-10`), sin liberación parcial: es
 *   más simple y el resultado agregado es el mismo.
 */
final readonly class TrackCorrectionLocking
{
    public function __construct(
        private CorrectionRepository $corrections,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function wentNegative(CreditBalanceWentNegative $event): void
    {
        if (null === $event->subjectId) {
            // El saldo bajó por algo que no es una corrección. No hay nada
            // que retener: lo que se bloquea es la lectura de un trabajo
            // concreto, no la cuenta.
            return;
        }

        try {
            $correction = $this->corrections->ofId(CorrectionId::fromString($event->subjectId));
        } catch (InvalidValue) {
            return;
        }

        if (null === $correction || $correction->ownerId()->value() !== $event->userId) {
            return;
        }

        $this->session->execute(function () use ($correction): void {
            $correction->lock($this->clock->now());
            $this->corrections->save($correction);
        });
    }

    public function debtCleared(CreditDebtCleared $event): void
    {
        try {
            $authorId = AuthorId::fromString($event->userId);
        } catch (InvalidValue) {
            return;
        }

        $locked = $this->corrections->lockedFor($authorId);

        if ([] === $locked) {
            return;
        }

        $this->session->execute(function () use ($locked): void {
            $now = $this->clock->now();

            foreach ($locked as $correction) {
                $correction->unlock($now);
                $this->corrections->save($correction);
            }
        });
    }
}
