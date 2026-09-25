<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The invitation cannot go ahead, and this is why (`FEAT-RDG-004`,
 * `FEAT-RDG-005`).
 *
 * The mirror of `AccessRequestRefused`, and the same shape for the same
 * reason: nobody catches these one by one, they all end up as an RFC 9457
 * problem with a code, and the kind travels per factory so that one idea can
 * answer `422`, `409` and `410`.
 */
final class InvitationRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * Not a `404`: the author picked this person, and telling them the work
     * does not exist would be answering about the wrong thing.
     */
    public static function userNotFound(): self
    {
        return new self(
            'USER_NOT_FOUND',
            FailureKind::INVALID,
            'There is no such user to invite.',
        );
    }

    public static function authorCannotBeInvited(): self
    {
        return new self(
            'AUTHOR_CANNOT_BE_BETA_READER',
            FailureKind::INVALID,
            'The author of a work is not a beta reader of it.',
        );
    }

    /**
     * La obra es `ADULTS_ONLY` y la persona invitada no tiene edad
     * ([`FEAT-USR-044`](../../../../../docs/features/user/FEAT-USR-044-age-based-content-filtering.md)
     * `U-22`).
     *
     * Se comprueba **al invitar** y no solo al leer. Antes la invitación se
     * cursaba y la lectura fallaba después, así que el autor veía a alguien
     * aceptar y no poder entrar, sin ninguna explicación.
     *
     * No dice **por qué** esa persona no puede: la edad de otro no es asunto
     * de quien invita, y un mensaje que lo insinuara convertiría el botón de
     * invitar en un comprobador de quién es menor.
     */
    public static function readerCannotSeeThisWork(): self
    {
        return new self(
            'READER_CANNOT_SEE_THIS_WORK',
            FailureKind::CONFLICT,
            'That person cannot be given access to this work.',
        );
    }

    /**
     * Esa persona no admite invitaciones a leer (`FEAT-USR-011`).
     *
     * **Sí se dice**, a diferencia de la edad. La diferencia es qué revela
     * cada una: la edad de otro es un dato suyo y un mensaje que la insinuara
     * convertiría el botón de invitar en un comprobador de quién es menor;
     * que alguien tenga el buzón de invitaciones cerrado es una decisión
     * pública en la práctica —se nota a la primera— y callarla dejaría al
     * autor esperando una respuesta que no va a llegar.
     */
    public static function invitationsNotAccepted(): self
    {
        return new self(
            'INVITATIONS_NOT_ACCEPTED',
            FailureKind::CONFLICT,
            'That person does not accept invitations to read.',
        );
    }

    public static function alreadyABetaReader(): self
    {
        return new self(
            'ALREADY_A_BETA_READER',
            FailureKind::CONFLICT,
            'That reader already has access to this work.',
        );
    }

    /**
     * They asked first. What the author has to do is accept that request, not
     * create a second object meaning the same thing.
     */
    public static function requestAlreadyPending(): self
    {
        return new self(
            'REQUEST_ALREADY_PENDING',
            FailureKind::CONFLICT,
            'That person already asked for access; resolve their request instead.',
        );
    }

    public static function alreadyPending(): self
    {
        return new self(
            'INVITATION_ALREADY_PENDING',
            FailureKind::CONFLICT,
            'That person has already been invited to this work.',
        );
    }

    public static function alreadyResolved(): self
    {
        return new self(
            'INVITATION_ALREADY_RESOLVED',
            FailureKind::CONFLICT,
            'That invitation was already accepted, declined or withdrawn.',
        );
    }

    public static function workIsGone(): self
    {
        return new self(
            'WORK_GONE',
            FailureKind::GONE,
            'That work no longer exists.',
        );
    }

    public static function unknownDecision(): self
    {
        return new self(
            'UNKNOWN_DECISION',
            FailureKind::INVALID,
            'An invitation is either ACCEPTED or DECLINED.',
        );
    }

    public static function unknownStatusFilter(): self
    {
        return new self(
            'UNKNOWN_FILTER_VALUE',
            FailureKind::INVALID,
            'An invitation is PENDING, ACCEPTED, DECLINED or CANCELLED; ALL asks for every one.',
        );
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return $this->failureKind;
    }
}
