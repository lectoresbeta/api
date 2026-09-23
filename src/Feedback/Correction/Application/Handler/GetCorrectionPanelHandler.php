<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\DTO\CorrectionPanelView;
use LectoresBeta\Feedback\Correction\Application\DTO\PanelQuestionView;
use LectoresBeta\Feedback\Correction\Application\Query\GetCorrectionPanel;
use LectoresBeta\Feedback\Correction\Application\Service\EligibleCorrectionBrief;
use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectionAnswer;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionAnswerRepository;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Work\Chapter\Application\Contract\BriefQuestion;

/**
 * The correction panel: what the author asks about this chapter, and what
 * this reader has written so far (`FEAT-FBK-003`).
 *
 * **One call and not two.** Which questions apply depends on the chapter and
 * the draft belongs to the chapter, so splitting them would make opening the
 * panel a round trip that can arrive half-done.
 *
 * It is a pure read: opening the questionnaire neither takes one of the
 * chapter's three slots nor fixes a price. Starting is a separate, explicit
 * act.
 */
final readonly class GetCorrectionPanelHandler
{
    public function __construct(
        private EligibleCorrectionBrief $eligible,
        private CorrectionRepository $corrections,
        private CorrectionAnswerRepository $answers,
    ) {
    }

    public function __invoke(GetCorrectionPanel $query): CorrectionPanelView
    {
        $brief = $this->eligible->for($query->chapterId, $query->readerId);

        $correction = $this->corrections->ofReaderAndChapter(
            ReaderId::fromString($query->readerId),
            ChapterId::fromString($query->chapterId),
        );

        $written = [];

        if (null !== $correction) {
            foreach ($this->answers->ofCorrection($correction->id()) as $answer) {
                $written[$answer->questionId()->value()] = $answer;
            }
        }

        return new CorrectionPanelView(
            $query->chapterId,
            $brief->workId,
            // The version on screen is the one in force; a correction already
            // under way keeps answering the one it started with, and the
            // author is told so when it arrives.
            $correction?->questionnaireVersion() ?? $brief->questionnaireVersion,
            $correction?->id()->value(),
            $correction?->status()->value ?? 'NOT_STARTED',
            array_map(
                static fn (BriefQuestion $question): PanelQuestionView => new PanelQuestionView(
                    $question->questionId,
                    $question->position,
                    $question->statement,
                    $question->example,
                    $question->required,
                    $question->minWords,
                    $question->maxWords,
                    self::written($written, $question->questionId),
                ),
                $brief->questions,
            ),
        );
    }

    /**
     * @param array<string, CorrectionAnswer> $written
     */
    private static function written(array $written, string $questionId): string
    {
        return isset($written[$questionId]) ? $written[$questionId]->text() : '';
    }
}
