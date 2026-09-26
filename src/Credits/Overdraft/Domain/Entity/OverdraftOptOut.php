<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Domain\Entity;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;

/**
 * Quién ha dicho que no quiere el gancho de reactivación (`FEAT-CRD-019`
 * `RN-2d`, `RN-8`).
 *
 * Es una copia local de una decisión que se toma en otro contexto, y vive
 * aquí porque `Credits` **no puede preguntar**
 * ([`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)):
 * lo que llega es el hecho por la cola y lo que queda es esta fila.
 *
 * Solo se guarda **quién ha renunciado**, no la respuesta de todo el mundo.
 * La mayoría no ha tocado nada, y una tabla con una fila por usuario para
 * decir «no ha dicho nada» es una tabla que hay que rellenar al dar de alta a
 * alguien y mantener al borrarlo.
 */
class OverdraftOptOut
{
    private string $userId;

    private \DateTimeImmutable $declinedAt;

    public function __construct(UserId $userId, \DateTimeImmutable $now)
    {
        $this->userId = $userId->value();
        $this->declinedAt = $now;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function declinedAt(): \DateTimeImmutable
    {
        return $this->declinedAt;
    }
}
