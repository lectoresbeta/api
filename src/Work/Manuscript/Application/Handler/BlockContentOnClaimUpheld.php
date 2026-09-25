<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Domain\Entity\Chapter;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Application\Event\ClaimUpheld;
use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Event\WorkBlockedByModeration;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Bloquear lo reclamado (`FEAT-MOD-003`).
 *
 * **Se bloquea lo que se reclamó**: si la reclamación era sobre un capítulo,
 * ese capítulo; si era sobre la obra, la obra entera. Bloquear una novela de
 * treinta capítulos por uno solo destruiría el trabajo de los otros
 * veintinueve y las correcciones que otros escribieron sobre ellos.
 *
 * Y un umbral, que impide el extremo contrario: **al tercer capítulo
 * bloqueado cae la obra entera** (`RN-9`). Una obra cuyos capítulos van
 * cayendo uno a uno seguiría publicada indefinidamente, ofreciendo una
 * lectura llena de huecos.
 *
 * Bloquear **no borra nada** (`RN-6`): el autor la sigue viendo, marcada, y
 * el contenido reclamado se conserva, que es justamente lo que hace falta si
 * alguien discute la decisión. Salir de `BLOCKED` no lo hace ninguna
 * transición ordinaria: solo un moderador.
 *
 * ## Idempotencia
 *
 * Un mensaje puede llegar dos veces, así que lo que decide si se publica el
 * hecho es **si el estado ha cambiado de verdad**. `block()` es idempotente
 * —guarda el primer instante y no lo pisa—, y la segunda entrega no anuncia
 * nada: un correo de bloqueo repetido es, para quien lo recibe, un segundo
 * bloqueo.
 */
final readonly class BlockContentOnClaimUpheld
{
    /** `RN-9`. Tres capítulos bloqueados y la obra entera cae. */
    public const CHAPTERS_BEFORE_THE_WHOLE_WORK = 3;

    private const WORK = 'WORK';
    private const CHAPTER = 'CHAPTER';

    public function __construct(
        private WorkRepository $works,
        private ChapterRepository $chapters,
        private EventPublisher $events,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(ClaimUpheld $event): void
    {
        $announcements = match ($event->targetType) {
            self::WORK => $this->blockTheWholeWork($event),
            self::CHAPTER => $this->blockTheChapter($event),
            // Correcciones, usuarios y publicaciones no cambian nada aquí.
            // Un contexto que reacciona a todo acaba sabiendo de todo.
            default => [],
        };

        if ([] !== $announcements) {
            $this->events->publish(...$announcements);
        }
    }

    /**
     * @return list<IntegrationEvent>
     */
    private function blockTheWholeWork(ClaimUpheld $event): array
    {
        $work = $this->workOf($event->targetId);

        if (null === $work || $work->isBlocked()) {
            return [];
        }

        $now = $this->clock->now();
        $work->block($now);

        $this->session->execute(fn () => $this->works->save($work));

        return [self::announce($work, self::WORK, null, $event->type, $event->claimId, $now)];
    }

    /**
     * @return list<IntegrationEvent>
     */
    private function blockTheChapter(ClaimUpheld $event): array
    {
        $chapter = $this->chapterOf($event->targetId);

        if (null === $chapter || $chapter->isBlocked()) {
            return [];
        }

        $work = $this->works->ofId($chapter->workId());

        if (null === $work) {
            return [];
        }

        $now = $this->clock->now();
        $chapter->block($now);

        $this->session->execute(fn () => $this->chapters->save($chapter));

        // El umbral se cuenta **después de confirmar** el bloqueo de este
        // capítulo, porque es el que puede ser el tercero: contarlo dentro de
        // la misma transacción leería un estado en el que todavía no está
        // bloqueado, y la obra no caería nunca.
        $blocked = $this->chapters->countBlockedOfWork($work->id());

        $announcements = [
            self::announce($work, self::CHAPTER, $chapter->id()->value(), $event->type, $event->claimId, $now),
        ];

        if ($blocked >= self::CHAPTERS_BEFORE_THE_WHOLE_WORK && !$work->isBlocked()) {
            $work->block($now);
            $this->session->execute(fn () => $this->works->save($work));

            $announcements[] = self::announce(
                $work,
                self::WORK,
                null,
                WorkBlockedByModeration::CHAPTER_THRESHOLD,
                null,
                $now,
            );
        }

        return $announcements;
    }

    private function workOf(string $id): ?Work
    {
        try {
            return $this->works->ofId(WorkId::fromString($id));
        } catch (InvalidValue) {
            return null;
        }
    }

    private function chapterOf(string $id): ?Chapter
    {
        try {
            return $this->chapters->ofId(ChapterId::fromString($id));
        } catch (InvalidValue) {
            return null;
        }
    }

    private static function announce(
        Work $work,
        string $scope,
        ?string $chapterId,
        string $reason,
        ?string $claimId,
        \DateTimeImmutable $now,
    ): WorkBlockedByModeration {
        return new WorkBlockedByModeration(
            EventId::generate(),
            $work->id()->value(),
            $work->authorId()->value(),
            (string) $work->title(),
            $scope,
            $chapterId,
            $reason,
            $claimId,
            $now,
        );
    }
}
