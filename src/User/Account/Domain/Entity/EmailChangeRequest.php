<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\EmailChangeRequestId;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * A pending change of email address (`FEAT-USR-040`).
 *
 * Until it is confirmed, the valid address is still the old one. That is the
 * whole point of the intermediate row: typing the new address must not lock
 * anybody out of their account.
 */
/**
 * Una petición de cambio de correo esperando confirmación (`FEAT-USR-040`).
 *
 * **La dirección no cambia hasta que se confirma** (`RN-2`): hasta entonces
 * la válida sigue siendo la anterior, que es con la que se entra y a la que
 * llegan los avisos. Entre pedirlo y confirmarlo la cuenta funciona con
 * normalidad; no hay un estado intermedio degradado.
 *
 * `tokenHash` nace **nulo** y lo rellena el contrato publicado al enviar el
 * correo, igual que el enlace de activación. Es lo que mantiene el secreto
 * fuera de la cola y hace que el plazo empiece a contar cuando el correo sale
 * y no cuando se pidió.
 */
class EmailChangeRequest
{
    private string $id;

    private string $userId;

    private string $newEmail;

    private ?string $tokenHash = null;

    private \DateTimeImmutable $requestedAt;

    private \DateTimeImmutable $expiresAt;

    private ?\DateTimeImmutable $consumedAt = null;

    public function __construct(
        EmailChangeRequestId $id,
        UserId $userId,
        Email $newEmail,
        \DateTimeImmutable $now,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->id = $id->value();
        $this->userId = $userId->value();
        $this->newEmail = $newEmail->value();
        $this->requestedAt = $now;
        $this->expiresAt = $expiresAt;
    }

    public function id(): EmailChangeRequestId
    {
        return EmailChangeRequestId::fromString($this->id);
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function newEmail(): Email
    {
        return Email::fromString($this->newEmail);
    }

    public function tokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * El secreto se acuña al mandar el correo, no al pedir el cambio. Así el
     * plazo empieza cuando el mensaje sale y no mientras espera en una cola
     * de reintentos, y el valor en claro nunca pasa por la cola.
     */
    public function issueToken(string $tokenHash, \DateTimeImmutable $expiresAt): void
    {
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
    }

    public function isPending(\DateTimeImmutable $now): bool
    {
        return null === $this->consumedAt && $now < $this->expiresAt;
    }

    public function isConsumed(): bool
    {
        return null !== $this->consumedAt;
    }

    public function consume(\DateTimeImmutable $now): void
    {
        $this->consumedAt = $now;
    }
}
