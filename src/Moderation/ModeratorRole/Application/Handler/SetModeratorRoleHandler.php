<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Application\Handler;

use LectoresBeta\Moderation\AuditLog\Application\Service\RecordAuditEntry;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\ModeratorRole\Application\Command\SetModeratorRole;
use LectoresBeta\Moderation\ModeratorRole\Domain\Entity\ModeratorRole;
use LectoresBeta\Moderation\ModeratorRole\Domain\Enum\ModeratorLevel;
use LectoresBeta\Moderation\ModeratorRole\Domain\Exception\ModeratorRoleRefused;
use LectoresBeta\Moderation\ModeratorRole\Domain\Repository\ModeratorRoleRepository;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\RegisteredUsers;

/**
 * Conceder o revocar el rol de moderación (`FEAT-MOD-004` `RN-1`).
 *
 * **Solo un `Admin`**, y la comprobación de quién puede la hace el firewall —
 * `ROLE_ADMIN` sobre la ruta— porque es autorización de transporte. Lo que se
 * decide aquí es lo demás.
 *
 * Tres reglas que no son evidentes:
 *
 * - **la cuenta tiene que estar activada** (`FEAT-MOD-012` `RN-2`). Una sin
 *   activar no ha demostrado todavía que haya alguien detrás, y el
 *   backoffice lee obra inédita y datos personales de cualquiera;
 * - **nadie se toca su propio rol.** Concedérselo convertiría el registro de
 *   auditoría en un trámite, y quitárselo dejaría la plataforma sin
 *   administrador por un descuido;
 * - **conceder a quien lo tuvo reactiva su fila**, no crea otra. La clave es
 *   la cuenta, y su historia importa.
 *
 * Queda en el registro de auditoría, en la misma transacción que el cambio:
 * un registro que se puede perder mientras el efecto se conserva invita a
 * confiar en él.
 */
final readonly class SetModeratorRoleHandler
{
    /**
     * El actor que queda en la auditoría cuando el cambio viene por consola
     * (`FEAT-MOD-012` `RN-3`). La consola no tiene identidad propia —la tiene
     * quien la ejecuta, y eso no se puede saber desde aquí— pero sí importa
     * que **fue por consola**, porque es el único camino sin administrador
     * detrás.
     */
    public const CONSOLE = '00000000-0000-4000-8000-000000000000';

    public function __construct(
        private ModeratorRoleRepository $roles,
        private RegisteredUsers $accounts,
        private RecordAuditEntry $audit,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(SetModeratorRole $command): void
    {
        try {
            $userId = PartyId::fromString($command->userId);
            $actorId = PartyId::fromString($command->actorId);
        } catch (InvalidValue) {
            throw ModeratorRoleRefused::becauseTheAccountIsNotUsable();
        }

        // Nadie se toca su propio rol desde la API. Por consola sí: ahí no
        // hay administrador que firme, porque la primera vez no lo hay.
        if (!$command->fromConsole && $userId->value() === $actorId->value()) {
            throw ModeratorRoleRefused::becauseItIsYourOwnAccount();
        }

        $level = $this->levelFrom($command->level);
        $existing = $this->roles->ofUser($userId);

        if (null !== $level && !$this->accounts->isActivated($command->userId)) {
            throw ModeratorRoleRefused::becauseTheAccountIsNotUsable();
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($existing, $userId, $actorId, $level, $now): void {
            $role = $this->applied($existing, $userId, $actorId, $level, $now);

            $this->roles->save($role);
            $this->audit->of(
                $actorId,
                null === $level ? 'MODERATOR_ROLE_REVOKED' : 'MODERATOR_ROLE_GRANTED',
                'USER',
                $userId->value(),
                payload: ['level' => $level?->value],
            );
        });
    }

    private function applied(
        ?ModeratorRole $existing,
        PartyId $userId,
        PartyId $actorId,
        ?ModeratorLevel $level,
        \DateTimeImmutable $now,
    ): ModeratorRole {
        if (null === $existing) {
            // Revocar a quien no tiene nada deja constancia de la intención y
            // una fila ya revocada. Es idempotente, que es lo que `RN-4` de
            // `FEAT-MOD-012` pide.
            $role = new ModeratorRole($userId, $level ?? ModeratorLevel::MODERATOR, $actorId, $now);

            if (null === $level) {
                $role->revoke($now);
            }

            return $role;
        }

        if (null === $level) {
            $existing->revoke($now);

            return $existing;
        }

        $existing->grantAgain($level, $actorId, $now);

        return $existing;
    }

    private function levelFrom(?string $level): ?ModeratorLevel
    {
        if (null === $level) {
            return null;
        }

        return ModeratorLevel::tryFrom($level) ?? throw ModeratorRoleRefused::becauseThatLevelDoesNotExist($level);
    }
}
