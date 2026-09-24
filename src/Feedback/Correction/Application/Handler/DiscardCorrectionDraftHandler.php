<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Command\DiscardCorrectionDraft;
use LectoresBeta\Feedback\Correction\Domain\Event\CorrectionDraftDiscarded;
use LectoresBeta\Feedback\Correction\Domain\Exception\ChapterNotCorrectable;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionAnswerRepository;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * «Descartar» (`FEAT-FBK-011` `RN-7`).
 *
 * Guardar no es un hecho de negocio y descartar **sí**, y la asimetría tiene
 * motivo: empezar ocupó dos cosas que ahora hay que soltar. La cotización del
 * precio, y con ella uno de los tres sitios de corrección del capítulo
 * ([`FEAT-CRD-009`](../../../../../docs/features/credits/FEAT-CRD-009-balance-check-on-correction-start.md)),
 * y el acceso de lector beta si nació de esta corrección
 * ([`FEAT-RDG-001`](../../../../../docs/features/reading/FEAT-RDG-001-become-beta-reader-by-correcting.md)).
 *
 * Nada de eso se decide aquí. Este contexto publica que alguien se echó
 * atrás; cada consumidor sabe qué deshacer.
 *
 * **No se exige que el lector siga pudiendo corregir.** Descartar es la
 * salida, y cerrársela a quien perdió el acceso o cuya obra se cerró dejaría
 * borradores imposibles de borrar ocupando sitio para siempre.
 *
 * Descartar algo que no existe no es un error: el resultado que pedía —que no
 * haya borrador— ya se cumple.
 */
final readonly class DiscardCorrectionDraftHandler
{
    public function __construct(
        private CorrectionRepository $corrections,
        private CorrectionAnswerRepository $answers,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(DiscardCorrectionDraft $command): void
    {
        $chapterId = ChapterId::fromString($command->chapterId);
        $readerId = ReaderId::fromString($command->readerId);
        $correction = $this->corrections->ofReaderAndChapter($readerId, $chapterId);

        if (null === $correction) {
            return;
        }

        if (!$correction->isDraft()) {
            // Entregada es inmutable: el autor ya ha pagado por ella
            // (`FEAT-FBK-003` `RN-3`).
            throw ChapterNotCorrectable::becauseItWasAlreadyCorrected();
        }

        $workId = $correction->workId();

        $this->session->execute(function () use ($correction): void {
            $this->answers->removeAllOf($correction->id());
            $this->corrections->discardDraft($correction);
        });

        $this->events->publish(new CorrectionDraftDiscarded(
            EventId::generate(),
            $chapterId,
            $workId,
            $readerId,
            $this->clock->now(),
        ));
    }
}
