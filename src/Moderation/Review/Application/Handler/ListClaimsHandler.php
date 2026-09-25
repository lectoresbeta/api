<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Handler;

use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Review\Application\DTO\ClaimInQueue;
use LectoresBeta\Moderation\Review\Application\Query\ListClaims;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * La cola del moderador (`FEAT-MOD-002`).
 *
 * **Excluye las reclamaciones en las que es parte, no las rechaza después**
 * (`RN-1`). La ficha insiste en la diferencia y tiene razón: ver el
 * expediente de una reclamación que te señala ya es enterarte de quién te
 * denunció, aunque el botón de resolver no funcione.
 *
 * No lleva identidades. El moderador decide sobre **lo que se escribió** —el
 * texto reclamado, el motivo y lo que contó quien reclama—, y saber de quién
 * es antes de mirarlo solo puede inclinar la decisión. Quien necesite saberlo
 * para actuar lo tiene en el objeto reclamado, con las comprobaciones de su
 * propio contexto.
 */
final readonly class ListClaimsHandler
{
    /** Una cola es para trabajarla, no para mirarla entera. */
    private const MAX_PAGE = 100;

    public function __construct(private ClaimRepository $claims)
    {
    }

    /**
     * @return list<ClaimInQueue>
     */
    public function __invoke(ListClaims $query): array
    {
        try {
            $moderator = PartyId::fromString($query->moderatorId);
        } catch (InvalidValue) {
            return [];
        }

        $open = $this->claims->openExcludingParty(
            $moderator,
            max(1, min(self::MAX_PAGE, $query->limit)),
            max(0, $query->offset),
        );

        return array_map(
            static fn (Claim $claim): ClaimInQueue => new ClaimInQueue(
                $claim->id()->value(),
                $claim->type()->value,
                $claim->targetType()->value,
                $claim->targetId(),
                $claim->reason()->value,
                $claim->description(),
                $claim->status()->value,
                $claim->submittedAt()->format(\DATE_ATOM),
            ),
            $open,
        );
    }
}
