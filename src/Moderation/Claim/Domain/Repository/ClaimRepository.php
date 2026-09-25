<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Domain\Repository;

use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimTargetType;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;

interface ClaimRepository
{
    public function save(Claim $claim): void;

    public function ofId(ClaimId $id): ?Claim;

    /**
     * La reclamación que esa persona ya presentó sobre ese objeto, si la hay.
     *
     * Es `RN-2`: nadie reclama dos veces lo mismo, y reintentar devuelve la
     * que ya existe en vez de crear otra. Diez denuncias de una persona sobre
     * un texto no son diez señales, son la misma repetida.
     */
    public function of(PartyId $reporterId, ClaimTargetType $targetType, string $targetId): ?Claim;

    /**
     * Cuántas ha presentado desde ese instante, para el cupo de `RN-6`.
     */
    public function countBy(PartyId $reporterId, \DateTimeImmutable $since): int;

    /**
     * @return list<Claim>
     */
    public function by(PartyId $reporterId): array;
}
