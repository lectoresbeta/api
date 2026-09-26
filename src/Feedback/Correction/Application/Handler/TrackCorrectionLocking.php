<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Event\CreditBalanceWentNegative;
use LectoresBeta\Feedback\Correction\Application\Event\CreditDebtCleared;
use LectoresBeta\Feedback\Correction\Application\Event\CreditDebtFrozen;
use LectoresBeta\Feedback\Correction\Application\Event\CreditDebtThawed;
use LectoresBeta\Feedback\Correction\Domain\Entity\FrozenDebt;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\Repository\FrozenDebtRepository;
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
 *
 * Y una tercera, que llega de fuera: **durante una suspensión parcial la
 * retención se levanta** (`RN-8b`, [`FEAT-MOD-006`](../../../../../docs/features/moderation/FEAT-MOD-006-sanctions.md)
 * `RN-9`). Quien está suspendido no puede corregir, y corregir es lo único
 * que salda la deuda; retenerle mientras tanto sería exigirle justo lo que le
 * hemos prohibido. Al levantarse la sanción la deuda vuelve a retener, pero
 * **solo hacia adelante**: lo que ya pudo leer no se vuelve a cerrar (`RN-11`).
 */
final readonly class TrackCorrectionLocking
{
    public function __construct(
        private CorrectionRepository $corrections,
        private FrozenDebtRepository $frozenDebts,
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

        $frozen = $this->frozenDebts->ofAuthor($correction->ownerId());

        if (null !== $frozen && $frozen->isInForceAt($this->clock->now())) {
            // La deuda está congelada: entra sin retener (`RN-8b`). Se
            // pregunta por la fecha y no por «llegó el evento de congelar»
            // porque esto puede ocurrir semanas después de aquel.
            return;
        }

        $this->session->execute(function () use ($correction): void {
            $correction->lock($this->clock->now());
            $this->corrections->save($correction);
        });
    }

    public function debtCleared(CreditDebtCleared $event): void
    {
        $authorId = $this->parse($event->userId);

        if (null === $authorId) {
            return;
        }

        $this->releaseEverythingHeldFrom($authorId);
    }

    /**
     * El autor está cumpliendo una suspensión parcial (`RN-8b`).
     *
     * Dos cosas, y las dos hacen falta: se libera lo que ya estaba retenido y
     * se **apunta la fecha**, porque lo que se entregue durante el plazo
     * tampoco debe retenerse y para entonces este evento será historia.
     */
    public function debtFrozen(CreditDebtFrozen $event): void
    {
        $authorId = $this->parse($event->userId);

        if (null === $authorId) {
            return;
        }

        $now = $this->clock->now();
        $frozen = $this->frozenDebts->ofAuthor($authorId);
        // La fecha del hecho, no la de ahora: reaplicarlo no debe alargar el
        // plazo ni revivir una sanción que después se levantó.
        $changed = null === $frozen || $frozen->freezeUntil($event->frozenUntil, $event->occurredAt(), $now);
        $frozen ??= new FrozenDebt($authorId, $event->frozenUntil, $now);

        $this->session->execute(function () use ($frozen): void {
            $this->frozenDebts->save($frozen);
        });

        if ($changed) {
            $this->releaseEverythingHeldFrom($authorId);
        }
    }

    /**
     * Se ha levantado la sanción antes de su plazo.
     *
     * Solo se retira la fecha. **Nada se vuelve a bloquear**: lo que el autor
     * ha podido leer, leído está (`RN-11`). Lo que la deuda recupera es su
     * efecto sobre lo que venga después.
     */
    public function debtThawed(CreditDebtThawed $event): void
    {
        $authorId = $this->parse($event->userId);

        if (null === $authorId) {
            return;
        }

        $frozen = $this->frozenDebts->ofAuthor($authorId);

        if (null === $frozen) {
            return;
        }

        $now = $this->clock->now();

        if (!$frozen->liftAt($event->occurredAt(), $now)) {
            return;
        }

        $this->session->execute(function () use ($frozen): void {
            $this->frozenDebts->save($frozen);
        });
    }

    private function releaseEverythingHeldFrom(AuthorId $authorId): void
    {
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

    private function parse(string $userId): ?AuthorId
    {
        try {
            return AuthorId::fromString($userId);
        } catch (InvalidValue) {
            return null;
        }
    }
}
