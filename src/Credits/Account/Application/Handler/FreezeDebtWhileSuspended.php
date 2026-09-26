<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Handler;

use LectoresBeta\Credits\Account\Application\Event\SanctionImposed;
use LectoresBeta\Credits\Account\Application\Event\SanctionLifted;
use LectoresBeta\Credits\Account\Domain\Entity\CreditAccount;
use LectoresBeta\Credits\Account\Domain\Event\CreditDebtFrozen;
use LectoresBeta\Credits\Account\Domain\Event\CreditDebtThawed;
use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Qué significa una suspensión parcial **para la deuda** (`FEAT-CRD-018`
 * `RN-8b`, `FEAT-MOD-006` `RN-9`).
 *
 * Quien está suspendido parcialmente puede entrar y leer, pero no escribir; y
 * corregir es escribir. De modo que durante la sanción no tiene forma alguna
 * de saldar lo que debe. Retenerle mientras tanto el contenido de las
 * correcciones que ya le entregaron sería exigirle que haga lo único que le
 * hemos prohibido hacer.
 *
 * **`Moderation` no ordena nada de esto.** Publica que ha sancionado a
 * alguien; qué le hace eso a su saldo lo decide este contexto a solas, que es
 * la regla dura de
 * [`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md).
 *
 * Tres decisiones que no se ven en el código y son la funcionalidad entera:
 *
 * - **solo `PARTIAL_SUSPENSION`, y solo con fecha de fin.** Una suspensión
 *   total o una expulsión cierran la cuenta: no hay nadie dentro a quien
 *   retenerle nada, y congelar la deuda de quien ya no entra sería perdonarle
 *   la consecuencia por haber hecho algo peor. Un aviso no cambia nada;
 * - **se congela la retención, no la corregibilidad.** Si durante la sanción
 *   sus capítulos volvieran a admitir correcciones, cada una cobrada
 *   ahondaría la deuda — y `RN-8b` dice «ni crece» en la misma frase en que
 *   dice «ni bloquea nada»;
 * - **sin registro de duplicados**, igual que `ApplySanction` en `User`. Y
 *   aquí cuesta más que allí, porque levantar una sanción **deshace** lo que
 *   imponerla hizo: una reentrega del hecho de imponer podría volver a
 *   congelar lo que ya se descongeló. La cuenta guarda las dos fechas
 *   —hasta cuándo, y si se levantó antes— precisamente para que reaplicar
 *   cualquiera de los dos hechos no cambie nada.
 */
final readonly class FreezeDebtWhileSuspended
{
    public function __construct(
        private CreditAccountRepository $accounts,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function imposed(SanctionImposed $event): void
    {
        if ('PARTIAL_SUSPENSION' !== strtoupper($event->type) || null === $event->expiresAt) {
            return;
        }

        $userId = $this->parse($event->userId);

        if (null === $userId) {
            return;
        }

        $now = $this->clock->now();
        // La cuenta se crea si no la había. Es la única vez que `Credits`
        // crea una sin que haya un movimiento detrás, y está justificado:
        // aquí sí hay un dato que guardar —hasta cuándo— que se perdería si
        // durante la sanción alguien corrigiera su obra y la dejara en rojo.
        $account = $this->accounts->ofUser($userId) ?? new CreditAccount($userId, $now);

        // La fecha del hecho, no la de ahora: es lo que hace que reaplicarlo
        // sea inocuo por muchas veces que la cola lo entregue.
        $changed = $account->freezeDebtUntil($event->expiresAt, $event->occurredAt(), $now);

        $this->session->execute(function () use ($account): void {
            $this->accounts->save($account);
        });

        if (!$changed) {
            // Ya estaba congelada hasta esa fecha o más allá, o esta es la
            // reentrega de una sanción que después se levantó.
            return;
        }

        $this->events->publish(new CreditDebtFrozen(
            EventId::generate(),
            $userId,
            $event->expiresAt,
            $now,
        ));
    }

    public function lifted(SanctionLifted $event): void
    {
        $userId = $this->parse($event->userId);

        if (null === $userId) {
            return;
        }

        $account = $this->accounts->ofUser($userId);

        if (null === $account) {
            return;
        }

        $now = $this->clock->now();

        if (!$account->thawDebt($event->occurredAt(), $now)) {
            // No estaba congelada: la sanción levantada no era parcial, su
            // plazo ya había vencido solo, o esto es una reentrega. No hay
            // hecho que anunciar.
            return;
        }

        $this->session->execute(function () use ($account): void {
            $this->accounts->save($account);
        });

        $this->events->publish(new CreditDebtThawed(EventId::generate(), $userId, $now));
    }

    private function parse(string $userId): ?UserId
    {
        try {
            return UserId::fromString($userId);
        } catch (InvalidValue) {
            // Un hecho con un identificador ilegible no se reintenta: se
            // descarta. Reintentarlo lo dejaría dando vueltas para siempre.
            return null;
        }
    }
}
