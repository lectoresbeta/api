<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Esa invitación no sale (`FEAT-USR-018`).
 *
 * **`alreadyRegistered()` no existe a propósito.** Responder «esa dirección
 * ya tiene cuenta» convertiría el formulario de invitar en un comprobador de
 * quién está en la plataforma, que es justo lo que el alta y la recuperación
 * de contraseña se cuidan de no decir. Invitar a alguien que ya está dentro
 * responde igual que invitar a cualquiera; lo que cambia es que no se manda
 * nada.
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

    public static function toYourself(): self
    {
        return new self(
            'CANNOT_INVITE_YOURSELF',
            FailureKind::INVALID,
            'You are already here.',
        );
    }

    public static function becauseOfTheDailyLimit(int $limit): self
    {
        return new self(
            'INVITATION_LIMIT_REACHED',
            FailureKind::RATE_LIMITED,
            \sprintf('You can send %d invitations a day.', $limit),
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
