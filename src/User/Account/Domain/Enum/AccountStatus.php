<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Enum;

/**
 * The life of an account.
 *
 * `PENDING_ACTIVATION → ACTIVE → DELETED` is the ordinary path.
 *
 * Two states are **not** part of it and llegan desde fuera, cuando
 * `Moderation` publica una sanción (`FEAT-MOD-006`):
 *
 * - `SUSPENDED` es la suspensión total. **Es indefinida por diseño**: no
 *   caduca sola, alguien tiene que levantarla;
 * - `BLOCKED` es la expulsión. La cuenta **conserva sus datos** —no se
 *   anonimiza (`MOD-26`)— precisamente porque no se puede a la vez borrar a
 *   alguien y recordarlo para impedirle volver.
 *
 * Las dos impiden entrar. La **suspensión parcial** no es un estado de la
 * cuenta: deja entrar y leer, y solo bloquea las escrituras, así que vive en
 * una fecha aparte (`User::restrictedUntil`).
 */
enum AccountStatus: string
{
    case PENDING_ACTIVATION = 'PENDING_ACTIVATION';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case BLOCKED = 'BLOCKED';
    case DELETED = 'DELETED';

    /**
     * Only an activated account writes (`decision:0003`).
     */
    public function canWrite(): bool
    {
        return self::ACTIVE === $this;
    }

    /**
     * A deleted account is **anonymised**: there is nothing left to show, and
     * its profile answers as if it had never existed (`FEAT-USR-014` `RN-7`).
     * What survives is the identifier, which corrections and credit movements
     * still point at.
     */
    public function isDeleted(): bool
    {
        return self::DELETED === $this;
    }

    /**
     * A blocked account does **not** authenticate
     * ([`FEAT-MOD-006`](../../../../../docs/features/moderation/FEAT-MOD-006-sanctions.md),
     * [`decision:0007`](../../../../../docs/decisions/0007-jwt-sessions.md)).
     *
     * An unactivated one does: the onboarding happens before activating
     * (`FEAT-USR-001` `RN-9`), so refusing here would lock everybody out
     * immediately after signing up. What it cannot do is write
     * (`canWrite()`).
     */
    public function canAuthenticate(): bool
    {
        return self::PENDING_ACTIVATION === $this || self::ACTIVE === $this;
    }

    /**
     * Si el correo de esta cuenta se puede volver a usar para registrarse.
     *
     * `BLOCKED` es el único que dice que no, y es lo que hace efectiva a la
     * expulsión: si se anonimizara, la persona podría registrarse de nuevo al
     * minuto siguiente y la sanción más grave del catálogo sería la más fácil
     * de esquivar (`FEAT-MOD-006`).
     */
    public function releasesItsEmail(): bool
    {
        return self::BLOCKED !== $this;
    }
}
