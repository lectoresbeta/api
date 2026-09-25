<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Qué propuestas admite esta persona (`FEAT-USR-011`).
 *
 * **No es una preferencia de aviso, es una de recepción**, y la diferencia
 * importa: no es lo mismo no querer enterarse que no querer recibirlas
 * (`S-19`, resuelta). Silenciar una notificación deja la invitación esperando
 * respuesta en algún sitio; cerrar esto impide que llegue a existir.
 *
 * **Dos interruptores y no uno**, porque son dos peticiones distintas: leer
 * un texto ajeno es un rato, y ser compañero de escritura es un compromiso
 * que dura. Con uno solo habría que renunciar a las dos para librarse de una.
 *
 * Los dos empiezan **abiertos**, y explícitamente: una fila que falta no
 * puede significar a la vez «no ha decidido» y «acepta todo». Cuando no hay
 * fila se leen los mismos valores por defecto, dichos en voz alta.
 */
class UserReceptionSettings
{
    private string $userId;

    private bool $betaReaderInvitations;

    private bool $writingBuddyProposals;

    private \DateTimeImmutable $updatedAt;

    public function __construct(UserId $userId, \DateTimeImmutable $now)
    {
        $this->userId = $userId->value();
        $this->betaReaderInvitations = true;
        $this->writingBuddyProposals = true;
        $this->updatedAt = $now;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function acceptsBetaReaderInvitations(): bool
    {
        return $this->betaReaderInvitations;
    }

    public function acceptsWritingBuddyProposals(): bool
    {
        return $this->writingBuddyProposals;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function change(
        bool $betaReaderInvitations,
        bool $writingBuddyProposals,
        \DateTimeImmutable $now,
    ): void {
        $this->betaReaderInvitations = $betaReaderInvitations;
        $this->writingBuddyProposals = $writingBuddyProposals;
        $this->updatedAt = $now;
    }
}
