<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Application\Handler;

use LectoresBeta\Moderation\Claim\Application\Query\ListMyClaims;
use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * Mis reclamaciones (`FEAT-MOD-010`).
 *
 * **Existe para que denunciar no sea gritar a un buzón.** Quien se molesta en
 * explicar por qué algo está mal necesita poder comprobar que su reclamación
 * sigue viva, y en qué estado; sin esta lista, el sistema pide un esfuerzo y
 * no devuelve nada a cambio.
 *
 * Solo las propias. Y nunca la identidad de quien la revisa (`FEAT-MOD-001`
 * `RN-7`): el moderador no da la cara ante las partes porque eso lo expondría
 * a quien acaba de ser sancionado.
 */
final readonly class ListMyClaimsHandler
{
    public function __construct(private ClaimRepository $claims)
    {
    }

    /**
     * @return list<Claim>
     */
    public function __invoke(ListMyClaims $query): array
    {
        try {
            return $this->claims->by(PartyId::fromString($query->reporterId));
        } catch (InvalidValue) {
            return [];
        }
    }
}
