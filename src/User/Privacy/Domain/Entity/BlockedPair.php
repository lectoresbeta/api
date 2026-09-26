<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Que dos personas no se hablan, **copiado aquí** (`FEAT-COM-034`).
 *
 * Igual que la proyección de seguidores, y por la misma regla: `User`
 * responde el contrato `AuthorAudience` —qué autor acepta comentarios de
 * quién— y un contrato no puede llamar al de otro contexto mientras responde
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * Guarda **el par ordenado**, no «quién bloqueó a quién», y es deliberado: lo
 * único que se pregunta aquí es si hay bloqueo entre dos personas, porque el
 * efecto sobre los comentarios es bidireccional. Guardar la dirección
 * invitaría a usarla, y quien la usara acabaría respondiendo distinto a cada
 * lado de un bloqueo que corta en los dos.
 *
 * Quién bloqueó a quién sigue estando donde debe: en `Community`, que es su
 * dueño y el único sitio donde se puede deshacer.
 */
class BlockedPair
{
    private string $oneId;

    private string $otherId;

    private \DateTimeImmutable $blockedAt;

    public function __construct(UserId $one, UserId $other, \DateTimeImmutable $blockedAt)
    {
        [$this->oneId, $this->otherId] = self::ordered($one->value(), $other->value());
        $this->blockedAt = $blockedAt;
    }

    /**
     * El par, siempre en el mismo orden. Es lo que permite que la clave
     * primaria sea el par y que reprocesar un hecho escriba la misma fila,
     * viniera de quien viniera el bloqueo.
     *
     * @return array{string, string}
     */
    public static function ordered(string $one, string $other): array
    {
        return $one <= $other ? [$one, $other] : [$other, $one];
    }

    public function blockedAt(): \DateTimeImmutable
    {
        return $this->blockedAt;
    }
}
