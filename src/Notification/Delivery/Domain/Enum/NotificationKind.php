<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Domain\Enum;

/**
 * The catalogue of notices this context delivers.
 *
 * Two families, and the difference is not cosmetic:
 *
 * - **operational** messages — activation, password reset, security
 *   warnings, legal notices, moderator alerts — ignore every preference,
 *   including the master switch (`FEAT-USR-039` `RN-3`). They are not
 *   notifications.
 * - everything else obeys the person's choices.
 *
 * A new kind must declare which family it is in (`RN-4c`). One that declares
 * neither will silently behave as a notification, which is the wrong default
 * for anything to do with security.
 *
 * `PASSWORD_RESET_REQUESTED` is operational for the starkest reason of all
 * (`FEAT-USR-007`): somebody who had unsubscribed from it could never
 * recover their account. `PASSWORD_CHANGED` likewise — it is the only thing
 * that tells a person their account has just been taken.
 *
 * `WORK_BLOCKED` es operativo por una razón parecida y más dura
 * (`FEAT-MOD-003` `RN-8b`): es la **única vía** por la que el autor se entera
 * de que su obra ha sido bloqueada y la única que le dice a qué dirección
 * recurrir. Un recurso que no se sabe dónde presentar no existe.
 *
 * `BETA_READER_ACCESS_REVOKED` arrived with `FEAT-NOT-001` and is the one
 * that saldó a debt two features had written down: somebody who loses access
 * to a work **stops being able to deliver what they were writing**, and until
 * now nothing told them. It is a notification and not operational: it is not
 * about their account, it is about somebody else's decision.
 */
enum NotificationKind: string
{
    case ACCOUNT_ACTIVATION = 'ACCOUNT_ACTIVATION';
    case PASSWORD_RESET_REQUESTED = 'PASSWORD_RESET_REQUESTED';
    case PASSWORD_CHANGED = 'PASSWORD_CHANGED';
    case EMAIL_CHANGE_REQUESTED = 'EMAIL_CHANGE_REQUESTED';
    case EMAIL_CHANGED = 'EMAIL_CHANGED';
    case ACCOUNT_BLOCKED = 'ACCOUNT_BLOCKED';
    case MODERATION_ALERT = 'MODERATION_ALERT';
    case PLATFORM_INVITATION = 'PLATFORM_INVITATION';

    case CORRECTION_RECEIVED = 'CORRECTION_RECEIVED';
    case CORRECTION_REPLIED = 'CORRECTION_REPLIED';
    case CORRECTION_RATED = 'CORRECTION_RATED';
    case CHAPTER_COMMENT = 'CHAPTER_COMMENT';
    case ACCESS_REQUESTED = 'ACCESS_REQUESTED';
    case ACCESS_REQUEST_RESOLVED = 'ACCESS_REQUEST_RESOLVED';
    case BETA_READER_INVITATION = 'BETA_READER_INVITATION';
    case BETA_READER_ACCESS_REVOKED = 'BETA_READER_ACCESS_REVOKED';
    case WRITING_BUDDY_PROPOSED = 'WRITING_BUDDY_PROPOSED';
    case DIRECT_MESSAGE_RECEIVED = 'DIRECT_MESSAGE_RECEIVED';
    case MENTION = 'MENTION';
    case POST_REPLY = 'POST_REPLY';
    case SUBSCRIBED_AUTHOR_PUBLISHED = 'SUBSCRIBED_AUTHOR_PUBLISHED';
    case CREDITS_ADDED = 'CREDITS_ADDED';
    case CREDITS_SPENT = 'CREDITS_SPENT';
    case BALANCE_WENT_NEGATIVE = 'BALANCE_WENT_NEGATIVE';
    case CORRECTION_UNLOCKED = 'CORRECTION_UNLOCKED';
    case REACTIVATION_OFFER = 'REACTIVATION_OFFER';
    case CORRECTION_CLOSED = 'CORRECTION_CLOSED';
    case CLAIM_RESOLVED = 'CLAIM_RESOLVED';
    case WORK_BLOCKED = 'WORK_BLOCKED';

