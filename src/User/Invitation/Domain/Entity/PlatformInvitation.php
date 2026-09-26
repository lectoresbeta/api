<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Invitation\Domain\ValueObject\PlatformInvitationId;

/**
 * An invitation to join the platform.
 *
 * Consumed exactly once (`FEAT-USR-001` `RN-12`). Consuming it only records
 * who invited whom: the +5 credits are not earned here but when the invited
 * person delivers their first correction (`decision:0006`, rule 6). That is
 * the whole anti-fraud design — faking it costs a real correction.
 */
class PlatformInvitation
{
    private string $id;

    private string $inviterId;

    private ?string $email = null;

    private string $tokenHash;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $consumedAt = null;

    private ?string $consumedBy = null;

    public function __construct(
        PlatformInvitationId $id,
        UserId $inviterId,
        string $tokenHash,
        \DateTimeImmutable $now,
        ?Email $email = null,
    ) {
        $this->id = $id->value();
        $this->inviterId = $inviterId->value();
        $this->tokenHash = $tokenHash;
        $this->createdAt = $now;
        $this->email = $email?->value();
    }

    public function id(): PlatformInvitationId
    {
        return PlatformInvitationId::fromString($this->id);
    }

    public function inviterId(): UserId
    {
        return UserId::fromString($this->inviterId);
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function consumedAt(): ?\DateTimeImmutable
    {
        return $this->consumedAt;
    }

    /**
     * Un token nuevo, que invalida el anterior (`FEAT-NOT-007`).
     *
     * El token en claro no se puede recuperar —en la tabla solo está su
     * hash—, así que reenviar una invitación es emitir otro. **Solo el último
     * correo funciona**, que es lo que hace seguro el reenvío.
     */
    public function replaceToken(string $tokenHash): void
    {
        $this->tokenHash = $tokenHash;
    }

    public function isAvailable(): bool
    {
        return null === $this->consumedAt;
    }

    public function consumedBy(): ?UserId
    {
        return null === $this->consumedBy ? null : UserId::fromString($this->consumedBy);
    }

    /**
     * Returns false if the invitation was already used. An invalid or spent
     * token never blocks a registration: it is ignored (`RN-13`).
     */
    public function consume(UserId $invitee, \DateTimeImmutable $now): bool
    {
        if (null !== $this->consumedAt) {
            return false;
        }

        $this->consumedAt = $now;
        $this->consumedBy = $invitee->value();

        return true;
    }
}
