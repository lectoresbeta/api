<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Application\Command\UpdateWork;
use LectoresBeta\Work\Manuscript\Domain\Event\WorkUpdated;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkTitle;

/**
 * Editar el título y la sinopsis de una obra (`FEAT-WRK-005` `RN-9`).
 *
 * **No versiona nada y no toca el precio.** Nadie corrige una sinopsis, y el
 * precio de un capítulo sale de sus palabras y del cuestionario, no de la
 * portada. Arrastrar la obra al versionado duplicaría el modelo por un dato
 * que nadie discute.
 *
 * Una edición que no cambia nada tampoco es una edición (`RN-10`): ni
 * actualiza la fecha ni publica hecho alguno. Guardar sin tocar es lo más
 * frecuente que hace un editor de texto.
 */
final readonly class UpdateWorkHandler
{
    public function __construct(
        private WorkRepository $works,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateWork $command): void
    {
        $work = $this->ownedWork($command);

        $title = null === $command->title ? null : WorkTitle::fromString($command->title);
        $synopsis = $command->synopsisWasSent ? self::trimmed($command->synopsis) : null;

        $renames = null !== $title && $title->value() !== $work->title()->value();
        $redescribes = $command->synopsisWasSent && $synopsis !== $work->synopsis();

        if (!$renames && !$redescribes) {
            return;
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($work, $title, $synopsis, $renames, $redescribes, $now): void {
            if ($renames && null !== $title) {
                $work->rename($title, $now);
            }

            if ($redescribes) {
                $work->describe($synopsis, $now);
            }

            $this->works->save($work);
        });

        $this->events->publish(new WorkUpdated(
            EventId::generate(),
            $work->id(),
            $work->authorId(),
            $now,
        ));
    }

    /**
     * No es suya y no existe son la misma respuesta: confirmar que una obra
     * existe ya es una fuga.
     */
    private function ownedWork(UpdateWork $command): \LectoresBeta\Work\Manuscript\Domain\Entity\Work
    {
        try {
            $work = $this->works->ofId(WorkId::fromString($command->workId));
            $authorId = AuthorId::fromString($command->authorId);
        } catch (InvalidValue) {
            throw WorkNotFound::create();
        }

        if (null === $work || !$work->authorId()->equals($authorId)) {
            throw WorkNotFound::create();
        }

        return $work;
    }

    private static function trimmed(?string $synopsis): ?string
    {
        $text = trim((string) $synopsis);

        return '' === $text ? null : $text;
    }
}
