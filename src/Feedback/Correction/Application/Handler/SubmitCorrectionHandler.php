<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Command\SubmitCorrection;
use LectoresBeta\Feedback\Correction\Application\Service\EligibleCorrectionBrief;
use LectoresBeta\Feedback\Correction\Domain\Entity\Correction;
use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectionAnswer;
use LectoresBeta\Feedback\Correction\Domain\Event\CorrectionStarted;
use LectoresBeta\Feedback\Correction\Domain\Event\FeedbackSubmitted;
use LectoresBeta\Feedback\Correction\Domain\Exception\ChapterNotCorrectable;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionAnswerRepository;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\Service\AnswerValidator;
use LectoresBeta\Feedback\Correction\Domain\Service\AnswerWordCounter;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionAnswerId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\QuestionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\QuestionRequirement;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Application\Contract\BriefQuestion;

/**
 * Delivering a correction (`FEAT-FBK-003`).
 *
 * **This is the use case the product exists for.** Everything else —
 * discovering works, asking for access, reading — is scaffolding around this
 * moment, and it is the only one that moves credits.
 *
 * What it does not do is move them. It publishes a fact: *this reader
 * delivered this correction*. `Credits` decides what that is worth, charges
 * the author and pays the reader, and this context never learns either figure
 * ([`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)).
 * The HTTP response does not wait for any of it: what the reader needs to
 * know is that their work is registered.
 *
 * A correction is answered against **the version it started with**. The
 * author may have rewritten the questionnaire in the meantime, and refusing
 * the delivery would punish the reader for somebody else's edit.
 */
final readonly class SubmitCorrectionHandler
{
    public function __construct(
        private EligibleCorrectionBrief $eligible,
        private CorrectionRepository $corrections,
        private CorrectionAnswerRepository $answers,
        private AnswerValidator $validator,
        private AnswerWordCounter $words,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(SubmitCorrection $command): string
    {
        $brief = $this->eligible->for($command->chapterId, $command->readerId);

        $chapterId = ChapterId::fromString($command->chapterId);
        $readerId = ReaderId::fromString($command->readerId);
        $workId = WorkId::fromString($brief->workId);
        $authorId = AuthorId::fromString($brief->authorId);
        $now = $this->clock->now();

        $correction = $this->corrections->ofReaderAndChapter($readerId, $chapterId);

        if (null !== $correction && !$correction->isDraft()) {
            // Immutable once delivered: the author has already paid for it
            // (`RN-3`).
            throw ChapterNotCorrectable::becauseItWasAlreadyCorrected();
        }

        $opened = null === $correction;

        // Somebody who wrote the whole thing without ever opening a panel —
        // a script, or a client that lost its draft. Starting it now keeps
        // the two facts in the order `Credits` expects: a price is quoted,
        // then charged.
        $correction ??= Correction::start(
            CorrectionId::generate(),
            $workId,
            $chapterId,
            $readerId,
            $authorId,
            $brief->questionnaireVersion,
            $now,
        );

        $this->validator->validate(self::required($brief->questions), $command->answers);

        $written = $this->written($correction, $brief->questions, $command->answers, $now);
        $correction->submit($now);

        $this->session->execute(function () use ($correction, $written): void {
            $this->corrections->save($correction);

            foreach ($written as $answer) {
                $this->answers->save($answer);
            }
        });

        $announcements = [];

        if ($opened) {
            $announcements[] = new CorrectionStarted(
                EventId::generate(),
                $chapterId,
                $workId,
                $authorId,
                $readerId,
                $now,
            );
        }

        $announcements[] = new FeedbackSubmitted(
            EventId::generate(),
            $correction->id(),
            $chapterId,
            $workId,
            $authorId,
            $readerId,
            $correction->questionnaireVersion(),
            $now,
        );

        $this->events->publish(...$announcements);

        return $correction->id()->value();
    }

    /**
     * @param list<BriefQuestion> $questions
     *
     * @return list<QuestionRequirement>
     */
    private static function required(array $questions): array
    {
        return array_map(
            static fn (BriefQuestion $question): QuestionRequirement => new QuestionRequirement(
                QuestionId::fromString($question->questionId),
                $question->position,
                $question->required,
                $question->minWords,
                $question->maxWords,
            ),
            $questions,
        );
    }

    /**
     * @param list<BriefQuestion>   $questions
     * @param array<string, string> $answers
     *
     * @return list<CorrectionAnswer>
     */
    private function written(Correction $correction, array $questions, array $answers, \DateTimeImmutable $now): array
    {
        $existing = [];

        foreach ($this->answers->ofCorrection($correction->id()) as $answer) {
            $existing[$answer->questionId()->value()] = $answer;
        }

        $written = [];

        foreach ($questions as $question) {
            $text = trim($answers[$question->questionId] ?? '');

            if ('' === $text && !isset($existing[$question->questionId])) {
                // An optional question nobody answered leaves no row behind.
                continue;
            }

            $answer = $existing[$question->questionId] ?? new CorrectionAnswer(
                CorrectionAnswerId::generate(),
                $correction->id(),
                QuestionId::fromString($question->questionId),
                $question->position,
                $now,
            );
            $answer->write($text, $this->words->count($text), $now);

            $written[] = $answer;
        }

        return $written;
    }
}
