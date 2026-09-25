<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Event\SanctionImposed;
use LectoresBeta\User\Account\Application\Event\SanctionLifted;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Lo que una sanción significa **dentro de `User`** (`FEAT-MOD-006`).
 *
 * `Moderation` registra la sanción y este contexto la aplica. Es la frontera
 * que evita dos dueños del estado de una cuenta: si `Moderation` marcara la
 * cuenta directamente, el día que los dos discreparan no habría forma de
 * saber cuál manda.
 *
 * Las cuatro familias no se parecen, y aquí se ve por qué:
 *
 * - un **aviso** no cambia nada. Queda registrado en `Moderation`, que es
 *   donde pesa para la reincidencia;
 * - una **suspensión parcial** deja entrar y leer, y solo cierra las
 *   escrituras hasta una fecha. Que pueda entrar no es una concesión menor:
 *   es lo que le permite leer la sanción y ver cuándo termina;
 * - una **suspensión total** cierra la puerta y **no caduca sola**;
 * - una **expulsión** deja la cuenta bloqueada y **conserva sus datos**: no
 *   se puede a la vez borrar a alguien y recordarlo para impedirle volver.
 *
 * Sin registro de duplicados y a propósito: aquí no hay un efecto que sumar,
 * hay **un estado que afirmar**. Reprocesar el mismo hecho escribe lo mismo.
 */
final readonly class ApplySanction
{
    public function __construct(
        private UserRepository $users,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function imposed(SanctionImposed $event): void
    {
        $this->on($event->userId, static function (User $user, \DateTimeImmutable $now) use ($event): void {
            match (strtoupper($event->type)) {
                'PARTIAL_SUSPENSION' => $user->restrictWritingUntil($event->expiresAt ?? $now, $now),
                'FULL_SUSPENSION' => $user->suspend($now),
                'EXPULSION' => $user->expel($now),
                // Un aviso no tiene efecto funcional. Queda registrado, que
                // es exactamente lo que se quería de él.
                default => null,
            };
        });
    }

    public function lifted(SanctionLifted $event): void
    {
        $this->on($event->userId, static function (User $user, \DateTimeImmutable $now): void {
            $user->liftSanctions($now);
        });
    }

    /**
     * @param \Closure(User, \DateTimeImmutable): void $apply
     */
    private function on(string $userId, \Closure $apply): void
    {
        try {
            $id = UserId::fromString($userId);
        } catch (InvalidValue) {
            // Un hecho con un identificador ilegible no se reintenta: se
            // descarta. Reintentarlo lo dejaría dando vueltas para siempre.
            return;
        }

        $user = $this->users->ofId($id);

        if (null === $user) {
            return;
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($apply, $user, $now): void {
            $apply($user, $now);
            $this->users->save($user);
        });
    }
}
