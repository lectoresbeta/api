<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Entity;

use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionAnswerId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\QuestionId;

/**
 * One answer to one question.
 *
 * `Feedback` owns the answers; `Work` owns the questions. The question is
 * referenced by identifier and its wording is not copied here — a correction
 * resolves what it answered through the questionnaire version it started
 * with.
 *
 * The word count is stored because the questions set minimums and maximums
 * in words, and recounting on every read would be wasteful.
 */
class CorrectionAnswer
{
    private string $id;

    private string $correctionId;

    private string $questionId;

    private int $position;

    private string $text = '';

    private int $wordCount = 0;

    private \DateTimeImmutable $updatedAt;

    public function __construct(
        CorrectionAnswerId $id,
        CorrectionId $correctionId,
        QuestionId $questionId,
        int $position,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->correctionId = $correctionId->value();
        $this->questionId = $questionId->value();
        $this->position = $position;
        $this->updatedAt = $now;
    }

    public function id(): CorrectionAnswerId
    {
        return CorrectionAnswerId::fromString($this->id);
    }

    public function correctionId(): CorrectionId
    {
        return CorrectionId::fromString($this->correctionId);
    }

    public function questionId(): QuestionId
    {
        return QuestionId::fromString($this->questionId);
    }

    public function text(): string
    {
        return $this->text;
    }

    public function wordCount(): int
    {
        return $this->wordCount;
    }

    public function write(string $text, int $wordCount, \DateTimeImmutable $now): void
    {
        $this->text = $text;
        $this->wordCount = $wordCount;
        $this->updatedAt = $now;
    }
}
