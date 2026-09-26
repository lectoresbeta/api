<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Domain\Repository;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Sanction\Domain\Entity\Sanction;
use LectoresBeta\Moderation\Sanction\Domain\ValueObject\SanctionId;

interface SanctionRepository
{
    public function save(Sanction $sanction): void;

    public function ofId(SanctionId $sanctionId): ?Sanction;

    /**
     * El historial de esa persona, **caducadas incluidas** (`RN-5`). Es lo
     * que permite que la reincidencia pese: tres avisos dicen algo que uno
     * solo no dice.
     *
     * @return list<Sanction>
     */
    public function historyOf(PartyId $userId): array;

    /**
     * Lo que sigue en vigor ahora mismo sobre esa persona.
     *
     * @return list<Sanction>
     */
    public function inForceFor(PartyId $userId, \DateTimeImmutable $moment): array;

    /**
     * Las sanciones **que no se acaban solas y nadie ha levantado** (`MOD-25`).
     *
     * De la más antigua a la más reciente, que es el orden que importa: lo
     * que lleva meses sin revisarse es lo que se ha convertido en una
     * expulsión que nadie decidió. Las de plazo fijo no entran — terminan por
     * su fecha y no hay nada que recordar.
     *
     * @return list<Sanction>
     */
    public function openIndefinitely(int $limit, int $offset): array;

    /**
     * Cuántas hay, para que la cola diga si queda trabajo debajo.
     */
    public function countOpenIndefinitely(): int;
}
