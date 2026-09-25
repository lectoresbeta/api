<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Referral\Domain\Entity;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;

/**
 * Quién trajo a quién, tal y como `Credits` necesita saberlo
 * (`FEAT-CRD-005`).
 *
 * **Es una proyección, no una copia de la invitación.** `User` sabe de
 * correos, tokens caducados y reenvíos; aquí no hace falta nada de eso. Lo
 * único que este contexto necesita para aplicar su regla es el par de
 * personas y si ya se pagó por él, y guardar más sería volver a modelar aquí
 * un concepto que es de otro.
 *
 * La clave es **el invitado**, no la invitación: a cada persona la trae
 * alguien una sola vez y para siempre. Con la invitación como clave, dos
 * invitaciones distintas a la misma persona —que `User` sí permite, porque
 * dos personas pueden invitarla— darían dos recompensas por una sola alta.
 */
class Referral
{
    private string $inviteeId;

    private string $inviterId;

    private \DateTimeImmutable $linkedAt;

    private ?\DateTimeImmutable $rewardedAt = null;

    public function __construct(UserId $inviteeId, UserId $inviterId, \DateTimeImmutable $now)
    {
        $this->inviteeId = $inviteeId->value();
        $this->inviterId = $inviterId->value();
        $this->linkedAt = $now;
    }

    public function inviteeId(): UserId
    {
        return UserId::fromString($this->inviteeId);
    }

    public function inviterId(): UserId
    {
        return UserId::fromString($this->inviterId);
    }

    public function linkedAt(): \DateTimeImmutable
    {
        return $this->linkedAt;
    }

    public function rewardedAt(): ?\DateTimeImmutable
    {
        return $this->rewardedAt;
    }

    public function wasRewarded(): bool
    {
        return null !== $this->rewardedAt;
    }

    /**
     * Devuelve `false` si ya se había pagado.
     *
     * Es la garantía de «una vez por persona invitada, para siempre»
     * (`decision:0006`, regla 6). Deduplicar por identificador de evento no
     * la cubre: la segunda corrección del invitado llega con un evento
     * distinto y legítimo, y pasaría de largo.
     */
    public function reward(\DateTimeImmutable $now): bool
    {
        if (null !== $this->rewardedAt) {
            return false;
        }

        $this->rewardedAt = $now;

        return true;
    }
}
