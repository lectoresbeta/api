<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Domain\Entity;

use LectoresBeta\Community\Curation\Domain\Exception\MuteRefused;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

/**
 * Alguien a quien no quiero leer en mi muro (`FEAT-COM-033`).
 *
 * **Silenciar no es bloquear.** Esto es una preferencia de visualización y no
 * toca nada más: el seguimiento sigue, esa persona puede comentarme,
 * escribirme y mencionarme, sus comentarios en hilos ajenos se siguen viendo,
 * y su perfil y sus obras también. Bloquear (`FEAT-COM-034`) es una regla de
 * acceso que atraviesa cuatro contextos; esto vive entero en `Community`.
 *
 * Por eso **no publica ningún evento**: un consumidor que reaccionara a un
 * silencio estaría convirtiendo una preferencia de pantalla en una regla del
 * sistema.
 */
class MutedMember
{
    private string $memberId;

    private string $mutedId;

    private \DateTimeImmutable $mutedAt;

    public function __construct(MemberId $memberId, MemberId $mutedId, \DateTimeImmutable $now)
    {
        // Una regla de negocio, no una comprobación defensiva: quien la
        // incumple recibe un `422` con su código, no un `500`.
        if ($memberId->value() === $mutedId->value()) {
            throw MuteRefused::yourself();
        }

        $this->memberId = $memberId->value();
        $this->mutedId = $mutedId->value();
        $this->mutedAt = $now;
    }

    public function memberId(): MemberId
    {
        return MemberId::fromString($this->memberId);
    }

    public function mutedId(): MemberId
    {
        return MemberId::fromString($this->mutedId);
    }

    public function mutedAt(): \DateTimeImmutable
    {
        return $this->mutedAt;
    }
}
