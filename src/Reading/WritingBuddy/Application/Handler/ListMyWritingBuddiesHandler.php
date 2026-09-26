<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\WritingBuddy\Application\DTO\WritingBuddyView;
use LectoresBeta\Reading\WritingBuddy\Application\Query\ListMyWritingBuddies;
use LectoresBeta\Reading\WritingBuddy\Domain\Entity\WritingBuddyLink;
use LectoresBeta\Reading\WritingBuddy\Domain\Repository\WritingBuddyLinkRepository;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Application\Contract\ProfileCards;

/**
 * Los vínculos vivos de quien pregunta: propuestos y aceptados
 * (`FEAT-RDG-009`).
 *
 * **Las dos cosas en una lista**, porque es una sola pantalla: lo que cambia
 * por fila es si hay que decidir algo o si toca esperar, y eso lo dicen
 * `status` y `proposedByMe`.
 *
 * Los resueltos no salen. Un vínculo rechazado hace dos meses no es una fila
 * que nadie quiera volver a ver, y guardarlo a la vista sería un histórico de
 * desaires.
 *
 * Los perfiles se piden **de golpe** y por `ProfileCards`, no por
 * `VisibleProfiles`: quien tiene un vínculo con alguien ya sabe quién es, y
 * filtrar por privacidad haría desaparecer de la lista a quien cerrara su
 * perfil — con él, la posibilidad de responderle.
 */
final readonly class ListMyWritingBuddiesHandler
{
    private const MAX_PAGE = 100;

    public function __construct(
        private WritingBuddyLinkRepository $links,
        private ProfileCards $profiles,
    ) {
    }

    /**
     * @return list<WritingBuddyView>
     */
    public function __invoke(ListMyWritingBuddies $query): array
    {
        try {
            $reader = ReaderId::fromString($query->readerId);
        } catch (InvalidValue) {
            return [];
        }

        $found = $this->links->liveOf(
            $reader,
            max(1, min(self::MAX_PAGE, $query->limit)),
            max(0, $query->offset),
        );

        if ([] === $found) {
            return [];
        }

        $others = $this->profiles->of(array_values(array_unique(array_map(
            static fn (WritingBuddyLink $link): string => $link->otherThan($reader)->value(),
            $found,
        ))));

        return array_map(
            static fn (WritingBuddyLink $link): WritingBuddyView => new WritingBuddyView(
                $link->id()->value(),
                // Nulo cuando la otra parte es una cuenta eliminada: la fila
                // sigue, porque el vínculo existió.
                $others[$link->otherThan($reader)->value()] ?? null,
                $link->status()->value,
                $link->proposedBy()->value() === $reader->value(),
                $link->proposedAt(),
            ),
            $found,
        );
    }
}
