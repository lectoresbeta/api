<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderGroup\Application\DTO\BetaReaderGroupSummary;
use LectoresBeta\Reading\BetaReaderGroup\Application\Query\ListMyBetaReaderGroups;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Entity\BetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Repository\BetaReaderGroupMemberRepository;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Repository\BetaReaderGroupRepository;

/**
 * Mis listas, con cuánta gente hay en cada una (`FEAT-RDG-007`).
 *
 * **No pagina**: el tope son cincuenta y caben en una pantalla. Lo que sí
 * admite es buscar por nombre (`RN-13`, cierra `R-20`), que es lo que hace
 * falta cuando uno tiene treinta.
 *
 * Los recuentos se piden **de una vez** para los grupos que salen. Una
 * consulta por fila es la forma habitual de que una lista de cincuenta
 * elementos haga cincuenta y una consultas.
 */
final readonly class ListMyBetaReaderGroupsHandler
{
    public function __construct(
        private BetaReaderGroupRepository $groups,
        private BetaReaderGroupMemberRepository $members,
    ) {
    }

    /**
     * @return list<BetaReaderGroupSummary>
     */
    public function __invoke(ListMyBetaReaderGroups $query): array
    {
        $groups = $this->groups->ofAuthor(AuthorId::fromString($query->authorId), self::term($query->query));

        if ([] === $groups) {
            return [];
        }

        $counts = $this->members->countsOf(array_map(
            static fn (BetaReaderGroup $group) => $group->id(),
            $groups,
        ));

        return array_map(
            static fn (BetaReaderGroup $group): BetaReaderGroupSummary => new BetaReaderGroupSummary(
                $group->id()->value(),
                $group->name()->value(),
                $counts[$group->id()->value()] ?? 0,
                $group->createdAt(),
            ),
            $groups,
        );
    }

    private static function term(?string $query): ?string
    {
        $trimmed = trim($query ?? '');

        return '' === $trimmed ? null : $trimmed;
    }
}
