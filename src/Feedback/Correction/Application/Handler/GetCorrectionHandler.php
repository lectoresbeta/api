<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\DTO\AnsweredQuestionView;
use LectoresBeta\Feedback\Correction\Application\DTO\CorrectionDetail;
use LectoresBeta\Feedback\Correction\Application\Query\GetCorrection;
use LectoresBeta\Feedback\Correction\Domain\Entity\Correction;
use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectionAnswer;
use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionStatus;
use LectoresBeta\Feedback\Correction\Domain\Event\CorrectionRead;
use LectoresBeta\Feedback\Correction\Domain\Exception\CorrectionNotFound;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionAnswerRepository;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionReplyRepository;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Application\Contract\ChapterHeadings;
use LectoresBeta\Work\Questionnaire\Application\Contract\AnsweredQuestion;
use LectoresBeta\Work\Questionnaire\Application\Contract\AnsweredQuestionnaires;

/**
 * Una corrección entregada, entera (`FEAT-FBK-004`).
 *
 * **La leen dos personas y nadie más**: su destinatario y quien la escribió
 * (`RN-1`). Ni siquiera otro lector beta de la misma obra, que podría
 * deducir de ella qué le va a preguntar el autor.
 *
 * Los enunciados son **los de la versión que se respondió** y no los de
 * ahora. El autor puede haber reescrito el cuestionario mientras el lector
 * escribía, y una respuesta bajo una pregunta que ya no existe es una
 * respuesta sin pregunta.
 *
 * Retenida por descubierto: se devuelve la cabecera **sin las respuestas**
 * ([`FEAT-CRD-018`](../../../../../docs/features/credits/FEAT-CRD-018-negative-balance.md)),
 * y no se marca como leída, porque no se ha leído nada.
 */
final readonly class GetCorrectionHandler
{
    public function __construct(
        private CorrectionRepository $corrections,
        private CorrectionAnswerRepository $answers,
        private CorrectionReplyRepository $replies,
        private AnsweredQuestionnaires $questionnaires,
        private ChapterHeadings $chapters,
        private EventPublisher $events,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(GetCorrection $query): CorrectionDetail
    {
        $correction = $this->readable($query);
        $isOwner = $correction->ownerId()->value() === $query->readerId;

        $heading = $this->chapters->ofChapters([$correction->chapterId()->value()]);

        return new CorrectionDetail(
            $correction->id()->value(),
            $correction->workId()->value(),
            $correction->chapterId()->value(),
            ($heading[$correction->chapterId()->value()] ?? null)?->title,
            $correction->readerId()?->value(),
            $correction->authorLabel(),
            $correction->origin()->value,
            $correction->visibility()->value,
            $correction->questionnaireVersion(),
            ($correction->submittedAt() ?? $correction->startedAt())->format(\DATE_ATOM),
            $isOwner ? null !== $correction->readAt() : null,
            $correction->helpful(),
            $this->replies->ofCorrection($correction->id())?->body(),
            $correction->isReadable() ? $this->answersOf($correction) : [],
        );
    }

    /**
     * Quien no es parte recibe lo mismo que si no existiera. Que exista es
     * información sobre una obra que no es suya.
     */
    private function readable(GetCorrection $query): Correction
    {
        try {
            $correction = $this->corrections->ofId(CorrectionId::fromString($query->correctionId));
        } catch (InvalidValue) {
            throw CorrectionNotFound::withId($query->correctionId);
        }

        if (null === $correction || CorrectionStatus::SUBMITTED !== $correction->status()) {
            throw CorrectionNotFound::withId($query->correctionId);
        }

        $isOwner = $correction->ownerId()->value() === $query->readerId;
        $isWriter = $correction->readerId()?->value() === $query->readerId;

        if (!$isOwner && !$isWriter) {
            throw CorrectionNotFound::withId($query->correctionId);
        }

        if ($isOwner) {
            $this->markRead($correction);
        }

        return $correction;
    }

    /**
     * Se anuncia **después de confirmar**: un hecho publicado desde un estado
     * que luego se deshace es una mentira que ya no se puede retirar.
     */
    private function markRead(Correction $correction): void
    {
        $now = $this->clock->now();

        $justRead = $this->session->execute(function () use ($correction, $now): bool {
            if (!$correction->markRead($now)) {
                return false;
            }

            $this->corrections->save($correction);

            return true;
        });

        if ($justRead) {
            $this->events->publish(new CorrectionRead(
                EventId::generate(),
                $correction->id()->value(),
                $correction->ownerId()->value(),
                $now,
            ));
        }
    }

    /**
     * @return list<AnsweredQuestionView>
     */
    private function answersOf(Correction $correction): array
    {
        $statements = [];

        foreach ($this->questionnaires->questionsOf(
            $correction->workId()->value(),
            $correction->questionnaireVersion(),
        ) as $question) {
            $statements[$question->questionId] = $question;
        }

        $views = array_map(
            static function (CorrectionAnswer $answer) use ($statements): AnsweredQuestionView {
                /** @var AnsweredQuestion|null $question */
                $question = $statements[$answer->questionId()->value()] ?? null;

                return new AnsweredQuestionView(
                    $answer->questionId()->value(),
                    $question->position ?? 0,
                    $question->statement ?? '',
                    $answer->text(),
                    $answer->wordCount(),
                );
            },
            $this->answers->ofCorrection($correction->id()),
        );

        usort($views, static fn (AnsweredQuestionView $a, AnsweredQuestionView $b): int => $a->position <=> $b->position);

        return $views;
    }
}
