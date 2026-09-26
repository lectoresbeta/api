<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Domain\Repository;

use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\Questionnaire\Domain\Entity\Question;
use LectoresBeta\Work\Questionnaire\Domain\Entity\Questionnaire;
use LectoresBeta\Work\Questionnaire\Domain\ValueObject\QuestionnaireId;

/**
 * Questionnaires are **versioned and never edited in place** (`RN-4`).
 *
 * There is no `update` and no `remove` for that reason: corrections already
 * delivered answer a specific version, and without it they would be orphans —
 * answers with no questions, and an author reading a text with no idea what
 * it replies to.
 */
interface QuestionnaireRepository
{
    public function save(Questionnaire $questionnaire): void;

    public function addQuestion(Question $question): void;

    /**
     * The version in force, or `null` if the work has none yet.
     */
    public function currentOf(WorkId $workId): ?Questionnaire;

    /**
     * Una versión concreta, que puede ya no ser la vigente.
     *
     * Existe para `FEAT-FBK-004`: quien lee una corrección entregada hace un
     * mes necesita **los enunciados de entonces**, no los de ahora. Sin esto,
     * las versiones antiguas se conservan y no sirven para nada.
     */
    public function ofVersion(WorkId $workId, int $version): ?Questionnaire;

    /**
     * @return list<Question>
     */
    public function questionsOf(QuestionnaireId $questionnaireId): array;
}
