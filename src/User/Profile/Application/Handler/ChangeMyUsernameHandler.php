<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Event\UsernameChanged;
use LectoresBeta\User\Account\Domain\Exception\UsernameNotAvailable;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\Service\ReservedUsernames;
use LectoresBeta\User\Account\Domain\ValueObject\Username;
use LectoresBeta\User\Profile\Application\Command\ChangeMyUsername;
use LectoresBeta\User\Profile\Application\DTO\UsernameChange;
use LectoresBeta\User\Profile\Application\Service\MyProfile;
use LectoresBeta\User\Profile\Domain\Entity\UsernameAlias;
use LectoresBeta\User\Profile\Domain\Repository\UsernameAliasRepository;

/**
 * Cambiar el nombre de usuario (`FEAT-USR-034`).
 *
 * **El nombre abandonado no queda libre: se reserva 30 días como alias.** Esa
 * es la funcionalidad entera, y su razón principal no es que los enlaces
 * compartidos sigan funcionando —que también— sino que **nadie más pueda
 * ocupar el nombre y heredar el tráfico dirigido a otra persona**.
 *
 * Todo ocurre en una transacción, y el orden importa menos que la atomicidad:
 * si el alias se creara sin completar el cambio, alguien bloquearía su propio
 * nombre; y al revés, el cambio sin alias rompería exactamente los enlaces
 * que esto existe para proteger.
 *
 * Tiene su propio endpoint aunque la pantalla lo enseñe junto al nombre y la
 * biografía, y `RN-9` de [`FEAT-USR-008`](../../../../../docs/features/user/FEAT-USR-008-edit-profile.md)
 * explica por qué: mezclado en aquel `PATCH`, una biografía se quedaría sin
 * guardar porque el nombre que alguien quería está ocupado.
 */
final readonly class ChangeMyUsernameHandler
{
    public function __construct(
        private MyProfile $profile,
        private UserRepository $users,
        private UsernameAliasRepository $aliases,
        private ReservedUsernames $reserved,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ChangeMyUsername $command): UsernameChange
    {
        $user = $this->profile->of($command->userId);
        $requested = Username::fromString((string) $command->username);
        $now = $this->clock->now();

        // Pedir el nombre que ya se tiene no es un cambio: no gasta el cupo y
        // no crea un alias de uno mismo. Guardar dos veces el formulario sin
        // tocar este campo no puede costar treinta días.
        if ($requested->equals($user->username())) {
            return self::unchanged($user, $now);
        }

        if ($this->reserved->isReserved($requested)) {
            throw UsernameNotAvailable::reserved();
        }

        if ($this->users->usernameIsTaken($requested)) {
            throw UsernameNotAvailable::taken();
        }

        $held = $this->aliases->ofUsername($requested);
        $reclaimable = null !== $held && $held->resolvesToProfileAt($now)
            && $held->userId()?->value() === $user->id()->value();

        // Un alias ajeno vigente, o el de una cuenta eliminada, responden lo
        // mismo que un nombre en uso: decir que está «reservado por otro»
        // contaría que alguien lo tuvo y lo dejó hace menos de un mes.
        if (null !== $held && $held->isInForceAt($now) && !$reclaimable) {
            throw UsernameNotAvailable::taken();
        }

        $previous = $user->username();

        $this->session->execute(function () use ($user, $requested, $previous, $held, $reclaimable, $now): void {
            $reclaimable ? $user->reclaimUsername($requested, $now) : $user->changeUsername($requested, $now);
            $this->users->save($user);

            $this->reserve($previous, $user, $now);

            // `RN-11`: el nombre recuperado vuelve a ser el de la cuenta, y
            // no puede ser las dos cosas. Una fila caducada que la purga aún
            // no ha retirado se va por el mismo sitio.
            if (null !== $held) {
                $this->aliases->purge($held);
            }
        });

        $alias = $this->aliases->ofUsername($previous);

        $this->events->publish(new UsernameChanged(
            EventId::generate(),
            $user->id(),
            $previous->value(),
            $requested->value(),
            $alias?->expiresAt() ?? $now,
            $now,
        ));

        return new UsernameChange(
            $requested->value(),
            $previous->value(),
            $alias?->expiresAt(),
            $user->usernameChangeableOn($now),
        );
    }

    /**
     * El nombre que se deja pasa a alias. Si ya existía una fila suya —una
     * caducada que la purga no ha recogido— se le renueva el plazo en vez de
     * insertar otra: la clave es el nombre, y no caben dos.
     */
    private function reserve(Username $previous, User $user, \DateTimeImmutable $now): void
    {
        $existing = $this->aliases->ofUsername($previous);

        if (null !== $existing) {
            $existing->renew($now);
            $this->aliases->save($existing);

            return;
        }

        $this->aliases->save(UsernameAlias::afterRename($previous, $user->id(), $now));
    }

    private static function unchanged(User $user, \DateTimeImmutable $now): UsernameChange
    {
        return new UsernameChange(
            $user->username()->value(),
            null,
            null,
            $user->usernameChangeableOn($now),
        );
    }
}
