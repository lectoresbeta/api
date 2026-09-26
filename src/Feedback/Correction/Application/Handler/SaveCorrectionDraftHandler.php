<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Command\SaveCorrectionDraft;
use LectoresBeta\Feedback\Correction\Application\DTO\SavedDraft;
use LectoresBeta\Feedback\Correction\Application\Service\EligibleCorrectionBrief;
use LectoresBeta\Feedback\Correction\Application\Service\WriteAnswers;
use LectoresBeta\Feedback\Correction\Domain\Entity\Correction;
use LectoresBeta\Feedback\Correction\Domain\Event\CorrectionStarted;
use LectoresBeta\Feedback\Correction\Domain\Exception\ChapterNotCorrectable;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionAnswerRepository;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\Service\AnswerValidator;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * «Guardar» (`FEAT-FBK-011`).
 *
 * Una corrección seria de una obra larga no se escribe de una sentada. Sin
 * esto, quien la intenta tiene dos salidas —escribir en otro sitio y pegar, o
 * perder el trabajo— y las dos empeoran el feedback, que es el producto.
 *
 * **No publica nada y no mueve un solo crédito.** Un borrador a medias no es
 * un hecho de negocio, y avisar de que alguien está escribiendo sería
 * precisamente lo que `RN-4` evita: el autor sabría que hay una crítica en
 * camino y el lector sentiría la presión de enviarla.
 *
 * Solo se valida el **techo** de palabras. Rechazar un borrador por corto
 * sería impedir guardar.
 *
 * Guardar en un capítulo que no se había empezado **lo empieza**: escribir es
 * la señal más clara posible de que alguien está corrigiendo, y exigir dos
 * botones para que el sistema se entere sería inventar un trámite.
 */
final readonly class SaveCorrectionDraftHandler
{
    public function __construct(
        private EligibleCorrectionBrief $eligible,
        private CorrectionRepository $corrections,
        private CorrectionAnswerRepository $answers,
        private AnswerValidator $validator,
        private WriteAnswers $write,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(SaveCorrectionDraft $command): SavedDraft
    {
        $brief = $this->eligible->for($command->chapterId, $command->readerId);

        $chapterId = ChapterId::fromString($command->chapterId);
        $readerId = ReaderId::fromString($command->readerId);
        $workId = WorkId::fromString($brief->workId);
        $authorId = AuthorId::fromString($brief->authorId);
        $now = $this->clock->now();

        $correction = $this->corrections->ofReaderAndChapter($readerId, $chapterId);

        if (null !== $correction && !$correction->isDraft()) {
            throw ChapterNotCorrectable::becauseItWasAlreadyCorrected();
        }

        $started = null === $correction;
        $correction ??= Correction::start(
            CorrectionId::generate(),
            $workId,
            $chapterId,
            $readerId,
            $authorId,
            $brief->questionnaireVersion,
            $now,
        );

        $this->validator->validateDraft($this->write->requirementsOf($brief->questions), $command->answers);

        $written = $this->write->onto($correction, $brief->questions, $command->answers, $now);

        $this->session->execute(function () use ($correction, $written): void {
            $this->corrections->save($correction);

            foreach ($written as $answer) {
                $this->answers->save($answer);
            }
        });

        if ($started) {
            $this->events->publish(new CorrectionStarted(
                EventId::generate(),
                $chapterId,
                $workId,
                $authorId,
                $readerId,
                $now,
            ));
        }

        return new SavedDraft(
            $correction->id()->value(),
            $correction->questionnaireVersion(),
            // El autor puede haber reescrito el cuestionario mientras el
            // lector escribía, y el lector tiene derecho a enterarse antes de
            // seguir: las preguntas que ve ya no son las que responde.
            $correction->questionnaireVersion() !== $brief->questionnaireVersion,
            $started,
        );
    }
}