    /**
     * **Sin `default`, y es la mitad de `RN-4c`.**.
     *
     * Con un `default` a `false`, un tipo nuevo se comportaría en silencio
     * como notificación —silenciable—, que es exactamente el valor
     * equivocado para cualquier cosa que tenga que ver con la seguridad de
     * una cuenta. Sin él, el análisis estático se queja en cuanto alguien
     * añade un caso, y la clasificación deja de poder olvidarse.
     */
    /**
     * Si este aviso **también sale por correo** (`FEAT-NOT-002` `RN-1`).
     *
     * Sin `default`, por lo mismo que `isOperational()`: un tipo nuevo tiene
     * que decidirlo o el análisis estático se queja.
     *
     * Los que dicen que no son los de **ritmo social** —una respuesta, un me
     * gusta, un mensaje— y los **movimientos de créditos**: pasan muchas
     * veces al día y un correo por cada uno enseña a ignorar el remitente,
     * que es la forma más rápida de que el correo que sí importa tampoco se
     * lea.
     *
     * Los **operativos** tampoco: tienen su propio consumidor y su propio
     * texto (`RN-6`), con un enlace de un solo uso o una dirección a la que
     * recurrir que un correo genérico perdería.
     *
     * Esta respuesta tiene que coincidir con la del catálogo de `User`, que
     * es el que enseña la casilla. Lo comprueba
     * `tests/Unit/Architecture/NotificationCatalogueTest.php` en cada
     * ejecución, porque son dos contextos y ninguno importa el enum del otro.
     */
    public function reachesInbox(): bool
    {
        return match ($this) {
            self::CORRECTION_RECEIVED,
            self::CORRECTION_REPLIED,
            self::CORRECTION_UNLOCKED,
            self::CORRECTION_CLOSED,
            self::CHAPTER_COMMENT,
            self::MENTION,
            self::ACCESS_REQUESTED,
            self::ACCESS_REQUEST_RESOLVED,
            self::BETA_READER_INVITATION,
            self::BETA_READER_ACCESS_REVOKED,
            self::WRITING_BUDDY_PROPOSED,
            self::SUBSCRIBED_AUTHOR_PUBLISHED,
            self::BALANCE_WENT_NEGATIVE,
            self::CLAIM_RESOLVED,
            self::REACTIVATION_OFFER => true,

            // De ritmo social, o demasiado frecuentes para un buzón.
            self::CORRECTION_RATED,
            self::POST_REPLY,
            self::DIRECT_MESSAGE_RECEIVED,
            self::CREDITS_ADDED,
            self::CREDITS_SPENT => false,

            // Operativos: salen por su propio consumidor, con su texto.
            self::ACCOUNT_ACTIVATION,
            self::PASSWORD_RESET_REQUESTED,
            self::PASSWORD_CHANGED,
            self::EMAIL_CHANGE_REQUESTED,
            self::EMAIL_CHANGED,
            self::ACCOUNT_BLOCKED,
            self::MODERATION_ALERT,
            self::WORK_BLOCKED,
            self::PLATFORM_INVITATION => false,
        };
    }

    public function isOperational(): bool
    {
        return match ($this) {
            self::ACCOUNT_ACTIVATION,
            self::PASSWORD_RESET_REQUESTED,
            self::PASSWORD_CHANGED,
            self::EMAIL_CHANGE_REQUESTED,
            self::EMAIL_CHANGED,
            self::ACCOUNT_BLOCKED,
            self::MODERATION_ALERT,
            self::WORK_BLOCKED,
            self::PLATFORM_INVITATION => true,

            self::CORRECTION_RECEIVED,
            self::CORRECTION_REPLIED,
            self::CORRECTION_RATED,
            self::CHAPTER_COMMENT,
            self::ACCESS_REQUESTED,
            self::ACCESS_REQUEST_RESOLVED,
            self::BETA_READER_INVITATION,
            self::BETA_READER_ACCESS_REVOKED,
            self::WRITING_BUDDY_PROPOSED,
            self::DIRECT_MESSAGE_RECEIVED,
            self::MENTION,
            self::POST_REPLY,
            self::SUBSCRIBED_AUTHOR_PUBLISHED,
            self::CREDITS_ADDED,
            self::CREDITS_SPENT,
            self::BALANCE_WENT_NEGATIVE,
            self::CORRECTION_UNLOCKED,
            self::CORRECTION_CLOSED,
            self::CLAIM_RESOLVED,
            // Silenciable, y es justo el punto: apagarlo renuncia al
            // mecanismo entero (`FEAT-CRD-019` `RN-2d`, `RN-8`), porque un
            // descubierto sin aviso no es un gancho sino deuda a espaldas de
            // alguien.
            self::REACTIVATION_OFFER => false,
        };
    }
}
