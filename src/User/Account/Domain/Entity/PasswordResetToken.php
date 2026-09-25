<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\PasswordResetTokenId;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * El token detrás del enlace «he olvidado mi contraseña» (`FEAT-USR-007`).
 *
 * Solo se guarda su hash (`RN-5`). Quien pueda leer esta tabla no debe poder
 * entrar en ninguna cuenta, y durante la hora que vive este valor **es la
 * cuenta**: quien lo tiene fija la contraseña.
 *
 * Es una tabla aparte de la de activación y no una columna más, aunque la
 * forma sea la misma. Son dos credenciales con vidas distintas —dos días
 * frente a una hora (`RN-6`)— y mezclarlas haría que invalidar una tocase a
 * la otra: pedir el enlace de recuperación rompería el de activación que
 * alguien tuviera abierto.
 */
class PasswordResetToken
{
    private string $id;

    private string $userId;

    private string $tokenHash;

    private \DateTimeImmutable $createdAt;

    private \DateTimeImmutable $expiresAt;

    private ?\DateTimeImmutable $usedAt = null;

    private ?\DateTimeImmutable $invalidatedAt = null;

    public function __construct(
        PasswordResetTokenId $id,
        UserId $userId,
        string $tokenHash,
        \DateTimeImmutable $now,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->id = $id->value();
        $this->userId = $userId->value();
        $this->tokenHash = $tokenHash;
        $this->createdAt = $now;
        $this->expiresAt = $expiresAt;
    }

    public function id(): PasswordResetTokenId
    {
        return PasswordResetTokenId::fromString($this->id);
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isUsable(\DateTimeImmutable $now): bool
    {
        return null === $this->usedAt
            && null === $this->invalidatedAt
            && $now < $this->expiresAt;
    }

    /**
     * Un solo uso (`RN-7`). Sin esto, un enlace reenviado por error o
     * recuperado del historial seguiría abriendo la cuenta.
     */
    public function consume(\DateTimeImmutable $now): void
    {
        $this->usedAt = $now;
    }

    /**
     * Pedir el enlace otra vez invalida el anterior (`RN-8`): dos vivos a la
     * vez doblarían la ventana en la que uno filtrado sigue sirviendo.
     */
    public function invalidate(\DateTimeImmutable $now): void
    {
        $this->invalidatedAt = $now;
    }
}
