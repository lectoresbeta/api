<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Service;

use LectoresBeta\Work\Chapter\Application\Contract\BriefQuestion;
use LectoresBeta\Work\Chapter\Application\Contract\CorrectionBrief;
use LectoresBeta\Work\Chapter\Application\Contract\CorrectionBriefs;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Questionnaire\Domain\Entity\Question;
use LectoresBeta\Work\Questionnaire\Domain\Enum\QuestionScope;
use LectoresBeta\Work\Questionnaire\Domain\Repository\QuestionnaireRepository;

/**
 * `Work`'s side of the correction brief (`FEAT-FBK-003`).
 *
 * It reaches across two of this context's concepts — the chapter and the
 * questionnaire — which is exactly why it exists: assembling them **inside**
 * `Work` is what stops the caller from having to know that the last chapter
 * answers more questions than the rest.
 *
 * A blocked work or a blocked chapter yields nothing at all. Refusing later,
 * on the strength of a flag handed over here, would put a moderation decision
 * in somebody else's hands.
 */
final readonly class ResolveCorrectionBriefs implements CorrectionBriefs
{
    public function __construct(
        private ChapterRepository $chapters,
        private WorkRepository $works,
        private QuestionnaireRepository $questionnaires,
    ) {
    }

    public function ofChapter(string $chapterId): ?CorrectionBrief
    {
        $chapter = $this->chapters->ofId(ChapterId::fromString($chapterId));

        // Oculto y bloqueado dan lo mismo aquí: no hay nada que corregir.
        // Ocultar un capítulo lo cierra para empezar **y para entregar**,
        // porque los dos caminos pasan por este contrato (`FEAT-WRK-008`
        // `RN-3`, `RN-5`).
        if (null === $chapter || $chapter->isBlocked() || $chapter->isHidden()) {
            return null;
        }

        $work = $this->works->ofId($chapter->workId());

        if (null === $work || $work->isBlocked()) {
            return null;
        }

        $questionnaire = $this->questionnaires->currentOf($work->id());
        $questions = null === $questionnaire ? [] : $this->questionnaires->questionsOf($questionnaire->id());
        $isLast = $chapter->position() === $this->chapters->countOfWork($work->id());

        return new CorrectionBrief(
            $chapterId,
            $work->id()->value(),
            $work->authorId()->value(),
            $work->status()->acceptsNewCorrections(),
            $work->accessMode()->value,
            $work->isAdultsOnly(),
            $questionnaire?->version() ?? 0,
            $chapter->version(),
            $this->asked($questions, $isLast),
        );
    }

    /**
     * @param list<Question> $questions
     *
     * @return list<BriefQuestion>
     */
    private function asked(array $questions, bool $isLastChapter): array
    {
        $asked = [];

        foreach ($questions as $question) {
            if (QuestionScope::LAST_CHAPTER === $question->scope() && !$isLastChapter) {
                continue;
            }

            $asked[] = new BriefQuestion(
                $question->id()->value(),
                $question->position(),
                $question->statement(),
                $question->example(),
                $question->isRequired(),
                // Zero means «none declared», which a questionnaire written
                // through `FEAT-WRK-014` cannot produce any more: `C-14` made
                // minimums mandatory. It survives for the ones written before.
                $question->minWords() ?? 0,
                $question->maxWords(),
            );
        }

        return $asked;
    }
}
