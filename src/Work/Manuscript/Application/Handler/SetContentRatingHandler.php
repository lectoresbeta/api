<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Application\Command\SetContentRating;
use LectoresBeta\Work\Manuscript\Application\DTO\ContentRating;
use LectoresBeta\Work\Manuscript\Domain\Enum\ContentWarning;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkContentWarningRepository;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\Service\ContentRatingPolicy;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Declarar qué contiene una obra, y para quién (`FEAT-WRK-017`).
 *
 * Se puede en cualquier momento (`RN-1`) y **no afecta a las correcciones en
 * curso** (`RN-5`): eso no es una comprobación que haga falta escribir, es
 * una consecuencia de que aquí no se toque nada más que la obra. Lo que
 * cambia es quién la encuentra a partir de ahora.
 *
 * No publica ningún evento, por la misma razón que reclasificar por temática
 * tampoco: los consumidores que la ficha prevé —`Community` para el muro,
 * `Moderation` para juzgar una reclamación— no existen todavía, y publicar un
 * hecho que nadie escucha es inventarse un contrato que luego hay que
 * mantener. Cuando el primero exista, `WorkContentRatingSet` se publica desde
 * aquí.
 */
final readonly class SetContentRatingHandler
{
    public function __construct(
        private WorkRepository $works,
        private WorkContentWarningRepository $warnings,
        private ContentRatingPolicy $policy,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(SetContentRating $command): ContentRating
    {
        $work = $this->works->ofId(WorkId::fromString($command->workId));

        // Ajena y no existente son la misma respuesta: confirmar que una obra
        // existe ya es decir algo de ella.
        if (null === $work || !$work->authorId()->equals(AuthorId::fromString($command->authorId))) {
            throw WorkNotFound::create();
        }

        // Se valida antes de tocar nada: una declaración a medias no deja la
        // obra clasificada a medias.
        $adultsOnly = $this->policy->audience($command->adultsOnly);
        $declared = $this->policy->warnings($command->contentWarnings);

        $this->session->execute(function () use ($work, $adultsOnly, $declared): void {
            $work->classify($adultsOnly, $this->clock->now());
            $this->warnings->replaceAll($work->id(), $declared);
        });

        return new ContentRating($adultsOnly, array_map(
            static fn (ContentWarning $warning): string => $warning->value,
            $declared,
        ));
    }
}
