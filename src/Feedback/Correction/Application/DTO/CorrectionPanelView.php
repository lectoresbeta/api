<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\DTO;

/**
 * The correction panel as the reader sees it: what is asked, and whatever
 * they had already written.
 */
final readonly class CorrectionPanelView
{
    /**
     * @param list<PanelQuestionView> $questions
     */
    public function __construct(
        public string $chapterId,
        public string $workId,
        public int $questionnaireVersion,
        public ?string $correctionId,
        public string $status,
        public array $questions,
    ) {
    }
}
