<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Handler;

use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimReason;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimTargetType;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Review\Application\DTO\ClaimInQueue;
use LectoresBeta\Moderation\Review\Application\DTO\ClaimQueue;
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
 *
 * **La prioridad es la antigüedad, y no se puede cambiar** (`FEAT-MOD-008`
 * `RN-1`). Lo que sí se puede es acotar por motivo y por clase de objeto, que
 * es otra cosa: elegir a qué dedicarse no es reordenar lo que a uno le
 * apetece atender antes. Dentro de lo acotado, sigue mandando quien lleva más
 * tiempo esperando.
 */
final readonly class ListClaimsHandler
{
    /** Una cola es para trabajarla, no para mirarla entera. */
    private const MAX_PAGE = 100;

    public function __construct(private ClaimRepository $claims)
    {
    }

    public function __invoke(ListClaims $query): ClaimQueue
    {
        try {
            $moderator = PartyId::fromString($query->moderatorId);
        } catch (InvalidValue) {
            return new ClaimQueue([], 0);
        }

        // Un filtro que no es un valor del catálogo **se rechaza**, no se
        // ignora. Quien modera está acotando su cola, y devolverle la cola
        // entera porque escribió mal un motivo le haría creer que de ese
        // motivo hay muchas más de las que hay.
        $reason = self::reason($query->reason);
        $targetType = self::targetType($query->targetType);

        $open = $this->claims->openExcludingParty(
            $moderator,
            max(1, min(self::MAX_PAGE, $query->limit)),
            max(0, $query->offset),
            $reason,
            $targetType,
        );

        $claims = array_map(
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

        return new ClaimQueue($claims, $this->claims->countOpenExcludingParty($moderator, $reason, $targetType));
    }

    private static function reason(?string $reason): ?ClaimReason
    {
        return null === $reason || '' === $reason
            ? null
            : ClaimReason::tryFrom(strtoupper($reason)) ?? throw InvalidValue::because('That is not a reason a claim can have.');
    }

    private static function targetType(?string $targetType): ?ClaimTargetType
    {
        return null === $targetType || '' === $targetType
            ? null
            : ClaimTargetType::tryFrom(strtoupper($targetType)) ?? throw InvalidValue::because('That is not something a claim can be about.');
    }
}
