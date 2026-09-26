<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Application\Handler;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Legal\Application\Query\ListMyLegalAcceptances;
use LectoresBeta\User\Legal\Domain\Entity\LegalAcceptance;
use LectoresBeta\User\Legal\Domain\Repository\LegalAcceptanceRepository;

/**
 * Qué acepté y cuándo (`FEAT-USR-024` `RN-6`).
 *
 * **Es el derecho a ver la prueba**, y por eso el listado no resume: devuelve
 * cada aceptación con su documento, su versión y su fecha. Un «aceptaste las
 * condiciones» sin versión sería exactamente el booleano que `RN-2` evita.
 *
 * Solo las propias, y no hay forma de pedir las de otra persona.
 */
final readonly class ListMyLegalAcceptancesHandler
{
    public function __construct(private LegalAcceptanceRepository $acceptances)
    {
    }

    /**
     * @return list<LegalAcceptance>
     */
    public function __invoke(ListMyLegalAcceptances $query): array
    {
        try {
            return $this->acceptances->ofUser(UserId::fromString($query->userId));
        } catch (InvalidValue) {
            return [];
        }
    }
}
