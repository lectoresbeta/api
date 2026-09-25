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
}
